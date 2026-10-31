<?php

namespace GP247\Shop\Services;

/**
 * Which product fields a store uses, and the rules of the fields an admin API client
 * may write (price, cost, status). Shared by the admin product form and the admin API
 * so a field turned off in a store's product settings is off on both.
 *
 * @aidlc-unit api-admin-write
 * @aidlc-story US-api-admin-write-product-fields
 */
final class ProductFieldRules
{
    /** `product_config_attribute` key gating each writable money field. */
    public const GATED = ['price' => 'product_price', 'cost' => 'product_cost'];

    /** Largest amount `shop_product.price` / `cost` (decimal 15,2) hold. */
    public const MAX_AMOUNT = '9999999999999.99';

    /**
     * Whether the product field behind $configKey is on for $storeId (empty = GLOBAL).
     *
     * @param string      $configKey e.g. `product_cost`.
     * @param string|null $storeId
     * @return bool
     */
    public static function enabled(string $configKey, ?string $storeId): bool
    {
        if (!function_exists('gp247_config')) {
            return true;
        }
        $value = gp247_config($configKey, $storeId ?: null);

        return $value !== '0' && $value !== 0;
    }

    /**
     * Validation rules of the fields an admin API client may write on a product of
     * $storeId; a field turned off for that store is left out (and so refused).
     *
     * @param string|null $storeId
     * @return array<string, string>
     */
    public static function apiRules(?string $storeId): array
    {
        $rules = [];
        foreach (self::GATED as $field => $configKey) {
            if (self::enabled($configKey, $storeId)) {
                $rules[$field] = 'numeric|min:0|max:' . self::MAX_AMOUNT;
            }
        }
        $rules['status'] = 'integer|in:0,1';

        return $rules;
    }
}
