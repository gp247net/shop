<?php

namespace GP247\Shop\Shipping\Data;

/**
 * One quoted service of one carrier (e.g. a "road" service, 30000 VND, 2–3 days), in the
 * carrier's own currency. RateService converts it for display.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-checkout-rates
 * @aidlc-adr shop-shipping_rate-snapshot-and-currency
 */
final readonly class ShippingRate
{
    /**
     * @param string               $pluginKey     Shipping plugin key (registry key).
     * @param string               $serviceCode   Carrier service code ('' when the carrier has one service).
     * @param string               $title         Label shown to the shopper.
     * @param Money                $amount        Price in the carrier's currency.
     * @param int|null             $etaMinDays    Earliest delivery, in days.
     * @param int|null             $etaMaxDays    Latest delivery, in days.
     * @param int|null             $expiresAt     Unix time the quote stops being valid.
     * @param array<string, mixed> $meta          Extra, non-secret data the provider wants back.
     * @param bool                 $displayNative True when $amount is already in the display
     *                                            currency (legacy getInfo() contract) — never converted again.
     */
    public function __construct(
        public string $pluginKey,
        public string $serviceCode,
        public string $title,
        public Money $amount,
        public ?int $etaMinDays = null,
        public ?int $etaMaxDays = null,
        public ?int $expiresAt = null,
        public array $meta = [],
        public bool $displayNative = false,
    ) {
    }

    /**
     * Identifier the checkout radio carries.
     *
     * WHY the bare plugin key for single-service carriers: templates and plugins that
     * predate the contract read session('shippingMethod') / $shippingPlugins by plugin
     * key; keeping it unchanged keeps them working (ADR shop-shipping_legacy-getinfo-adapter).
     *
     * @param bool $multiService Whether this carrier returned more than one service.
     * @return string
     */
    public function rateId(bool $multiService): string
    {
        return ($multiService && $this->serviceCode !== '')
            ? $this->pluginKey . ':' . $this->serviceCode
            : $this->pluginKey;
    }
}
