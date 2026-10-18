<?php

namespace GP247\Shop\Shipping\Data;

/**
 * The rate the shopper chose, frozen: carrier price in the carrier's currency, the
 * base and display figures it was converted to, and the request it was quoted for.
 * Kept in session('shippingRate') during checkout and stored on the order
 * (shop_order.shipping_rate) so the order carries exactly the price the shopper saw
 * (US-shop-shipping-rate-snapshot, MC-shipping-rate-consistency).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-rate-snapshot
 * @aidlc-adr shop-shipping_rate-snapshot-and-currency
 */
final readonly class RateSnapshot
{
    /**
     * @param string   $rateId          Checkout radio id (plugin key or key:service).
     * @param string   $pluginKey       Shipping plugin key.
     * @param string   $serviceCode     Carrier service code.
     * @param string   $title           Label shown.
     * @param Money    $providerAmount  Carrier price in the carrier currency.
     * @param float    $baseAmount      Converted to the shop base currency.
     * @param float    $displayAmount   Converted to the display currency (what the order stores).
     * @param string   $displayCurrency Display currency code at quote time.
     * @param bool     $displayNative   True when the plugin already returned display currency (legacy).
     * @param string   $requestHash     RateRequest::hash() it was quoted for.
     * @param int      $weightGrams     Parcel weight used for the quote.
     * @param int|null $etaMinDays      Earliest delivery, days.
     * @param int|null $etaMaxDays      Latest delivery, days.
     * @param int      $quotedAt        Unix time of the quote.
     * @param int      $expiresAt       Unix time the price stops being guaranteed.
     */
    public function __construct(
        public string $rateId,
        public string $pluginKey,
        public string $serviceCode,
        public string $title,
        public Money $providerAmount,
        public float $baseAmount,
        public float $displayAmount,
        public string $displayCurrency,
        public bool $displayNative,
        public string $requestHash,
        public int $weightGrams,
        public ?int $etaMinDays,
        public ?int $etaMaxDays,
        public int $quotedAt,
        public int $expiresAt,
    ) {
    }

    /**
     * @param RateRequest $request Request for the current checkout state.
     * @return bool True when this snapshot was quoted for exactly that request.
     */
    public function matches(RateRequest $request): bool
    {
        return hash_equals($this->requestHash, $request->hash());
    }

    /**
     * @param int|null $now Unix time (null = now).
     * @return bool
     */
    public function isExpired(?int $now = null): bool
    {
        return ($now ?? time()) >= $this->expiresAt;
    }

    /**
     * @return array<string, mixed> JSON-safe representation (session / order column).
     */
    public function toArray(): array
    {
        return [
            'rate_id' => $this->rateId,
            'plugin_key' => $this->pluginKey,
            'service_code' => $this->serviceCode,
            'title' => $this->title,
            'provider_amount' => $this->providerAmount->toArray(),
            'base_amount' => $this->baseAmount,
            'display_amount' => $this->displayAmount,
            'display_currency' => $this->displayCurrency,
            'display_native' => $this->displayNative,
            'request_hash' => $this->requestHash,
            'weight_grams' => $this->weightGrams,
            'eta_min_days' => $this->etaMinDays,
            'eta_max_days' => $this->etaMaxDays,
            'quoted_at' => $this->quotedAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    /**
     * @param mixed $data Output of toArray() (possibly round-tripped through JSON).
     * @return self|null Null when the data is not a snapshot.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data) || !isset($data['rate_id'], $data['plugin_key'], $data['request_hash'], $data['display_amount'])) {
            return null;
        }
        $money = Money::fromArray($data['provider_amount'] ?? null);
        if ($money === null) {
            return null;
        }
        $int = static fn ($v): ?int => $v === null || $v === '' ? null : (int) $v;

        return new self(
            rateId: (string) $data['rate_id'],
            pluginKey: (string) $data['plugin_key'],
            serviceCode: (string) ($data['service_code'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            providerAmount: $money,
            baseAmount: (float) ($data['base_amount'] ?? 0),
            displayAmount: (float) $data['display_amount'],
            displayCurrency: (string) ($data['display_currency'] ?? ''),
            displayNative: (bool) ($data['display_native'] ?? false),
            requestHash: (string) $data['request_hash'],
            weightGrams: (int) ($data['weight_grams'] ?? 0),
            etaMinDays: $int($data['eta_min_days'] ?? null),
            etaMaxDays: $int($data['eta_max_days'] ?? null),
            quotedAt: (int) ($data['quoted_at'] ?? 0),
            expiresAt: (int) ($data['expires_at'] ?? 0),
        );
    }
}
