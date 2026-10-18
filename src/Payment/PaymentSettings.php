<?php

namespace GP247\Shop\Payment;

use GP247\Core\AdminShell\Infrastructure\ReadinessCheck;
use GP247\Core\Models\AdminConfig;

/**
 * Shop-side conditions a checkout payment plugin depends on: the payment step at checkout
 * (`use_payment`) and the plugin being enabled for the store. The single source behind the
 * "Check before use" box of every payment plugin settings screen — the payment twin of
 * ShippingSettings::readiness() (NFR-MAINT-plugin-readiness-single-source).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-plugin-readiness-check
 */
final class PaymentSettings
{
    /**
     * What a store still needs before shoppers can pay with a payment plugin. Reads the
     * settings fresh (not the per-request gp247_config cache, so the box is right just after
     * a save) and never calls a gateway: checks of the plugin's own keys stay in the plugin.
     *
     * @param string      $storeId     Store being checked (GLOBAL = the shared base).
     * @param string|null $providerKey Plugin key that must be enabled for the store (null = skip that check).
     * @return array<int, ReadinessCheck> payment_enabled, then provider_enabled when a key is given.
     */
    public static function readiness(string $storeId, ?string $providerKey = null): array
    {
        $checks = [
            ReadinessCheck::failIf(in_array(self::fresh('use_payment', $storeId), ['', '0'], true), 'payment_enabled',
                gp247_language_quickly('admin.payment.warn_payment_off', 'The payment step is off at checkout: shoppers are never asked how to pay.'),
                ReadinessCheck::ERROR, self::adminUrl('admin_config.index'),
                gp247_language_quickly('admin.payment.fix_order_tab', 'Shop configuration → Order'),
                gp247_language_quickly('admin.payment.ready_payment_on', 'The payment step is on at checkout.')),
        ];

        if ($providerKey !== null) {
            $checks[] = ReadinessCheck::failIf(self::fresh($providerKey, $storeId, 'Plugins') !== '1', 'provider_enabled',
                gp247_language_quickly('admin.payment.warn_provider_off', 'This payment method is not enabled for this store.'),
                ReadinessCheck::ERROR, self::adminUrl('admin_plugin.index'),
                gp247_language_quickly('admin.payment.fix_plugins', 'Extension → Plugins'),
                gp247_language_quickly('admin.payment.ready_provider', 'This payment method is enabled for this store.'));
        }

        return $checks;
    }

    /**
     * @param string      $key     admin_config key.
     * @param string      $storeId Store id.
     * @param string|null $group   admin_config group (null = any).
     * @return string The store row, else the GLOBAL row — read from the table, not the request cache.
     */
    private static function fresh(string $key, string $storeId, ?string $group = null): string
    {
        $global = (string) (defined('GP247_STORE_ID_GLOBAL') ? GP247_STORE_ID_GLOBAL : 0);
        $rows = AdminConfig::where('key', $key)
            ->when($group !== null, static fn ($q) => $q->where('group', $group))
            ->whereIn('store_id', array_values(array_unique([$storeId, $global])))->get(['store_id', 'value'])
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
}
