<?php
#GP247/Shop/Models/ShopOrderTransaction.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One payment or refund on an order — the order's payment ledger.
 *
 * An order's money is two separate stories: what the customer OWES (one state, on
 * shop_order + shop_order_total) and what the customer HAS PAID (a sequence of events,
 * here). Collapsing the second into a single `received` column is what made it
 * impossible to say when money arrived, to refund part of an order, or to recognise a
 * gateway callback that arrived twice.
 *
 * Amounts are non-negative magnitudes; the meaning lives in `type`, mirroring the sign
 * contract of ADR shop-admin_money-sign-convention. `received` on the order is now
 * DERIVED from this table: Σ payment − Σ refund.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-order-payment-ledger
 * @aidlc-adr shop_order-payment-ledger
 */
class ShopOrderTransaction extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;
    use SoftDeletes;

    /** Money collected from the customer. */
    public const TYPE_PAYMENT = 'payment';

    /** Money given back to the customer. */
    public const TYPE_REFUND = 'refund';

    public $table = GP247_DB_PREFIX . 'shop_order_transaction';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    /**
     * `exchange_rate` is decimal(16,6); Laravel hands a raw decimal back as a string,
     * and this one is divided into money, so consumers need a number
     * (ADR compat-foundation_exchange-rate-precision).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'exchange_rate' => 'float',
        'paid_at' => 'datetime',
    ];

    /**
     * Assign the uuid on insert.
     *
     * WHY here and not in the trait: UuidTrait only tells Laravel the key is a
     * non-incrementing string — it does not generate one. Sibling tables get their id
     * from the caller (`'id' => gp247_uuid()`), which works only as long as every caller
     * remembers; the ledger is written from plugins too, so it generates its own.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = gp247_uuid();
            }
        });
    }

    /**
     * The order this movement belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(ShopOrder::class, 'order_id', 'id');
    }

    /**
     * Signed contribution of this row to the amount received.
     *
     * The stored amount is always a magnitude; this is the one place that turns it into
     * a direction, so no caller has to remember which type subtracts.
     *
     * @return float
     */
    public function signedAmount(): float
    {
        return $this->type === self::TYPE_REFUND ? -(float) $this->amount : (float) $this->amount;
    }

    /**
     * Net money collected on an order: Σ payment − Σ refund.
     *
     * @param int|string $orderId
     * @return float
     */
    public static function netReceived($orderId): float
    {
        $rows = self::where('order_id', $orderId)
            ->selectRaw('type, SUM(amount) AS total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return (float) ($rows[self::TYPE_PAYMENT] ?? 0) - (float) ($rows[self::TYPE_REFUND] ?? 0);
    }

    /**
     * Whether this gateway movement was already recorded.
     *
     * Callers use it to stay idempotent, but the real guarantee is the unique index on
     * the column — a racing duplicate fails at the database, not here
     * (NFR-SEC-payment-idempotency).
     *
     * @param string|null $gatewayTransactionId
     * @return bool
     */
    public static function alreadyRecorded(?string $gatewayTransactionId): bool
    {
        if ($gatewayTransactionId === null || $gatewayTransactionId === '') {
            return false;
        }

        return self::where('gateway_transaction_id', $gatewayTransactionId)->exists();
    }
}
