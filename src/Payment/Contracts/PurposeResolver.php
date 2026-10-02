<?php

namespace GP247\Shop\Payment\Contracts;

use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;

/**
 * The owner of a payment purpose: the package or plugin whose ledger the money
 * belongs to (an order, a cash book, a vendor payout…). Registered under
 * `gp247-config.payment.purposes.<key>.resolver`.
 *
 * onSettled() runs inside the same database transaction as the movement, so a
 * failure rolls the movement back. It MUST be idempotent: the core guarantees one
 * call per movement, but the owner's own write should tolerate a retry (record by
 * the movement's gateway_ref, as ShopOrder::recordPayment() does).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-purpose-registry
 * @aidlc-adr payment-request_gateway-capability-contract
 */
interface PurposeResolver
{
    /**
     * Describe the subject the request points at, for the admin screen and the public page.
     *
     * @param PaymentRequest $request
     * @return array{label?: string, url?: string, suggested_amount?: float}|null Null when there is nothing to show.
     */
    public function describeSubject(PaymentRequest $request): ?array;

    /**
     * Money moved for this request: write it into the owner's own ledger.
     *
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement
     * @return void
     * @throws \Throwable To refuse the movement (it is rolled back).
     */
    public function onSettled(PaymentRequest $request, PaymentMovement $movement): void;

    /**
     * A previously settled movement was reversed.
     *
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement
     * @return void
     */
    public function onReversed(PaymentRequest $request, PaymentMovement $movement): void;
}
