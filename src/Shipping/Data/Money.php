<?php

namespace GP247\Shop\Shipping\Data;

/**
 * An amount in a named currency, as a carrier quotes it (e.g. 30000 VND).
 *
 * The amount is kept as a decimal string so a carrier's figure survives the round
 * trip through session/JSON without float noise; callers read it with value().
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-rate-snapshot
 * @aidlc-adr shop-shipping_rate-snapshot-and-currency
 */
final readonly class Money
{
    public string $amount;
    public string $currency;

    /**
     * @param string|int|float $amount   Non-negative amount in $currency.
     * @param string           $currency ISO-4217 code (upper-cased).
     */
    public function __construct(string|int|float $amount, string $currency)
    {
        // WHY clamp: shipping money is always a magnitude; the sign lives in the order
        // totals formula (ADR shop-admin_money-sign-convention).
        $this->amount = (is_numeric($amount) && (float) $amount > 0) ? (string) $amount : '0';
        $this->currency = strtoupper(trim($currency));
    }

    /**
     * @return float The amount as a number.
     */
    public function value(): float
    {
        return (float) $this->amount;
    }

    /**
     * @return array{amount: string, currency: string}
     */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }

    /**
     * @param mixed $data Output of toArray().
     * @return self|null Null when the shape is not a money array.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data) || !isset($data['amount'], $data['currency']) || !is_numeric($data['amount'])) {
            return null;
        }

        return new self((string) $data['amount'], (string) $data['currency']);
    }
}
