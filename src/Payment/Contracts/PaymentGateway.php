<?php

namespace GP247\Shop\Payment\Contracts;

use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\Support\CollectResult;
use GP247\Shop\Payment\Support\RefundResult;

/**
 * A way to move money for a payment request. A gateway declares which of the three
 * capabilities it really provides — `collect` (a hosted page for an arbitrary amount),
 * `refund` (give back money it collected itself), `payout` (pay a third party) — and
 * the admin screen only offers those. Registered under
 * `gp247-config.payment.gateways.<key>`.
 *
 * A method for a capability the gateway did not declare may throw
 * UnsupportedCapabilityException. A driver resolves its credentials from the request's
 * own `store_id`, never from the session context (ADR addendum S3).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-gateway-capability
 * @aidlc-adr payment-request_gateway-capability-contract
 */
interface PaymentGateway
{
    public const CAP_COLLECT = 'collect';
    public const CAP_REFUND = 'refund';
    public const CAP_PAYOUT = 'payout';

    /**
     * @return string The registry key (e.g. "manual", "StripePayment").
     */
    public function key(): string;

    /**
     * @return array<int, string> Subset of CAP_* this gateway really supports.
     */
    public function capabilities(): array;

    /**
     * Whether this gateway can serve this particular request right now — e.g. the
     * request's store has credentials for the current mode. "Plugin enabled" is not
     * enough: a gateway answering false is hidden from the pay page.
     *
     * @param PaymentRequest $request
     * @return bool
     */
    public function availableFor(PaymentRequest $request): bool;

    /**
     * Start collecting the outstanding amount of a request (a hosted payment page).
     *
     * @param PaymentRequest $request
     * @return CollectResult Where to send the payer, and the gateway's session reference.
     */
    public function collect(PaymentRequest $request): CollectResult;

    /**
     * Give back part or all of a movement this gateway collected.
     *
     * @param PaymentRequest  $request
     * @param PaymentMovement $original Movement being refunded (its gateway must be this one).
     * @param float           $amount   Amount to give back, in the request's currency.
     * @param string|null     $idempotencyKey Key the core issues when the original is not a stored
     *                                        movement (a refund source: its `id` is null); the
     *                                        driver derives its own key when null.
     * @return RefundResult
     */
    public function refund(PaymentRequest $request, PaymentMovement $original, float $amount, ?string $idempotencyKey = null): RefundResult;
}
