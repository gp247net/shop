<?php

namespace GP247\Shop\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One real movement of money against a payment request: collected, paid out, or
 * refunded. The amount is always positive; the direction lives in `type`. The gateway
 * reference is unique, which is what makes a replayed webhook or a double submit
 * harmless (NFR-SEC-payment-idempotency).
 *
 * This table is a transaction log, not revenue: reports read the owner ledgers.
 *
 * @property int $id
 * @property int $request_id
 * @property string $type
 * @property string $amount
 * @property string $currency
 * @property string $gateway
 * @property string|null $gateway_ref
 * @property string $method
 * @property string|null $reference
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property string|null $admin_id
 * @property string|null $note
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-movement-ledger
 * @aidlc-adr payment-request_generic-money-request
 */
class PaymentMovement extends Model
{
    public const TYPE_COLLECT = 'collect';
    public const TYPE_PAYOUT = 'payout';
    public const TYPE_REFUND = 'refund';

    public const METHOD_GATEWAY = 'gateway';
    public const METHOD_MANUAL = 'manual';

    public $table = GP247_DB_PREFIX . 'payment_movement';
    protected $connection = GP247_DB_CONNECTION;
    protected $guarded = [];
    protected $casts = [
        'paid_at' => 'datetime',
        'reversed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<PaymentRequest, PaymentMovement>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class, 'request_id');
    }

    /**
     * Whether a movement with this gateway reference already exists.
     *
     * @param string|null $gatewayRef
     * @return bool
     */
    public static function alreadyRecorded(?string $gatewayRef): bool
    {
        if ($gatewayRef === null || $gatewayRef === '') {
            return false;
        }

        return self::where('gateway_ref', $gatewayRef)->exists();
    }

    /**
     * Signed contribution of this movement to the request's settled amount:
     * positive for the settling type, negative for a refund.
     *
     * @return float
     */
    public function signedAmount(): float
    {
        return $this->type === self::TYPE_REFUND ? -(float) $this->amount : (float) $this->amount;
    }
}
