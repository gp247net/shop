<?php

namespace GP247\Shop\Payment\Contracts;

/**
 * Optional part of a payment gateway: the order status the gateway gives a checkout order
 * once its money is in (`paid`) or fully returned (`refunded`) — the same statuses its
 * website payment step sets. A separate interface so gateways written against
 * PaymentGateway alone keep working; a gateway without it leaves the status as it is.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-hosted-payment
 * @aidlc-adr payment-request_gateway-capability-contract
 */
interface OrderStatusAfterPayment
{
    public const EVENT_PAID = 'paid';
    public const EVENT_REFUNDED = 'refunded';

    /**
     * @param string $event   EVENT_PAID | EVENT_REFUNDED.
     * @param string $storeId Store of the payment request (the order's store) — whose settings apply.
     * @return int|null Order status id, or null to leave the status as it is.
     */
    public function orderStatusAfter(string $event, string $storeId): ?int;
}
