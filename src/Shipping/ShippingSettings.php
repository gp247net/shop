<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Shipping\Data\ShippingAddress;

/**
 * Shop-wide shipping settings (config group `shipping_config`): pick-up origin,
 * default weight, lazy-refresh interval, COD auto-record — store_scoped like the
 * customer/order groups (GLOBAL base + per-store override; gp247_config() reads the
 * store row, then GLOBAL) (US-shop-shipping-origin-config).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-origin-config
 * @aidlc-story US-shop-shipping-cod
 */
final class ShippingSettings
{
    public const DEFAULT_WEIGHT_GRAMS = 500;
    public const DEFAULT_SYNC_MINUTES = 30;
    public const DEFAULT_QUOTE_TTL_SECONDS = 1800;

    /** @var array<int, string> Keys of the shipping_config group, in display order. */
    public const KEYS = [
        'shipping_origin_name', 'shipping_origin_phone', 'shipping_origin_province', 'shipping_origin_district',
        'shipping_origin_ward', 'shipping_origin_address', 'shipping_origin_country',
        'shipping_default_weight', 'shipping_sync_interval', 'shipping_cod_auto_record',
    ];

    /**
     * @param string $key     Config key.
     * @param string $storeId Store id.
     * @param mixed  $default Fallback.
     * @return mixed
     */
    public static function get(string $key, string $storeId, mixed $default = null): mixed
    {
        $value = function_exists('gp247_config') ? gp247_config($key, $storeId) : null;

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * @param string $storeId Store id.
     * @return ShippingAddress Pick-up address of the store.
     */
    public static function origin(string $storeId): ShippingAddress
    {
        $s = static fn (string $key): string => trim((string) self::get($key, $storeId, ''));

        return new ShippingAddress(
            name: $s('shipping_origin_name'),
            phone: $s('shipping_origin_phone'),
            country: strtoupper($s('shipping_origin_country') ?: 'VN'),
            province: $s('shipping_origin_province'),
            district: $s('shipping_origin_district'),
            ward: $s('shipping_origin_ward'),
            line1: $s('shipping_origin_address'),
        );
    }

    /**
     * @param string $storeId Store id.
     * @return int Grams assumed for a product without weight.
     */
    public static function defaultWeightGrams(string $storeId): int
    {
        $value = (int) self::get('shipping_default_weight', $storeId, self::DEFAULT_WEIGHT_GRAMS);

        return $value > 0 ? $value : self::DEFAULT_WEIGHT_GRAMS;
    }

    /**
     * @param string $storeId Store id.
     * @return int Minutes between lazy refreshes of an open shipment.
     */
    public static function syncIntervalMinutes(string $storeId): int
    {
        $value = (int) self::get('shipping_sync_interval', $storeId, self::DEFAULT_SYNC_MINUTES);

        return $value > 0 ? $value : self::DEFAULT_SYNC_MINUTES;
    }

    /**
     * @param string $storeId Store id.
     * @return bool Whether carrier-reconciled COD cash is recorded on the order automatically.
     */
    public static function codAutoRecord(string $storeId): bool
    {
        return (bool) (int) self::get('shipping_cod_auto_record', $storeId, 0);
    }

    /**
     * Payment plugin keys whose orders are paid in cash on delivery.
     *
     * @return array<int, string>
     */
    public static function codPaymentMethods(): array
    {
        return array_values(array_map('strval', (array) config('gp247-config.shop.cod_payment_methods', ['CashPayment'])));
    }
}
