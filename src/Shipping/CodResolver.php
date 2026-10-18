<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Models\ShopOrder;

/**
 * How much a carrier should collect on delivery for an order: the outstanding balance
 * when the order is paid with a cash-on-delivery method
 * (`gp247-config.shop.cod_payment_methods`), otherwise nothing (US-shop-shipping-cod).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-cod
 */
final class CodResolver
{
    /**
     * @param ShopOrder $order Order.
     * @return float Amount in the order currency (>= 0).
     */
    public static function forOrder(ShopOrder $order): float
    {
        if (!in_array((string) $order->payment_method, ShippingSettings::codPaymentMethods(), true)) {
            return 0.0;
        }

        return max(0.0, (float) $order->balance);
    }
}
