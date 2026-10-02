<?php

namespace GP247\Shop\Payment\Support;

/**
 * A payment a gateway collected outside payment requests, that a refund can go back
 * through: which gateway, its reference there, and how much of it is still refundable.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-refund-source
 */
final class RefundSource
{
    /**
     * @param string $gateway    Gateway registry key (e.g. "StripePayment").
     * @param string $gatewayRef The gateway's reference of the payment (PaymentIntent, capture id…).
     * @param float  $refundable Amount still refundable, in the request's currency.
     * @param string $label      Short text for the admin (date, amount…).
     */
    public function __construct(
        public readonly string $gateway,
        public readonly string $gatewayRef,
        public readonly float $refundable,
        public readonly string $label = '',
    ) {
    }
}
