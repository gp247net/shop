<?php

namespace GP247\Shop\Payment\Support;

/**
 * A purpose owner's refusal of request data, naming the field it is about so the admin
 * sees the message next to that field (e.g. the linked order id, the currency).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-owner-validation
 */
class RequestRejected extends \InvalidArgumentException
{
    /**
     * @param string $message Translated message for the admin.
     * @param string $field   Request field: subject_id, subject_type, amount, currency, store_id, direction.
     */
    public function __construct(string $message, public readonly string $field = 'amount')
    {
        parent::__construct($message);
    }
}
