<?php

namespace GP247\Shop\Payment;

use GP247\Shop\Payment\Contracts\PaymentGateway;
use GP247\Shop\Payment\Contracts\ProvidesRefundSources;
use GP247\Shop\Payment\Contracts\ValidatesRequest;
use GP247\Shop\Payment\Gateways\ManualGateway;
use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\Support\CollectResult;
use GP247\Shop\Payment\Support\RefundSource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * The one place that creates payment requests and writes money against them.
 *
 * recordMovement() is the single seam every path goes through — manual receipts,
 * gateway webhooks, refunds. It is idempotent on the gateway reference, refuses money
 * a request cannot take, re-derives the request's settled amount and status, and
 * calls the purpose owner inside the same transaction so a refused write leaves no
 * half-recorded money (ADR payment-request_generic-money-request).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-movement-ledger
 * @aidlc-story US-payment-request-generic-money-request
 * @aidlc-story US-payment-request-public-pay-link
 * @aidlc-adr payment-request_generic-money-request
 */
class PaymentRequestService
{
    /** Route of the public pay page (gp247/front); absent on a site without front. */
    public const PAY_ROUTE = 'payment_request.pay';

    /** @var bool|null Memoised readiness (tables exist) for this request. */
    private static ?bool $ready = null;

    /**
     * Whether payment requests are installed at the database level. The code can be
     * newer than the database (composer update without gp247:shop-update), so every entry
     * point — screens, buttons, purpose lists — checks this instead of assuming it.
     *
     * @return bool
     */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                $schema = \Illuminate\Support\Facades\Schema::connection(GP247_DB_CONNECTION);
                self::$ready = $schema->hasTable((new PaymentRequest())->getTable())
                    && $schema->hasTable((new PaymentMovement())->getTable());
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /**
     * Forget the memoised readiness (after running the upgrade in the same process).
     *
     * @return void
     */
    public static function forgetReadiness(): void
    {
        self::$ready = null;
    }

    public function __construct(private PurposeRegistry $purposes, private GatewayRegistry $gateways)
    {
    }

