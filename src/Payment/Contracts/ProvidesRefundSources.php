<?php

namespace GP247\Shop\Payment\Contracts;

use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\Support\RefundSource;

/**
 * Optional hook for the owner of a money-OUT purpose: the payments a gateway collected
 * for the request's subject OUTSIDE payment requests (e.g. an order paid at checkout),
 * so the refund can go back through that same gateway.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-refund-source
 * @aidlc-adr payment-request_generic-money-request
 */
interface ProvidesRefundSources
{
    /**
     * @param PaymentRequest $request
     * @return array<int, RefundSource>
     */
    public function refundSources(PaymentRequest $request): array;
}
