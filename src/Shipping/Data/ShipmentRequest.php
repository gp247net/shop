<?php

namespace GP247\Shop\Shipping\Data;

/**
 * What the admin asks a carrier to ship (the "create shipment" form, prefilled from
 * the order and editable).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 */
final readonly class ShipmentRequest
{
    /**
     * @param string                   $orderId       Order id (carriers use it as their idempotency key).
     * @param string                   $storeId       Store owning the order.
     * @param string                   $pluginKey     Provider plugin key.
     * @param string                   $serviceCode   Carrier service.
     * @param ShippingAddress          $origin        Pick-up address.
     * @param ShippingAddress          $destination   Recipient.
     * @param array<int, ShippingItem> $items         Parcel lines.
     * @param Money                    $declaredValue Value of the goods.
     * @param Money                    $codAmount     Amount to collect (0 = none).
     * @param string                   $note          Note to the carrier.
     * @param array<string, mixed>     $extra         Values of the provider's shipmentFormFields().
     * @param int                      $attempt       1 for the first booking of the order, then 2, 3… after
     *                                                cancellations (carriers refuse to reuse a booking id).
     * @param Parcel|null              $parcel        The packed parcel the admin measured (null = not measured).
     */
    public function __construct(
        public string $orderId,
        public string $storeId,
        public string $pluginKey,
        public string $serviceCode,
        public ShippingAddress $origin,
        public ShippingAddress $destination,
        public array $items,
        public Money $declaredValue,
        public Money $codAmount,
        public string $note = '',
        public array $extra = [],
        public int $attempt = 1,
        public ?Parcel $parcel = null,
    ) {
    }

    /**
     * @return string Booking reference to send the carrier: the order id, suffixed from the second attempt.
     */
    public function bookingReference(): string
    {
        return $this->attempt > 1 ? $this->orderId . '-' . $this->attempt : $this->orderId;
    }

    /**
     * @return int Total parcel weight in grams.
     */
    public function totalWeightGrams(): int
    {
        $sum = 0;
        foreach ($this->items as $item) {
            $sum += $item->lineWeightGrams();
        }

        return $sum;
    }

    /**
     * @return array<string, mixed> Non-secret copy kept on the shipment row.
     */
    public function toArray(): array
    {
        return [
            'service_code' => $this->serviceCode,
            'origin' => $this->origin->toArray(),
            'destination' => $this->destination->toArray(),
            'items' => array_map(static fn (ShippingItem $i): array => $i->toArray(), $this->items),
            'declared_value' => $this->declaredValue->toArray(),
            'cod_amount' => $this->codAmount->toArray(),
            'note' => $this->note,
            'extra' => $this->extra,
            'parcel' => $this->parcel?->toArray(),
        ];
    }
}
