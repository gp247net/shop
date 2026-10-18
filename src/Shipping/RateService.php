<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Models\ShopCurrency;
use GP247\Shop\Shipping\Data\RateRequest;
use GP247\Shop\Shipping\Data\RateSnapshot;
use GP247\Shop\Shipping\Data\ShippingRate;

/**
 * Asks every enabled provider for rates and returns them converted for display, as
 * ready-to-store snapshots keyed by rate id.
 *
 * - Fault isolation: a provider that throws is skipped (and skipped for the rest of a
 *   short cool-down in this session), the others still answer
 *   (NFR-AVAIL-shipping-rate-fault-isolation).
 * - Cache: answers are kept in the session per RateRequest::hash(), so Livewire
 *   re-renders never call a carrier again for the same request.
 * - Currency: a carrier rate is converted carrier currency → base → display ONCE,
 *   using shop_currency rates; a currency the shop does not know drops the rate and is
 *   reported. Legacy getInfo() rates are already display currency and kept as-is
 *   (ADR shop-shipping_rate-snapshot-and-currency).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-checkout-rates
 * @aidlc-adr shop-shipping_rate-snapshot-and-currency
 */
class RateService
{
    public const SESSION_QUOTES = 'shippingQuotes';
    public const SESSION_DOWN = 'shippingProviderDown';
    public const CACHE_TTL_SECONDS = 600;
    public const COOLDOWN_SECONDS = 60;

    /**
     * @param ShippingProviderRegistry $registry Provider resolution.
     */
    public function __construct(private readonly ShippingProviderRegistry $registry)
    {
    }

    /**
     * @return self The container-bound service.
     */
    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * Quote all enabled providers.
     *
     * @param RateRequest $request  Parcel to quote.
     * @param bool        $useCache False forces a fresh carrier call (re-quote of an expired snapshot).
     * @return array{rates: array<string, RateSnapshot>, errors: array<string, array<int, array{code: string, message: string}>>}
     */
    public function quote(RateRequest $request, bool $useCache = true): array
    {
        $hash = $request->hash();
        if ($useCache && ($cached = $this->cached($hash)) !== null) {
            return $cached;
        }

        $rates = [];
        $errors = [];
        foreach ($this->registry->enabled() as $key => $provider) {
            if ($this->isCoolingDown($key)) {
                $errors[$key] = [['code' => 'unavailable', 'message' => '']];
                continue;
            }
            try {
                $result = $provider->rates($request);
            } catch (\Throwable $e) {
                $this->coolDown($key);
                $this->report('[GP247 shipping] provider "' . $key . '" failed to quote: ' . $e->getMessage());
                $errors[$key] = [['code' => 'unavailable', 'message' => '']];
                continue;
            }
            if ($result->errors !== []) {
                $errors[$key] = $result->errors;
            }
            $own = array_values(array_filter($result->rates, static fn ($r): bool => $r instanceof ShippingRate));
            $multi = count($own) > 1;
            foreach ($own as $rate) {
                // WHY force the registry key: a provider must not answer for another plugin.
                $rate = $rate->pluginKey === $key ? $rate : new ShippingRate($key, $rate->serviceCode, $rate->title, $rate->amount, $rate->etaMinDays, $rate->etaMaxDays, $rate->expiresAt, $rate->meta, $rate->displayNative);
                $snapshot = $this->convert($rate, $multi, $request, $hash);
                if ($snapshot === null) {
                    $errors[$key][] = ['code' => 'currency_unknown', 'message' => ''];
                    continue;
                }
                $rates[$snapshot->rateId] = $snapshot;
            }
        }

        uasort($rates, static fn (RateSnapshot $a, RateSnapshot $b): int => $a->displayAmount <=> $b->displayAmount);
        $answer = ['rates' => $rates, 'errors' => $errors];
        $this->store($hash, $answer);

        return $answer;
    }

    /**
     * @param RateRequest $request Parcel.
     * @param string      $rateId  Rate id chosen by the shopper.
     * @param bool        $fresh   Bypass the cache.
     * @return RateSnapshot|null
     */
    public function find(RateRequest $request, string $rateId, bool $fresh = false): ?RateSnapshot
    {
        return $this->quote($request, !$fresh)['rates'][$rateId] ?? null;
    }

