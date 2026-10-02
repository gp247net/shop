<?php

namespace GP247\Shop\Payment\Gateways;

use GP247\Shop\Payment\Contracts\PaymentGateway;
use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\Support\CollectResult;
use GP247\Shop\Payment\Support\RefundResult;

/**
 * The gateway that is always there: money moved by bank transfer or cash and
 * recorded by an admin. It never redirects anyone and never calls the network —
 * the movement itself is written through PaymentRequestService::recordManual().
 *
 * WHY a gateway and not a special case: for a self-hosted shop, paying a partner by
 * bank transfer is the normal path, not the fallback; giving it the same shape as a
 * real gateway keeps the admin screen and the registries uniform.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-gateway-capability
 * @aidlc-adr payment-request_gateway-capability-contract
 */
class ManualGateway implements PaymentGateway
{
    public const KEY = 'manual';

    public function key(): string
    {
        return self::KEY;
    }

    public function capabilities(): array
    {
        return [self::CAP_COLLECT, self::CAP_REFUND, self::CAP_PAYOUT];
    }

    /**
     * Recording by hand is always possible.
     */
    public function availableFor(PaymentRequest $request): bool
    {
        return true;
    }

    /**
     * Nothing to redirect to: the admin records the receipt by hand.
     */
    public function collect(PaymentRequest $request): CollectResult
    {
        return new CollectResult(null, null);
    }

    /**
     * A manual refund is only a record; the admin gives the money back outside the system.
     */
    public function refund(PaymentRequest $request, PaymentMovement $original, float $amount, ?string $idempotencyKey = null): RefundResult
    {
        return new RefundResult('manual-refund-' . $original->id . '-' . time(), true);
    }
}
