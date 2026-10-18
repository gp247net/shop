<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Models\ShopCurrency;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Services\CartItem;
use GP247\Shop\Shipping\Data\Money;
use GP247\Shop\Shipping\Data\RateRequest;
use GP247\Shop\Shipping\Data\ShippingAddress;

/**
 * Builds a RateRequest from what checkout knows: the (possibly not yet committed)
 * address block, the checkout cart lines and the store. Product weight comes from
 * shop_product.weight + weight_class (grams), falling back to the store's default;
 * the declared value is in the shop's base currency (prices are stored in base).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-checkout-rates
 */
final class RateRequestFactory
{
    /**
     * @param array<string, mixed> $address       Address block (first_name, phone, city, district, ward, address1…).
     * @param iterable<mixed>      $cartItems     CartItem objects or their array form (session('dataCheckout')).
     * @param string               $storeId       Store being checked out.
     * @param string               $paymentMethod Payment plugin key chosen so far ('' = not yet).
     * @return RateRequest
     */
    public static function fromCheckout(array $address, iterable $cartItems, string $storeId, string $paymentMethod = ''): RateRequest
    {
        $base = self::baseCurrency();
        $items = [];
        $total = 0.0;

        foreach ($cartItems as $raw) {
            $cartItem = $raw instanceof CartItem ? $raw : CartItem::hydrate($raw);
            $product = ShopProduct::find($cartItem->id);
            if ($product === null) {
                continue;
            }
            $qty = max(1, (int) $cartItem->qty);
            $unit = (float) $product->getFinalPrice()
                + (function_exists('gp247_cart_options_price') ? (float) gp247_cart_options_price($cartItem->options) : 0.0);
            // One shipping profile for quote and booking (ADR shop-shipping_product-shipping-profile).
            $item = ShippingItemFactory::fromProduct($product, $qty, $cartItem->options, new Money((string) round($unit, 2), $base), $storeId, (string) $cartItem->name);
            if ($item === null) {
                continue; // digital goods are not part of the parcel
            }
            $items[] = $item;
            $total += $unit * $qty;
        }

        $declared = new Money((string) round($total, 2), $base);
        $cod = in_array($paymentMethod, ShippingSettings::codPaymentMethods(), true) ? $declared : null;

        return new RateRequest(
            storeId: $storeId,
            origin: ShippingSettings::origin($storeId),
            destination: ShippingAddress::fromShopBlock($address),
            items: $items,
            declaredValue: $declared,
            codAmount: $cod,
            paymentMethod: $paymentMethod,
        );
    }

    /**
     * @return string Base currency code (display currency when no base is designated).
     */
    public static function baseCurrency(): string
    {
        $code = ShopCurrency::getBaseCode();
        if ($code === null || $code === '') {
            $code = function_exists('gp247_currency_code') ? (string) gp247_currency_code() : '';
        }

        return strtoupper($code);
    }
}