    /**
     * Convert one carrier rate into a display snapshot.
     *
     * @param ShippingRate $rate    Carrier rate.
     * @param bool         $multi   Carrier returned several services.
     * @param RateRequest  $request Request it answers.
     * @param string       $hash    $request->hash().
     * @return RateSnapshot|null Null when the carrier currency is unknown to the shop.
     */
    public function convert(ShippingRate $rate, bool $multi, RateRequest $request, string $hash): ?RateSnapshot
    {
        $displayRate = (float) (function_exists('gp247_currency_rate') ? gp247_currency_rate() : 1);
        $displayRate = $displayRate > 0 ? $displayRate : 1.0;
        $amount = $rate->amount->value();

        if ($rate->displayNative) {
            $display = $amount;
            $base = $amount / $displayRate;
        } else {
            $rates = ShopCurrency::getListRate();
            $carrierRate = (float) ($rates[$rate->amount->currency] ?? 0);
            if ($carrierRate <= 0) {
                $this->report('[GP247 shipping] provider "' . $rate->pluginKey . '" quoted in ' . $rate->amount->currency . ', a currency this shop does not have — rate dropped.');

                return null;
            }
            $base = $amount / $carrierRate;
            $display = round($base * $displayRate, $this->displayPrecision());
        }

        $now = time();

        return new RateSnapshot(
            rateId: $rate->rateId($multi),
            pluginKey: $rate->pluginKey,
            serviceCode: $rate->serviceCode,
            title: $rate->title,
            providerAmount: $rate->amount,
            baseAmount: round($base, 6),
            displayAmount: $display,
            displayCurrency: (string) (function_exists('gp247_currency_code') ? gp247_currency_code() : ''),
            displayNative: $rate->displayNative,
            requestHash: $hash,
            weightGrams: $request->totalWeightGrams(),
            etaMinDays: $rate->etaMinDays,
            etaMaxDays: $rate->etaMaxDays,
            quotedAt: $now,
            expiresAt: $rate->expiresAt ?? ($now + ShippingSettings::DEFAULT_QUOTE_TTL_SECONDS),
        );
    }

    /**
     * @return int Decimals of the display currency.
     */
    private function displayPrecision(): int
    {
        $precision = ShopCurrency::getCurrency()['precision'] ?? 2;

        return is_numeric($precision) ? max(0, (int) $precision) : 2;
    }

    /**
     * @param string $hash Request hash.
     * @return array{rates: array<string, RateSnapshot>, errors: array<string, mixed>}|null
     */
    private function cached(string $hash): ?array
    {
        $entry = (array) (session(self::SESSION_QUOTES, [])[$hash] ?? []);
        if (!isset($entry['at'], $entry['rates']) || (time() - (int) $entry['at']) > self::CACHE_TTL_SECONDS) {
            return null;
        }
        $rates = [];
        foreach ((array) $entry['rates'] as $id => $data) {
            $snapshot = RateSnapshot::fromArray($data);
            if ($snapshot !== null) {
                $rates[(string) $id] = $snapshot;
            }
        }

        return ['rates' => $rates, 'errors' => (array) ($entry['errors'] ?? [])];
    }

    /**
     * @param string $hash   Request hash.
     * @param array{rates: array<string, RateSnapshot>, errors: array<string, mixed>} $answer
     * @return void
     */
    private function store(string $hash, array $answer): void
    {
        // WHY keep only the latest few: the cache lives in the session cookie/store.
        $all = array_slice((array) session(self::SESSION_QUOTES, []), -4, null, true);
        $all[$hash] = [
            'at' => time(),
            'rates' => array_map(static fn (RateSnapshot $s): array => $s->toArray(), $answer['rates']),
            'errors' => $answer['errors'],
        ];
        session([self::SESSION_QUOTES => $all]);
    }

    /**
     * @param string $key Plugin key.
     * @return bool
     */
    private function isCoolingDown(string $key): bool
    {
        return (int) (((array) session(self::SESSION_DOWN, []))[$key] ?? 0) > time();
    }

    /**
     * @param string $key Plugin key.
     * @return void
     */
    private function coolDown(string $key): void
    {
        $down = (array) session(self::SESSION_DOWN, []);
        $down[$key] = time() + self::COOLDOWN_SECONDS;
        session([self::SESSION_DOWN => $down]);
    }

    /**
     * @param string $message Report text.
     * @return void
     */
    private function report(string $message): void
    {
        if (function_exists('gp247_report')) {
            gp247_report($message);
        }
    }
}
