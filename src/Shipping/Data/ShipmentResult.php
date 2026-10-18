<?php

namespace GP247\Shop\Shipping\Data;

use GP247\Shop\Shipping\ShipmentStatus;

/**
 * The carrier's answer to a booking.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 */
final readonly class ShipmentResult
{
    /**
     * @param string               $trackingNumber    Carrier tracking number.
     * @param ShipmentStatus       $status            Status right after booking.
     * @param Money|null           $fee               Fee the carrier will charge.
     * @param string               $trackingUrl       Public tracking page, if any.
     * @param string               $providerReference Other carrier reference (e.g. internal id).
     * @param string               $etaText           Estimated pick-up / delivery, as the carrier words it.
     * @param array<string, mixed> $meta              Extra non-secret data.
     */
    public function __construct(
        public string $trackingNumber,
        public ShipmentStatus $status = ShipmentStatus::PENDING,
        public ?Money $fee = null,
        public string $trackingUrl = '',
        public string $providerReference = '',
        public string $etaText = '',
        public array $meta = [],
    ) {
    }
}
