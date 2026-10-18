<?php

namespace GP247\Shop\Shipping\Contracts;

use GP247\Shop\Shipping\Data\RateRequest;
use GP247\Shop\Shipping\Data\RateResult;

/**
 * A carrier, plugged in by a shipping plugin. Quoting is the only required
 * capability; creating, cancelling, documents, tracking and webhooks are optional
 * interfaces a provider implements only when the carrier really offers them
 * (ADR shop-shipping_provider-capability-contract).
 *
 * Registered in the plugin's Provider.php:
 *   config(['gp247-config.shop.shipping_providers.<PluginKey>' => [
 *       'driver' => MyProvider::class,
 *       'capabilities' => ['rates', 'shipments', 'cancel', 'documents', 'tracking', 'webhook', 'cod'],
 *   ]]);
 * The plugin's extension row (configCode "Shipping") still decides whether the
 * provider is enabled, per store.
 *
 * rates() must not throw for ordinary failures (address incomplete, carrier said
 * no): return RateResult::error(). Credentials are resolved from
 * RateRequest::$storeId, never from session state.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-provider-contract
 * @aidlc-adr shop-shipping_provider-capability-contract
 */
interface ShippingProvider
{
    public const CAP_RATES = 'rates';
    public const CAP_SHIPMENTS = 'shipments';
    public const CAP_CANCEL = 'cancel';
    public const CAP_DOCUMENTS = 'documents';
    public const CAP_TRACKING = 'tracking';
    public const CAP_WEBHOOK = 'webhook';
    public const CAP_COD = 'cod';

    /**
     * Quote every service the carrier offers for this parcel.
     *
     * @param RateRequest $request Origin, destination, items (grams), declared value.
     * @return RateResult Rates in the carrier's currency, or errors.
     */
    public function rates(RateRequest $request): RateResult;
}
