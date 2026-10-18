<?php

namespace GP247\Shop\Shipping\Contracts;

use GP247\Shop\Shipping\Data\TrackingEvent;
use GP247\Shop\Shipping\ShippingException;

/**
 * Capability `tracking`: read the current state of a shipment from the carrier
 * ("Sync" button and lazy refresh — no cron, NFR-AVAIL-shipping-no-cron).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-tracking-webhook
 * @aidlc-adr shop-shipping_webhook-routing
 */
interface TracksShipments
{
    /**
     * @param string $trackingNumber Carrier tracking number.
     * @param string $storeId        Store owning the shipment.
     * @return array<int, TrackingEvent> Events known to the carrier (at least the current one).
     * @throws ShippingException When the carrier cannot be reached or refuses.
     */
    public function track(string $trackingNumber, string $storeId): array;
}
