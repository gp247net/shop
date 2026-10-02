<?php

namespace GP247\Shop\Payment\Support;

/**
 * Result of PaymentGateway::refund(): the gateway's refund reference (the movement's
 * idempotency key) and whether the money has already left.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-gateway-capability
 */
final class RefundResult
{
    /**
     * @param string $gatewayRef Refund reference at the gateway (e.g. "re_…").
     * @param bool   $completed  True when the refund is final; false while the gateway is still processing it.
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $gatewayRef,
        public readonly bool $completed = true,
        public readonly array $meta = [],
    ) {
    }
}
