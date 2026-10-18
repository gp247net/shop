<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Shipping\Contracts\ShippingProvider;
use GP247\Shop\Shipping\Data\Money;
use GP247\Shop\Shipping\Data\RateRequest;
use GP247\Shop\Shipping\Data\RateResult;
use GP247\Shop\Shipping\Data\ShippingRate;

/**
 * Serves a shipping plugin written against the old contract (AppConfig::getInfo()
 * returning one fee) as a one-rate provider, so upgrading gp247/shop never breaks a
 * site running a third-party shipping plugin.
 *
 * The legacy `value` is already in the DISPLAY currency (ADR
 * storefront_total-method-currency-contract) — the rate is flagged displayNative and
 * never converted again.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-legacy-provider
 * @aidlc-adr shop-shipping_legacy-getinfo-adapter
 */
final class LegacyGetInfoAdapter implements ShippingProvider
{
    /**
     * @param string $pluginKey   Plugin key.
     * @param string $configClass Fully-qualified AppConfig class.
     */
    private function __construct(
        private readonly string $pluginKey,
        private readonly string $configClass,
    ) {
    }

    /**
     * @param string $pluginKey Plugin key.
     * @return self|null Null when the plugin has no AppConfig with getInfo().
     */
    public static function forPlugin(string $pluginKey): ?self
    {
        if (!function_exists('gp247_extension_get_namespace')) {
            return null;
        }
        $class = gp247_extension_get_namespace(type: 'Plugins', key: $pluginKey) . '\AppConfig';
        if (!class_exists($class) || !method_exists($class, 'getInfo')) {
            return null;
        }

        return new self($pluginKey, $class);
    }

    /**
     * @param RateRequest $request Ignored: the legacy contract has no context.
     * @return RateResult One rate built from getInfo().
     */
    public function rates(RateRequest $request): RateResult
    {
        $info = (array) (new ($this->configClass))->getInfo();
        $value = is_numeric($info['value'] ?? null) ? (float) $info['value'] : 0.0;

        $rate = new ShippingRate(
            pluginKey: $this->pluginKey,
            serviceCode: '',
            title: (string) ($info['title'] ?? $this->pluginKey),
            amount: new Money((string) $value, (string) (function_exists('gp247_currency_code') ? gp247_currency_code() : '')),
            meta: ['image' => (string) ($info['image'] ?? '')],
            displayNative: true,
        );

        return new RateResult([$rate]);
    }
}
