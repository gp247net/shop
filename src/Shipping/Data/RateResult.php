<?php

namespace GP247\Shop\Shipping\Data;

/**
 * What a provider answers to a quote: zero or more rates, plus reasons when it could
 * not quote (address incomplete, not configured, carrier refused…). Errors are data,
 * never exceptions, so one carrier cannot break the checkout
 * (NFR-AVAIL-shipping-rate-fault-isolation).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-checkout-rates
 */
final readonly class RateResult
{
    /**
     * @param array<int, ShippingRate>                       $rates
     * @param array<int, array{code: string, message: string}> $errors
     */
    public function __construct(
        public array $rates = [],
        public array $errors = [],
    ) {
    }

    /**
     * @param string $code    Machine code (e.g. "address_incomplete").
     * @param string $message Safe, human message.
     * @return self
     */
    public static function error(string $code, string $message = ''): self
    {
        return new self([], [['code' => $code, 'message' => $message]]);
    }
}
