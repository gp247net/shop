<?php

namespace GP247\Shop\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One carrier event of a shipment (timeline). `dedupe_key` is unique: a replayed
 * webhook or repeated sync never adds a second row (MC-shipping-status-sync).
 *
 * @property int         $id
 * @property string      $shipment_id
 * @property string|null $status
 * @property string|null $provider_status
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property string|null $message
 * @property string      $source
 * @property string      $dedupe_key
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-tracking-webhook
 * @aidlc-adr shop-shipping_shipment-entity
 */
class ShopShipmentEvent extends Model
{
    public $table = GP247_DB_PREFIX . 'shop_shipment_event';
    protected $connection = GP247_DB_CONNECTION;
    const UPDATED_AT = null;

    /** @var array<int, string> */
    protected $fillable = ['shipment_id', 'status', 'provider_status', 'occurred_at', 'message', 'source', 'dedupe_key'];

    /** @var array<string, string> */
    protected $casts = ['occurred_at' => 'datetime'];
}
