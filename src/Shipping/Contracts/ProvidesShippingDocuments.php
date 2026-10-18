<?php

namespace GP247\Shop\Shipping\Contracts;

use GP247\Shop\Shipping\Data\ShippingDocument;
use GP247\Shop\Shipping\ShippingException;

/**
 * Capability `documents`: fetch a printable document (label) of a shipment. The core
 * streams it to the admin; nothing is stored on disk and the carrier token never
 * reaches the browser.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 * @aidlc-adr shop-shipping_provider-capability-contract
 */
interface ProvidesShippingDocuments
{
    /**
     * @param string               $trackingNumber Carrier tracking number.
     * @param string               $storeId        Store owning the shipment.
     * @param array<string, mixed> $options        Carrier options (e.g. ['page_size' => 'A6']).
     * @return ShippingDocument
     * @throws ShippingException When the carrier cannot produce it.
     */
    public function document(string $trackingNumber, string $storeId, array $options = []): ShippingDocument;
}
