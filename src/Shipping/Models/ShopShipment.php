<?php

namespace GP247\Shop\Shipping\Models;

use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Shipping\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shipment booked with a carrier for an order: carrier + service, tracking number,
 * normalised status next to the carrier's raw code, carrier fee and COD amount (in the
 * carrier's currency). Changes go through ShipmentService.
 *
 * @property string      $id
 * @property string      $order_id
 * @property string      $store_id
 * @property string      $plugin_key
 * @property string|null $service_code
 * @property string|null $tracking_number
 * @property string|null $tracking_url
 * @property string      $status
 * @property string|null $provider_status
 * @property float       $fee
 * @property float       $cod_amount
 * @property string|null $currency
 * @property int         $weight_grams
 * @property array|null  $request
 * @property array|null  $meta
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 * @aidlc-adr shop-shipping_shipment-entity
 */
class ShopShipment extends Model
{
    use \GP247\Core\Models\UuidTrait;

    public $table = GP247_DB_PREFIX . 'shop_shipment';
    protected $connection = GP247_DB_CONNECTION;
    public $incrementing = false;
    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'order_id', 'store_id', 'plugin_key', 'service_code', 'tracking_number', 'tracking_url',
        'provider_reference', 'status', 'provider_status', 'fee', 'cod_amount', 'currency',
        'weight_grams', 'request', 'meta', 'last_synced_at', 'cancelled_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fee' => 'float',
        'cod_amount' => 'float',
        'weight_grams' => 'integer',
        'request' => 'array',
        'meta' => 'array',
        'last_synced_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Assign the uuid primary key on create (same convention as ShopOrderHistory).
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (self $shipment): void {
            if (empty($shipment->id)) {
                $shipment->id = gp247_generate_id();
            }
        });
    }

    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'order_id', 'id');
    }

    /**
     * @return HasMany Events, newest first.
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShopShipmentEvent::class, 'shipment_id', 'id')->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * @return ShipmentStatus
     */
    public function shipmentStatus(): ShipmentStatus
    {
        return ShipmentStatus::tryFrom((string) $this->status) ?? ShipmentStatus::PENDING;
    }

    /**
     * @return bool Not cancelled (counts as the order's active shipment).
     */
    public function isActive(): bool
    {
        return $this->shipmentStatus() !== ShipmentStatus::CANCELLED;
    }
}
