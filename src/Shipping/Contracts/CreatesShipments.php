<?php

namespace GP247\Shop\Shipping\Contracts;

use GP247\Shop\Shipping\Data\ShipmentRequest;
use GP247\Shop\Shipping\Data\ShipmentResult;
use GP247\Shop\Shipping\ShippingException;

/**
 * Capability `shipments`: book a shipment with the carrier.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 * @aidlc-adr shop-shipping_provider-capability-contract
 */
interface CreatesShipments
{
    /**
     * Book the shipment. When the carrier reports that this order was already booked,
     * return the existing shipment instead of failing (idempotent).
     *
     * @param ShipmentRequest $request What to ship, from where to where, COD, carrier extras.
     * @return ShipmentResult Tracking number, status, carrier fee.
     * @throws ShippingException When the carrier refuses (message is safe to show an admin).
     */
    public function createShipment(ShipmentRequest $request): ShipmentResult;

    /**
     * Carrier-specific fields of the "create shipment" form.
     *
     * Each field: ['name' => 'ward', 'label' => '<lang key or text>', 'type' => 'text'|'select'|'checkbox'|'textarea',
     * 'required' => bool, 'options' => [value => label] (select), 'default' => mixed, 'max' => int (text length)].
     *
     * @return array<int, array<string, mixed>>
     */
    public function shipmentFormFields(): array;
}
