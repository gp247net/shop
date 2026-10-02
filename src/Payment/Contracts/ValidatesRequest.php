<?php

namespace GP247\Shop\Payment\Contracts;

/**
 * Optional hook for a purpose owner: vet a request before it is created or updated
 * (the subject exists, same store, same currency, the amount makes sense).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-owner-validation
 * @aidlc-adr payment-request_generic-money-request
 */
interface ValidatesRequest
{
    /**
     * @param array<string, mixed> $data Normalised request data: direction, purpose, amount, currency,
     *                                   store_id, subject_type, subject_id (and, on update, the request id as `id`).
     * @return void
     * @throws \InvalidArgumentException With a message shown to the admin, to refuse the request.
     */
    public function validateRequest(array $data): void;
}
