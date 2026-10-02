<?php

namespace GP247\Shop\Payment;

/**
 * Currency knowledge for payment requests, without a currency table in core.
 *
 * WHY a seam: currencies (and their precision) are defined by gp247/shop, which core
 * must not depend on. A package that knows currencies registers a callable at
 * `gp247-config.payment.currencies` returning `[CODE => precision]`; with no seam,
 * any ISO-4217-shaped code is accepted at two decimals.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-generic-money-request
 * @aidlc-adr payment-request_generic-money-request
 */
final class PaymentCurrency
{
    /**
     * @return array<string, int>|null Upper-case code => precision, or null without a seam.
     */
    public static function catalog(): ?array
    {
        $seam = config('gp247-config.payment.currencies');
        if (!is_callable($seam)) {
            return null;
        }

        try {
            $list = (array) $seam();
        } catch (\Throwable $e) {
            gp247_report('PaymentCurrency seam failed: ' . $e->getMessage());

            return null;
        }

        $out = [];
        foreach ($list as $code => $precision) {
            $out[strtoupper((string) $code)] = max(0, (int) $precision);
        }

        // An empty list (seam owner not installed at the database level yet) means "no
        // catalogue", not "no currency is allowed".
        return $out === [] ? null : $out;
    }

    /**
     * Choices for a currency picker: the catalogue's codes, labelled through the optional
     * seam `gp247-config.payment.currency_labels` (callable ⇒ code => label; the code
     * itself when absent).
     *
     * @return array<string, string>|null Code => label, or null without a catalogue (free text then).
     */
    public static function options(): ?array
    {
        $catalog = self::catalog();
        if ($catalog === null) {
            return null;
        }
        $labels = [];
        $seam = config('gp247-config.payment.currency_labels');
        if (is_callable($seam)) {
            try {
                $labels = (array) $seam();
            } catch (\Throwable $e) {
                gp247_report('PaymentCurrency label seam failed: ' . $e->getMessage());
            }
        }
        $out = [];
        foreach (array_keys($catalog) as $code) {
            $label = (string) ($labels[$code] ?? $labels[strtolower($code)] ?? '');
            $out[$code] = $label !== '' ? $label : $code;
        }

        return $out;
    }

    /**
     * @param string $code
     * @return string Upper-case, trimmed code.
     */
    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * @param string $code
     * @return bool Whether the code may be used on a request.
     */
    public static function isKnown(string $code): bool
    {
        $code = self::normalize($code);
        $catalog = self::catalog();
        if ($catalog !== null) {
            return array_key_exists($code, $catalog);
        }

        return (bool) preg_match('/^[A-Z]{3}$/', $code);
    }

    /**
     * @param string $code
     * @return int Decimal places of the currency (2 when unknown).
     */
    public static function precision(string $code): int
    {
        $catalog = self::catalog();

        return $catalog[self::normalize($code)] ?? 2;
    }

    /**
     * @param float|int|string $amount
     * @param string           $code
     * @return float Amount rounded to the currency precision.
     */
    public static function round($amount, string $code): float
    {
        return round((float) $amount, self::precision($code));
    }

    /**
     * Whether two amounts differ by less than half a minor unit.
     *
     * @param float  $a
     * @param float  $b
     * @param string $code
     * @return bool
     */
    public static function same(float $a, float $b, string $code): bool
    {
        return abs($a - $b) < 0.5 / (10 ** self::precision($code));
    }
}
