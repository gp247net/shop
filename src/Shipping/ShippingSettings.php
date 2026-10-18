<?php

namespace GP247\Shop\Shipping;

use GP247\Core\AdminShell\Infrastructure\ReadinessCheck;
use GP247\Core\Models\AdminConfig;
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
 * @aidlc-story US-shop-shipping-readiness-check
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
     * What a store still needs before a carrier can quote — the single source behind the
     * warnings of the Shipping tab and the "Check before use" box of every shipping plugin
     * (US-shop-shipping-readiness-check, NFR-MAINT-plugin-readiness-single-source). Reads
     * the settings fresh (not the per-request gp247_config cache) and never calls a carrier.
     *
     * @param string               $storeId     Store being checked (GLOBAL = the shared base).
     * @param string|null          $providerKey Plugin key that must be enabled for the store (null = any carrier).
     * @param array<string, mixed> $overrides   Values being edited but not saved yet (the Shipping tab).
     * @return array<int, ReadinessCheck> shipping_enabled, customer_city, customer_ward, origin, provider_enabled.
     */
    public static function readiness(string $storeId, ?string $providerKey = null, array $overrides = []): array
    {
        $v = static fn (string $key): string => array_key_exists($key, $overrides)
            ? trim((string) $overrides[$key])
            : self::fresh($key, $storeId);
        $on = static fn (string $key): bool => !in_array($v($key), ['', '0'], true);
        $hub = self::adminUrl('admin_config.index');
        $shippingTab = gp247_language_quickly('admin.shipping.fix_shipping_tab', 'Shop configuration → Shipping');
        $customerTab = gp247_language_quickly('admin.shipping.fix_customer_tab', 'Shop configuration → Customer');

        $checks = [
            ReadinessCheck::failIf(!$on('use_shipping'), 'shipping_enabled',
                gp247_language_quickly('admin.shipping.warn_shipping_off', 'Shipping is off: shoppers cannot choose a delivery method at checkout.'),
                ReadinessCheck::ERROR, $hub, $shippingTab,
                gp247_language_quickly('admin.shipping.ready_shipping_on', 'Shipping is on at checkout.')),
            ReadinessCheck::failIf(!$on('customer_city'), 'customer_city', gp247_language_render('admin.shipping.warn_city'),
                ReadinessCheck::ERROR, $hub, $customerTab,
                gp247_language_quickly('admin.shipping.ready_city', 'Customer addresses ask for the province / city.')),
            ReadinessCheck::failIf(!$on('customer_ward') && !$on('customer_district'), 'customer_ward', gp247_language_render('admin.shipping.warn_ward'),
                ReadinessCheck::ERROR, $hub, $customerTab,
                gp247_language_quickly('admin.shipping.ready_ward', 'Customer addresses ask for the ward or district.')),
            ReadinessCheck::failIf($v('shipping_origin_province') === '' || $v('shipping_origin_address') === '' || $v('shipping_origin_phone') === '',
                'origin', gp247_language_render('admin.shipping.warn_origin'), ReadinessCheck::ERROR, $hub, $shippingTab,
                gp247_language_quickly('admin.shipping.ready_origin', 'The pick-up warehouse has a province, street address and phone.')),
        ];

        $plugins = self::adminUrl('admin_plugin.index');
        $pluginsLabel = gp247_language_quickly('admin.shipping.fix_plugins', 'Extension → Plugins');
        if ($providerKey !== null) {
            $enabled = self::fresh($providerKey, $storeId) === '1' && ShippingProviderRegistry::instance()->provider($providerKey) !== null;
            $checks[] = ReadinessCheck::failIf(!$enabled, 'provider_enabled',
                gp247_language_quickly('admin.shipping.warn_provider_off', 'This carrier is not enabled for this store.'), ReadinessCheck::ERROR, $plugins, $pluginsLabel,
                gp247_language_quickly('admin.shipping.ready_provider', 'This carrier is enabled for this store.'));
        } else {
            $checks[] = ReadinessCheck::failIf(ShippingProviderRegistry::instance()->enabled() === [], 'provider_enabled',
                gp247_language_render('admin.shipping.warn_no_provider'), ReadinessCheck::ERROR, $plugins, $pluginsLabel,
                gp247_language_quickly('admin.shipping.ready_any_provider', 'At least one carrier is enabled for this store.'));
        }

        return $checks;
    }

    /**
     * @param string $key     Config key.
     * @param string $storeId Store id.
     * @return string The store row, else the GLOBAL row — read from the table, not the request cache.
     */
    private static function fresh(string $key, string $storeId): string
    {
        $global = (string) (defined('GP247_STORE_ID_GLOBAL') ? GP247_STORE_ID_GLOBAL : 0);
        $rows = AdminConfig::where('key', $key)->whereIn('store_id', array_values(array_unique([$storeId, $global])))->get(['store_id', 'value'])
            ->mapWithKeys(static fn (AdminConfig $row): array => [(string) $row->store_id => trim((string) $row->value)]);
        $value = (string) ($rows[$storeId] ?? '');

        return $value !== '' ? $value : (string) ($rows[$global] ?? '');
    }

    /**
     * @param string $route Admin route name.
     * @return string URL, or '' when the route is not registered.
     */
    private static function adminUrl(string $route): string
    {
        return function_exists('gp247_route_admin') && \Illuminate\Support\Facades\Route::has($route) ? (string) gp247_route_admin($route) : '';
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
