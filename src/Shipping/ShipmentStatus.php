<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Models\ShopShippingStatus;

/**
 * Carrier-neutral shipment status — the common ground of Shopify fulfillment events
 * and EasyPost tracker statuses. Each provider maps its own codes onto these; the
 * carrier's raw code is kept next to it (shop_shipment.provider_status).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 * @aidlc-adr shop-shipping_shipment-entity
 */
enum ShipmentStatus: string
{
    case PENDING = 'pending';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERY_FAILED = 'delivery_failed';
    case DELIVERED = 'delivered';
    case RETURNING = 'returning';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case EXCEPTION = 'exception';

    /**
     * The order-level shipping status this shipment status implies
     * (domain model shop-shipping, "Suy shop_order.shipping_status").
     *
     * @return int One of ShopShippingStatus::NOT_SENT|SENDING|SHIPPING_DONE|REFUNDED.
     */
    public function orderShippingStatus(): int
    {
        return match ($this) {
            self::PENDING, self::CANCELLED => ShopShippingStatus::NOT_SENT,
            self::DELIVERED => ShopShippingStatus::SHIPPING_DONE,
            self::RETURNED => ShopShippingStatus::REFUNDED,
            default => ShopShippingStatus::SENDING,
        };
    }

    /**
     * @return bool True when no further carrier event is expected.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::RETURNED, self::CANCELLED], true);
    }

    /**
     * @return string Language key of the label.
     */
    public function labelKey(): string
    {
        return 'admin.shipment.status_' . $this->value;
    }
}
