<?php

namespace GP247\Shop\Payment\Support;

/**
 * Result of PaymentGateway::collect(): where the payer goes and how the gateway
 * names the session, so the later webhook / return can be matched to the request.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-gateway-capability
 */
final class CollectResult
{
    /**
     * @param string|null $redirectUrl  Hosted page to send the payer to; null when the gateway needs no redirect.
     * @param string|null $gatewayRef   The gateway's reference for this collection attempt.
     * @param array<string, mixed> $meta Anything the gateway wants kept on the request.
     */
    public function __construct(
        public readonly ?string $redirectUrl,
        public readonly ?string $gatewayRef = null,
        public readonly array $meta = [],
    ) {
    }
}
