<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Models\ShopCurrency;
use GP247\Shop\Shipping\Data\Money;

/**
 * Converts money between two currencies the shop knows, through the base currency
 * (shop_currency.exchange_rate is "units per 1 base"). Offered to providers so no
 * carrier plugin re-implements currency math (RISK-TECH-plugin-currency-contract-drift).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-rate-snapshot
 * @aidlc-adr shop-shipping_rate-snapshot-and-currency
 */
final class ShippingCurrency
{
    /**
     * @param Money  $money     Amount to convert.
     * @param string $to        Target currency code.
     * @param int    $precision Decimals of the result.
     * @return Money|null Null when either currency is unknown to the shop.
     */
    public static function convert(Money $money, string $to, int $precision = 2): ?Money
    {
        $to = strtoupper($to);
        if ($money->currency === $to) {
            return new Money((string) round($money->value(), $precision), $to);
        }
        $rates = ShopCurrency::getListRate();
        $from = (float) ($rates[$money->currency] ?? 0);
        $target = (float) ($rates[$to] ?? 0);
        if ($from <= 0 || $target <= 0) {
            return null;
        }

        return new Money((string) round($money->value() / $from * $target, $precision), $to);
    }
}
