<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Shipping\Contracts\CancelsShipments;
use GP247\Shop\Shipping\Contracts\CreatesShipments;
use GP247\Shop\Shipping\Contracts\HandlesShippingWebhooks;
use GP247\Shop\Shipping\Contracts\ProvidesShippingDocuments;
use GP247\Shop\Shipping\Contracts\ShippingProvider;
use GP247\Shop\Shipping\Contracts\TracksShipments;

/**
 * Resolves shipping providers: which ones are enabled for the effective store (the
 * plugin's extension row, configCode "Shipping"), which driver serves each
 * (`gp247-config.shop.shipping_providers`) and what it can really do (declared
 * capabilities ∩ implemented interfaces). An enabled shipping plugin that did not
 * register a driver is served by LegacyGetInfoAdapter. A broken driver is skipped and
 * reported once per request (ADR shop-shipping_provider-capability-contract).
 *
 * Resolved from the container, so tests can bind a registry with fake providers.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-provider-contract
 * @aidlc-story US-shop-shipping-legacy-provider
 * @aidlc-adr shop-shipping_provider-capability-contract
 */
class ShippingProviderRegistry
{
    /** @var array<string, class-string> Capability => interface it requires. */
    private const CAPABILITY_INTERFACES = [
        ShippingProvider::CAP_RATES => ShippingProvider::class,
        ShippingProvider::CAP_SHIPMENTS => CreatesShipments::class,
        ShippingProvider::CAP_CANCEL => CancelsShipments::class,
        ShippingProvider::CAP_DOCUMENTS => ProvidesShippingDocuments::class,
        ShippingProvider::CAP_TRACKING => TracksShipments::class,
        ShippingProvider::CAP_WEBHOOK => HandlesShippingWebhooks::class,
        ShippingProvider::CAP_COD => CreatesShipments::class,
    ];

    /** @var array<string, ShippingProvider|null> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $reported = [];

    /**
     * @return ShippingProviderRegistry The container-bound registry.
     */
    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * Plugin keys of the shipping plugins enabled for the effective store
     * (gp247_plugin_store_id), in their configured order.
     *
     * @return array<int, string>
     */
    public function enabledKeys(): array
    {
        if (!function_exists('gp247_extension_get_via_code') || !gp247_config('use_shipping')) {
            return [];
        }

        return array_keys(gp247_extension_get_via_code(code: 'shipping'));
    }

    /**
     * Enabled providers for the effective store.
     *
     * @return array<string, ShippingProvider> pluginKey => provider.
     */
    public function enabled(): array
    {
        $out = [];
        foreach ($this->enabledKeys() as $key) {
            $provider = $this->provider($key);
            if ($provider !== null) {
                $out[$key] = $provider;
            }
        }

        return $out;
    }

    /**
     * The provider serving a plugin key, whether or not it is enabled for this store
     * (webhooks and existing shipments must keep resolving).
     *
     * @param string $key Plugin key.
     * @return ShippingProvider|null Null when neither a driver nor a legacy AppConfig exists.
     */
    public function provider(string $key): ?ShippingProvider
    {
        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }

        $definition = $this->definition($key);
        $provider = null;
        if ($definition !== null) {
            $driver = (string) ($definition['driver'] ?? '');
            try {
                $candidate = ($driver !== '' && class_exists($driver)) ? app($driver) : null;
                $provider = $candidate instanceof ShippingProvider ? $candidate : null;
            } catch (\Throwable $e) {
                $provider = null;
            }
            if ($provider === null) {
                $this->reportOnce($key, '[GP247 shipping] provider "' . $key . '" registers driver "' . $driver . '" that is missing or not a ShippingProvider — skipped.');
            }
        } else {
            $provider = LegacyGetInfoAdapter::forPlugin($key);
        }

        return $this->instances[$key] = $provider;
    }

    /**
     * Effective capabilities: declared in the registry AND backed by the interface.
     *
     * @param string $key Plugin key.
     * @return array<int, string> Subset of ShippingProvider::CAP_*.
     */
    public function capabilities(string $key): array
    {
        $provider = $this->provider($key);
        if ($provider === null) {
            return [];
        }
        $definition = $this->definition($key);
        $declared = $definition === null
            ? [ShippingProvider::CAP_RATES]
            : array_values(array_unique(array_merge([ShippingProvider::CAP_RATES], (array) ($definition['capabilities'] ?? []))));

        $effective = [];
        foreach ($declared as $capability) {
            $interface = self::CAPABILITY_INTERFACES[$capability] ?? null;
            if ($interface !== null && $provider instanceof $interface) {
                $effective[] = $capability;
            } elseif ($interface !== null) {
                $this->reportOnce($key . ':' . $capability, '[GP247 shipping] provider "' . $key . '" declares "' . $capability . '" but does not implement ' . $interface . ' — capability ignored.');
            }
        }

        return $effective;
    }

    /**
     * @param string $key        Plugin key.
     * @param string $capability ShippingProvider::CAP_*.
     * @return bool
     */
    public function supports(string $key, string $capability): bool
    {
        return in_array($capability, $this->capabilities($key), true);
    }

    /**
     * Plugin keys (enabled for this store) that can book shipments.
     *
     * @return array<int, string>
     */
    public function shipmentCapableKeys(): array
    {
        return array_values(array_filter(
            $this->enabledKeys(),
            fn (string $key): bool => $this->supports($key, ShippingProvider::CAP_SHIPMENTS),
        ));
    }

    /**
     * @param string $key Plugin key.
     * @return array<string, mixed>|null Registry entry, or null when not registered.
     */
    public function definition(string $key): ?array
    {
        $all = (array) config('gp247-config.shop.shipping_providers', []);
        $definition = $all[$key] ?? null;

        return is_array($definition) ? $definition : null;
    }

    /**
     * @param string $key     De-duplication key.
     * @param string $message Report text.
     * @return void
     */
    private function reportOnce(string $key, string $message): void
    {
        if (isset($this->reported[$key])) {
            return;
        }
        $this->reported[$key] = true;
        if (function_exists('gp247_report')) {
            gp247_report($message);
        }
    }
}