    /**
     * Create an open request.
     *
     * @param array<string, mixed> $data direction, amount, currency, purpose, party_*, description,
     *                                   subject_type, subject_id, expires_at, store_id, created_by, metadata.
     * @return PaymentRequest
     * @throws \InvalidArgumentException When the data is not a valid request.
     */
    public function create(array $data): PaymentRequest
    {
        $direction = (string) ($data['direction'] ?? '');
        $purpose = (string) ($data['purpose'] ?? '');
        $currency = PaymentCurrency::normalize((string) ($data['currency'] ?? ''));

        $this->assertDirection($direction);
        $this->assertPurpose($purpose, $direction);
        if (!PaymentCurrency::isKnown($currency)) {
            throw new \InvalidArgumentException("Unknown currency [{$currency}]");
        }
        $amount = PaymentCurrency::round($data['amount'] ?? 0, $currency);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        $storeId = (string) ($data['store_id'] ?? $this->currentStoreId());
        $this->askOwner($purpose, [
            'direction' => $direction,
            'purpose' => $purpose,
            'amount' => $amount,
            'currency' => $currency,
            'store_id' => $storeId,
            'subject_type' => self::nullable($data['subject_type'] ?? null),
            'subject_id' => self::nullable($data['subject_id'] ?? null),
        ]);

        return PaymentRequest::create([
            'direction' => $direction,
            'amount' => $amount,
            'currency' => $currency,
            'settled_amount' => 0,
            'status' => PaymentRequest::STATUS_OPEN,
            'purpose' => $purpose,
            'subject_type' => self::nullable($data['subject_type'] ?? null),
            'subject_id' => self::nullable($data['subject_id'] ?? null),
            'party_name' => self::nullable($data['party_name'] ?? null),
            'party_email' => self::nullable($data['party_email'] ?? null),
            'party_phone' => self::nullable($data['party_phone'] ?? null),
            'description' => self::nullable($data['description'] ?? null),
            'store_id' => $storeId,
            'expires_at' => self::nullable($data['expires_at'] ?? null),
            'created_by' => self::nullable($data['created_by'] ?? null),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Update the editable fields of a request. Direction, amount and currency are
     * locked once money has moved.
     *
     * @param PaymentRequest       $request
     * @param array<string, mixed> $data
     * @return PaymentRequest
     * @throws \LogicException When a locked field would change.
     */
    public function update(PaymentRequest $request, array $data): PaymentRequest
    {
        $locked = $request->hasMovements();
        $changes = [];

        foreach (['direction', 'amount', 'currency'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $new = $field === 'currency'
                ? PaymentCurrency::normalize((string) $data[$field])
                : ($field === 'amount' ? PaymentCurrency::round($data[$field], $request->currency) : (string) $data[$field]);
            $old = $field === 'amount' ? (float) $request->amount : (string) $request->{$field};
            $same = $field === 'amount' ? PaymentCurrency::same($old, (float) $new, $request->currency) : $old === $new;
            if ($same) {
                continue;
            }
            if ($locked) {
                throw new \LogicException("Field [{$field}] is locked once money has moved");
            }
            $changes[$field] = $new;
        }

        $direction = $changes['direction'] ?? $request->direction;
        $purpose = (string) ($data['purpose'] ?? $request->purpose);
        $this->assertDirection($direction);
        $this->assertPurpose($purpose, $direction);
        if (isset($changes['currency']) && !PaymentCurrency::isKnown($changes['currency'])) {
            throw new \InvalidArgumentException("Unknown currency [{$changes['currency']}]");
        }
        if (isset($changes['amount']) && $changes['amount'] <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        $changes['purpose'] = $purpose;

        foreach (['subject_type', 'subject_id', 'party_name', 'party_email', 'party_phone', 'description', 'expires_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = self::nullable($data[$field]);
            }
        }
        if (array_key_exists('metadata', $data)) {
            $changes['metadata'] = $data['metadata'];
        }
        $this->askOwner($purpose, [
            'id' => $request->id,
            'direction' => $direction,
            'purpose' => $purpose,
            'amount' => (float) ($changes['amount'] ?? $request->amount),
            'currency' => (string) ($changes['currency'] ?? $request->currency),
            'store_id' => (string) $request->store_id,
            'subject_type' => array_key_exists('subject_type', $changes) ? $changes['subject_type'] : $request->subject_type,
            'subject_id' => array_key_exists('subject_id', $changes) ? $changes['subject_id'] : $request->subject_id,
        ]);

        $request->update($changes);

        return $request->fresh();
    }

    /**
     * Cancel an open request that holds no money.
     *
     * @param PaymentRequest $request
     * @param string|null    $adminId
     * @return PaymentRequest
     * @throws \DomainException When money has already moved.
     */
    public function cancel(PaymentRequest $request, ?string $adminId = null): PaymentRequest
    {
        if ($request->hasMovements()) {
            throw new \DomainException('A request that holds money cannot be cancelled');
        }
        if ($request->isCancelled()) {
            return $request;
        }
        $request->update([
            'status' => PaymentRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $adminId,
        ]);

        return $request->fresh();
    }

    /**
     * Record money moved by hand (bank transfer, cash).
     *
     * @param PaymentRequest $request
     * @param float|string   $amount
     * @param string|null    $reference Free reference typed by the admin.
     * @param mixed          $paidAt    Any Carbon-parsable value; defaults to now.
     * @param string|null    $adminId
     * @param string|null    $note
     * @return PaymentMovement
     */
    public function recordManual(PaymentRequest $request, $amount, ?string $reference = null, $paidAt = null, ?string $adminId = null, ?string $note = null): PaymentMovement
    {
        return $this->recordMovement($request, [
            'type' => $request->settlingType(),
            'amount' => $amount,
            'gateway' => ManualGateway::KEY,
            'gateway_ref' => null,
            'method' => PaymentMovement::METHOD_MANUAL,
            'reference' => $reference,
            'paid_at' => $paidAt,
            'admin_id' => $adminId,
            'note' => $note,
        ]);
    }

    /**
     * The single seam for writing a movement.
     *
     * @param PaymentRequest       $request
     * `confirmed` = true is for money a gateway reports as already moved (webhook, return
     * page, a refund it accepted): it is recorded even past the amount or on a request that
     * no longer takes money, with a reconciliation note and a report — refusing it would
     * leave the ledger short of what really happened (US-payment-request-confirmed-money).
     * Money recorded by hand never sets it.
     *
     * @param PaymentRequest       $request
     * @param array<string, mixed> $data type (default: the settling type), amount, gateway, gateway_ref,
     *                                   method, reference, paid_at, admin_id, note, metadata,
     *                                   confirmed (bool), refund_of (id of the collect a refund gives back).
     * @return PaymentMovement The new row, or the existing one when this was a replay.
     * @throws \DomainException When the request cannot take this money.
     * @throws \Throwable When the purpose owner refuses (the movement is rolled back).
     *
     * @aidlc-story US-payment-request-confirmed-money
     */
    public function recordMovement(PaymentRequest $request, array $data): PaymentMovement
    {
        $gatewayRef = self::nullable($data['gateway_ref'] ?? null);
        if ($gatewayRef !== null) {
            $existing = PaymentMovement::where('gateway_ref', $gatewayRef)->first();
            if ($existing !== null) {
                return $existing; // replay — the money was already recorded
            }
        }

        $amount = PaymentCurrency::round($data['amount'] ?? 0, $request->currency);
        if ($amount <= 0) {
            throw new \DomainException('Movement amount must be positive');
        }
        $type = (string) ($data['type'] ?? $request->settlingType());
        if (!in_array($type, [PaymentMovement::TYPE_COLLECT, PaymentMovement::TYPE_PAYOUT, PaymentMovement::TYPE_REFUND], true)) {
            throw new \DomainException("Unknown movement type [{$type}]");
        }

        $confirmed = !empty($data['confirmed']);

        return DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($request, $data, $gatewayRef, $amount, $type, $confirmed) {
            /** @var PaymentRequest $locked */
            $locked = PaymentRequest::lockForUpdate()->findOrFail($request->id);
            $mismatches = [];

            if (!$locked->acceptsMoney()) {
                $mismatches[] = 'request ' . ($locked->isCancelled() ? 'is cancelled' : 'has expired');
            }
            if (!$this->purposes->isSettleable($locked->purpose)) {
                $mismatches[] = 'purpose "' . $locked->purpose . '" is not available any more';
            }

            $signed = $type === PaymentMovement::TYPE_REFUND ? -$amount : $amount;
            $newSettled = round((float) $locked->settled_amount + $signed, 3);
            if ($signed > 0
                && $newSettled > (float) $locked->amount
                && !PaymentCurrency::same($newSettled, (float) $locked->amount, $locked->currency)
                && !$this->purposes->allowsOver($locked->purpose)) {
                $mismatches[] = 'exceeds the requested ' . $locked->amount . ' ' . $locked->currency . ' (settled would be ' . $newSettled . ')';
            }
            if ($newSettled < 0 && !PaymentCurrency::same($newSettled, 0.0, $locked->currency)) {
                $mismatches[] = 'refund exceeds the amount collected';
            }

            if ($mismatches !== [] && !$confirmed) {
                throw new \DomainException(match (true) {
                    !$locked->acceptsMoney() => 'This request no longer takes money (cancelled or expired)',
                    !$this->purposes->isSettleable($locked->purpose) => "Purpose [{$locked->purpose}] is not available any more",
                    $signed > 0 => 'Amount exceeds what is still outstanding on this request',
                    default => 'Refund exceeds the amount collected',
                });
            }

            $note = self::nullable($data['note'] ?? null);
            if ($mismatches !== []) {
                // Confirmed by the gateway: the money moved, so it is kept and explained.
                $reconcile = 'Recorded as confirmed by ' . ($data['gateway'] ?? 'gateway') . ' although it ' . implode('; ', $mismatches);
                $note = $note === null ? $reconcile : $note . ' — ' . $reconcile;
                gp247_report('[payment-request] request #' . $locked->id . ': ' . $type . ' ' . $amount . ' ' . $locked->currency . ' ' . $reconcile);
            }
            $metadata = (array) ($data['metadata'] ?? []);
            if ($confirmed) {
                $metadata['confirmed'] = true;
            }
            if (!empty($data['refund_of'])) {
                $metadata['refund_of'] = (int) $data['refund_of'];
            }

            $movement = self::ignoreDuplicate(
                fn () => PaymentMovement::create([
                    'request_id' => $locked->id,
                    'type' => $type,
                    'amount' => $amount,
                    'currency' => $locked->currency,
                    'gateway' => (string) ($data['gateway'] ?? ManualGateway::KEY),
                    'gateway_ref' => $gatewayRef,
                    'method' => (string) ($data['method'] ?? PaymentMovement::METHOD_GATEWAY),
                    'reference' => self::nullable($data['reference'] ?? null),
                    'paid_at' => !empty($data['paid_at']) ? $data['paid_at'] : now(),
                    'admin_id' => self::nullable($data['admin_id'] ?? null),
                    'note' => $note,
                    'metadata' => $metadata === [] ? null : $metadata,
                ]),
                fn () => $gatewayRef === null ? null : PaymentMovement::where('gateway_ref', $gatewayRef)->first()
            );
            if ($movement === null) {
                throw new \DomainException('Movement could not be recorded');
            }
            if (!$movement->wasRecentlyCreated) {
                return $movement; // lost the race to a concurrent identical write
            }

            $locked->settled_amount = max(0, $newSettled);
            $locked->status = $this->deriveStatus($locked);
            if (empty($locked->gateway) && $movement->gateway !== ManualGateway::KEY) {
                $locked->gateway = $movement->gateway;
            }
            $locked->save();

            // The owner writes its own ledger in the same transaction: a refusal here
            // rolls everything back, so money is never half-recorded. A purpose whose
            // plugin is gone has no owner to tell.
            $resolver = $this->purposes->isSettleable($locked->purpose) ? $this->purposes->resolver($locked->purpose) : null;
            if ($resolver !== null) {
                try {
                    $type === PaymentMovement::TYPE_REFUND
                        ? $resolver->onReversed($locked, $movement)
                        : $resolver->onSettled($locked, $movement);
                } catch (\Throwable $e) {
                    gp247_report('[payment-request] owner of purpose "' . $locked->purpose . '" refused movement for request #' . $locked->id . ': ' . $e->getMessage());
                    throw $e;
                }
            }

            return $movement;
        });
    }

    /**
     * Issue (or re-issue) the payment link of a request to collect money. A new
     * token replaces the old one, so an earlier link stops working at once.
     *
     * @param PaymentRequest $request
     * @return string The raw token (43 chars base64url) — shown to the admin, never stored in clear.
     * @throws \DomainException When the request is not one a payer can still pay.
     */
    public function issuePublicLink(PaymentRequest $request): string
    {
        $this->assertPayable($request);

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $request->forceFill([
            'public_token_hash' => self::tokenHash($token),
            'public_token_enc' => $token,
        ])->save();

        return $token;
    }

    /**
     * @param string $token Raw token from the link.
     * @return PaymentRequest|null
     */
    public function findByPublicToken(string $token): ?PaymentRequest
    {
        if ($token === '') {
            return null;
        }

        return PaymentRequest::where('public_token_hash', self::tokenHash($token))->first();
    }

    /**
     * @param PaymentRequest $request
     * @return string|null The current link token, or null when none was issued (or the key changed).
     */
    public function publicToken(PaymentRequest $request): ?string
    {
        if (empty($request->public_token_hash)) {
            return null;
        }
        $token = (string) ($request->public_token_enc ?? '');

        // A token that no longer matches its hash (undecryptable after a key change)
        // is useless — the admin re-issues the link.
        return ($token !== '' && hash_equals((string) $request->public_token_hash, self::tokenHash($token))) ? $token : null;
    }

    /**
     * @param PaymentRequest $request
     * @return string|null Absolute link, or null when no token exists or the pay page is not routed.
     */
    public function publicUrl(PaymentRequest $request): ?string
    {
        $token = $this->publicToken($request);
        if ($token === null || !Route::has(self::PAY_ROUTE)) {
            return null;
        }

        return route(self::PAY_ROUTE, ['token' => $token]);
    }

    /**
     * Gateways a payer may choose on the pay page: declared `collect`, never `manual`,
     * and — for a given request — only those that can serve it (its store has credentials).
     *
     * @param PaymentRequest|null $request
     * @return array<string, string> key => label.
     */
    public function collectGateways(?PaymentRequest $request = null): array
    {
        $out = $this->gateways->withCapability(PaymentGateway::CAP_COLLECT);
        unset($out[ManualGateway::KEY]);
        if ($request === null) {
            return $out;
        }

        foreach (array_keys($out) as $key) {
            try {
                $usable = (bool) $this->gateways->driver($key)?->availableFor($request);
            } catch (\Throwable $e) {
                gp247_report('[payment-request] gateway "' . $key . '" availability check failed: ' . $e->getMessage());
                $usable = false;
            }
            if (!$usable) {
                unset($out[$key]);
            }
        }

        return $out;
    }

    /**
     * Give back part or all of a collection through the gateway that collected it, and
     * record the refund against it. Money going out: the caller checks the permission.
     *
     * @param PaymentRequest  $request
     * @param PaymentMovement $original The `collect` movement being refunded.
     * @param float|string    $amount
     * @param string|null     $adminId
     * @param string|null     $note
     * @return PaymentMovement The refund movement.
     * @throws \DomainException When this collection cannot be refunded this way or for this amount.
     * @throws \Throwable When the gateway refuses (nothing is recorded).
     *
     * @aidlc-story US-payment-request-gateway-refund
     */
    public function refundViaGateway(PaymentRequest $request, PaymentMovement $original, $amount, ?string $adminId = null, ?string $note = null): PaymentMovement
    {
        if ((int) $original->request_id !== (int) $request->id || $original->type !== PaymentMovement::TYPE_COLLECT) {
            throw new \DomainException('Only a collection of this request can be refunded');
        }
        // WHY not manual: a manual collection was paid outside the system, so there is no
        // gateway to give it back through.
        if ($original->gateway === ManualGateway::KEY
            || !$this->gateways->supports((string) $original->gateway, PaymentGateway::CAP_REFUND)) {
            throw new \DomainException("Gateway [{$original->gateway}] cannot refund this collection");
        }
        $amount = PaymentCurrency::round($amount, $request->currency);
        $refundable = $this->refundableAmount($original);
        if ($amount <= 0 || ($amount > $refundable && !PaymentCurrency::same($amount, $refundable, $request->currency))) {
            throw new \DomainException('Refund must be positive and at most ' . $refundable . ' ' . $request->currency);
        }

        $driver = $this->gateways->driver((string) $original->gateway);
        if ($driver === null) {
            throw new \DomainException("Gateway [{$original->gateway}] is not available");
        }
        $result = $driver->refund($request, $original, (float) $amount);

        return $this->recordMovement($request, [
            'type' => PaymentMovement::TYPE_REFUND,
            'amount' => $amount,
            'gateway' => (string) $original->gateway,
            'gateway_ref' => $result->gatewayRef,
            'method' => PaymentMovement::METHOD_GATEWAY,
            'admin_id' => $adminId,
            'note' => $note,
            'metadata' => $result->completed ? [] : ['pending' => true],
            'refund_of' => $original->id,
            'confirmed' => true,
        ]);
    }

    /**
     * Payments a gateway collected for the subject of a money-out request outside payment
     * requests (e.g. the order's checkout payment) — only through gateways that refund.
     *
     * @param PaymentRequest $request
     * @return array<int, RefundSource>
     *
     * @aidlc-story US-payment-request-refund-source
     */
    public function refundSources(PaymentRequest $request): array
    {
        if ($request->direction !== PaymentRequest::DIRECTION_OUT) {
            return [];
        }
        $resolver = $this->purposes->resolver($request->purpose);
        if (!$resolver instanceof ProvidesRefundSources) {
            return [];
        }
        try {
            $sources = $resolver->refundSources($request);
        } catch (\Throwable $e) {
            gp247_report('[payment-request] refund sources of request #' . $request->id . ' failed: ' . $e->getMessage());

            return [];
        }

        return array_values(array_filter($sources, fn ($source) => $source instanceof RefundSource
            && $source->gateway !== ManualGateway::KEY
            && $source->gatewayRef !== ''
            && $source->refundable > 0
            && $this->gateways->supports($source->gateway, PaymentGateway::CAP_REFUND)));
    }

    /**
     * Pay a money-out request by refunding, through its gateway, a payment collected
     * outside payment requests (a refund source). The gateway's refund id becomes the
     * payout's reference, so the owner records it once even when the gateway's own
     * refund notification follows. Money going out: the caller checks the permission.
     *
     * @param PaymentRequest $request
     * @param string         $gateway
     * @param string         $gatewayRef The source's reference at the gateway.
     * @param float|string   $amount
     * @param string|null    $adminId
     * @return PaymentMovement The payout movement.
     * @throws \DomainException When the source, the amount or the request does not allow it.
     * @throws \Throwable When the gateway refuses (nothing is recorded).
     *
     * @aidlc-story US-payment-request-refund-source
     */
    public function refundFromSource(PaymentRequest $request, string $gateway, string $gatewayRef, $amount, ?string $adminId = null): PaymentMovement
    {
        $source = null;
        foreach ($this->refundSources($request) as $candidate) {
            if ($candidate->gateway === $gateway && $candidate->gatewayRef === $gatewayRef) {
                $source = $candidate;
                break;
            }
        }
        if ($source === null) {
            throw new \DomainException('This payment is not a refund source of this request');
        }
        if (!$request->acceptsMoney() || $request->isSettled()) {
            throw new \DomainException('This request no longer takes money (cancelled, expired or settled)');
        }
        $amount = PaymentCurrency::round($amount, $request->currency);
        $cap = min($request->outstanding(), $source->refundable);
        if ($amount <= 0 || ($amount > $cap && !PaymentCurrency::same($amount, $cap, $request->currency))) {
            throw new \DomainException('Refund must be positive and at most ' . $cap . ' ' . $request->currency);
        }
        $driver = $this->gateways->driver($gateway);
        if ($driver === null) {
            throw new \DomainException("Gateway [{$gateway}] is not available");
        }

        // WHY a transient movement: the drivers refund "a collection" — here it lives in
        // the owner's ledger, not ours, so it is never stored and the core issues the key.
        $original = new PaymentMovement([
            'request_id' => $request->id,
            'type' => PaymentMovement::TYPE_COLLECT,
            'amount' => $source->refundable,
            'currency' => $request->currency,
            'gateway' => $gateway,
            'gateway_ref' => $gatewayRef,
        ]);
        $earlier = PaymentMovement::where('request_id', $request->id)->where('type', PaymentMovement::TYPE_PAYOUT)->count();
        $minor = (int) round((float) $amount * (10 ** PaymentCurrency::precision($request->currency)));
        $key = 'gp247-src-refund-' . $request->id . '-' . substr(sha1($gatewayRef), 0, 12) . '-' . $earlier . '-' . $minor;

        $result = $driver->refund($request, $original, (float) $amount, $key);

        return $this->recordMovement($request, [
            'type' => PaymentMovement::TYPE_PAYOUT,
            'amount' => $amount,
            'gateway' => $gateway,
            'gateway_ref' => $result->gatewayRef,
            'method' => PaymentMovement::METHOD_GATEWAY,
            'admin_id' => $adminId,
            'metadata' => array_filter(['source_ref' => $gatewayRef, 'pending' => $result->completed ? null : true]),
            'confirmed' => true,
        ]);
    }

    /**
     * Let the purpose owner vet request data (optional ValidatesRequest hook).
     *
     * @param string               $purpose
     * @param array<string, mixed> $data
     * @return void
     * @throws \InvalidArgumentException When the owner refuses.
     */
    private function askOwner(string $purpose, array $data): void
    {
        $resolver = $this->purposes->resolver($purpose);
        if ($resolver instanceof ValidatesRequest) {
            $resolver->validateRequest($data);
        }
    }

    /**
     * What is still refundable on a collection: its amount minus the refunds that point
     * back at it (`metadata.refund_of`).
     *
     * @param PaymentMovement $original
     * @return float
     */
    public function refundableAmount(PaymentMovement $original): float
    {
        if ($original->type !== PaymentMovement::TYPE_COLLECT) {
            return 0.0;
        }
        $refunded = $this->refundsOf($original)->sum(fn (PaymentMovement $m) => (float) $m->amount);

        return max(0.0, round((float) $original->amount - $refunded, 3));
    }

    /**
     * Refund movements that give back (part of) a collection. Drivers use the count to
     * build an idempotency key that differs for each legitimate refund.
     *
     * @param PaymentMovement $original
     * @return \Illuminate\Support\Collection<int, PaymentMovement>
     */
    public function refundsOf(PaymentMovement $original): \Illuminate\Support\Collection
    {
        // WHY in PHP: metadata is a JSON text column; a request holds only a few movements.
        return PaymentMovement::where('request_id', $original->request_id)
            ->where('type', PaymentMovement::TYPE_REFUND)
            ->orderBy('id')
            ->get()
            ->filter(fn (PaymentMovement $m) => (int) ($m->metadata['refund_of'] ?? 0) === (int) $original->id)
            ->values();
    }

    /**
     * A collection a gateway reported, by its own reference (to match its refund events).
     *
     * @param string $gateway
     * @param string $gatewayRef
     * @return PaymentMovement|null
     */
    public function findGatewayCollect(string $gateway, string $gatewayRef): ?PaymentMovement
    {
        if ($gatewayRef === '') {
            return null;
        }

        return PaymentMovement::where('gateway', $gateway)
            ->where('gateway_ref', $gatewayRef)
            ->where('type', PaymentMovement::TYPE_COLLECT)
            ->first();
    }

    /**
     * Whether a payer can still pay the request through its link.
     *
     * @param PaymentRequest $request
     * @return bool
     */
    public function isPayable(PaymentRequest $request): bool
    {
        try {
            $this->assertPayable($request);

            return true;
        } catch (\DomainException $e) {
            return false;
        }
    }

    /**
     * Start collecting what is still outstanding through a gateway's hosted page.
     * No money is recorded here — the gateway reports the payment later (webhook).
     *
     * @param PaymentRequest $request
     * @param string         $gatewayKey
     * @return CollectResult
     * @throws \DomainException When the request cannot be paid or the gateway cannot collect.
     */
    public function startCollect(PaymentRequest $request, string $gatewayKey): CollectResult
    {
        $this->assertPayable($request);
        if (!array_key_exists($gatewayKey, $this->collectGateways($request))) {
            throw new \DomainException("Gateway [{$gatewayKey}] cannot collect this request");
        }
        $driver = $this->gateways->driver($gatewayKey);
        if ($driver === null) {
            throw new \DomainException("Gateway [{$gatewayKey}] is not available");
        }

        $result = $driver->collect($request);

        $request->forceFill([
            'gateway' => $gatewayKey,
            'gateway_ref' => $result->gatewayRef,
        ])->save();

        return $result;
    }

    /**
     * @param PaymentRequest $request
     * @return void
     * @throws \DomainException When a payer can no longer pay this request.
     */
    private function assertPayable(PaymentRequest $request): void
    {
        if ($request->direction !== PaymentRequest::DIRECTION_IN) {
            throw new \DomainException('Only a request to collect money has a payment link');
        }
        if (!$request->acceptsMoney() || $request->isSettled() || $request->outstanding() <= 0) {
            throw new \DomainException('This request cannot be paid any more');
        }
        if (!$this->purposes->isSettleable($request->purpose)) {
            throw new \DomainException("Purpose [{$request->purpose}] is not available any more");
        }
    }

    private static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Run a ledger insert, treating a unique-index collision as "already recorded".
     *
     * @template T
     * @param callable():T      $write
     * @param callable():T|null $existing Fetches the row that won the race.
     * @return T|null
     */
    public static function ignoreDuplicate(callable $write, ?callable $existing = null)
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException $e) {
            return $existing !== null ? $existing() : null;
        }
    }

    /**
     * @param PaymentRequest $request
     * @return string
     */
    private function deriveStatus(PaymentRequest $request): string
    {
        if ($request->cancelled_at !== null) {
            return PaymentRequest::STATUS_CANCELLED;
        }
        $settled = (float) $request->settled_amount;
        $amount = (float) $request->amount;
        if ($settled <= 0 && !PaymentCurrency::same($settled, 0.0, $request->currency)) {
            return PaymentRequest::STATUS_OPEN;
        }
        if (PaymentCurrency::same($settled, 0.0, $request->currency)) {
            return PaymentRequest::STATUS_OPEN;
        }
        if ($settled >= $amount || PaymentCurrency::same($settled, $amount, $request->currency)) {
            return PaymentRequest::STATUS_SETTLED;
        }

        return PaymentRequest::STATUS_PARTIAL;
    }

    private function assertDirection(string $direction): void
    {
        if (!in_array($direction, [PaymentRequest::DIRECTION_IN, PaymentRequest::DIRECTION_OUT], true)) {
            throw new \InvalidArgumentException("Unknown direction [{$direction}]");
        }
    }

    private function assertPurpose(string $purpose, string $direction): void
    {
        if (!$this->purposes->has($purpose)) {
            throw new \InvalidArgumentException("Unknown purpose [{$purpose}]");
        }
        if (!$this->purposes->allowsDirection($purpose, $direction)) {
            throw new \InvalidArgumentException("Purpose [{$purpose}] does not allow direction [{$direction}]");
        }
    }

    private function currentStoreId(): string
    {
        if (function_exists('gp247_plugin_store_id')) {
            return (string) gp247_plugin_store_id();
        }

        return (string) (defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : '1');
    }

    /**
     * @param mixed $value
     * @return mixed Null for '' / null, the value otherwise.
     */
    private static function nullable($value)
    {
        return ($value === null || $value === '') ? null : $value;
    }
}
