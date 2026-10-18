<?php

namespace GP247\Shop\Shipping\Contracts;

use GP247\Shop\Shipping\ShippingException;

/**
 * Capability `cancel`: cancel a booked shipment while the carrier still allows it.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 * @aidlc-adr shop-shipping_provider-capability-contract
 */
interface CancelsShipments
{
    /**
     * @param string $trackingNumber Carrier tracking number.
     * @param string $storeId        Store owning the shipment (selects credentials).
     * @return void
     * @throws ShippingException When the carrier refuses (e.g. already picked up).
     */
    public function cancelShipment(string $trackingNumber, string $storeId): void;
}
