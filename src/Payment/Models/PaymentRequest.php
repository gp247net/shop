<?php

namespace GP247\Shop\Payment\Models;

use GP247\Core\Casts\Secret;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request to collect (direction "in") or pay out (direction "out") a sum of money
 * for some purpose — the balance of an order, a refund, a partner commission, a
 * receivable, a free-form invoice. The core keeps the request and its movements; what
 * the money means belongs to the purpose's owner (see PurposeResolver).
 *
 * "expired" is never stored: an open request past `expires_at` reads as expired, so no
 * scheduler is needed (NFR-AVAIL-payment-request-no-scheduler).
 *
 * @property int $id
 * @property string $direction
 * @property string $amount
 * @property string $currency
 * @property string $settled_amount
 * @property string $status
 * @property string $purpose
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $party_name
 * @property string|null $party_email
 * @property string|null $party_phone
 * @property string|null $description
 * @property string $store_id
 * @property string|null $gateway
 * @property string|null $gateway_ref
 * @property string|null $public_token_hash
 * @property string|null $public_token_enc Payment link token, encrypted at rest (slice S2).
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-generic-money-request
 * @aidlc-adr payment-request_generic-money-request
 */
class PaymentRequest extends Model
{
    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public const STATUS_OPEN = 'open';
    public const STATUS_PARTIAL = 'partially_settled';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_CANCELLED = 'cancelled';

    /** Display-only pseudo status for an open request past its deadline. */
    public const DISPLAY_EXPIRED = 'expired';

    public $table = GP247_DB_PREFIX . 'payment_request';
    protected $connection = GP247_DB_CONNECTION;
    protected $guarded = [];
    protected $casts = [
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'public_token_enc' => Secret::class,
    ];

    /**
     * WHY hidden: the link token is a bearer credential — keep it out of array/JSON
     * dumps (Livewire snapshots, logs); read it through PaymentRequestService.
     *
     * @var array<int, string>
     */
    protected $hidden = ['public_token_hash', 'public_token_enc'];

    /**
     * @return HasMany<PaymentMovement>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(PaymentMovement::class, 'request_id')->orderBy('paid_at')->orderBy('id');
    }

    /**
     * Whether the request is open but its deadline has passed.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }

    /**
     * Whether money may still be recorded against the request.
     *
     * @return bool
     */
    public function acceptsMoney(): bool
    {
        return !$this->isCancelled() && !$this->isExpired();
    }

    public function hasMovements(): bool
    {
        return $this->movements()->exists();
    }

    /**
     * Amount still to be collected / paid.
     *
     * @return float
     */
    public function outstanding(): float
    {
        return max(0.0, round((float) $this->amount - (float) $this->settled_amount, 3));
    }

    /**
     * The status to show: the stored one, or "expired" for an open request past its deadline.
     *
     * @return string
     */
    public function getDisplayStatusAttribute(): string
    {
        return $this->isExpired() ? self::DISPLAY_EXPIRED : (string) $this->status;
    }

    /**
     * Open requests whose deadline has passed (computed, never stored).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeExpiredOnly(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Open requests that are still within their deadline.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOpenOnly(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    /**
     * Movement type that settles this request's direction.
     *
     * @return string
     */
    public function settlingType(): string
    {
        return $this->direction === self::DIRECTION_OUT
            ? PaymentMovement::TYPE_PAYOUT
            : PaymentMovement::TYPE_COLLECT;
    }
}
