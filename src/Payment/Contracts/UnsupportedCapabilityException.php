<?php

namespace GP247\Shop\Payment\Contracts;

use LogicException;

/**
 * Thrown when a gateway is asked for a capability it did not declare.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-gateway-capability
 */
class UnsupportedCapabilityException extends LogicException
{
    public static function for(string $gateway, string $capability): self
    {
        return new self("Payment gateway [{$gateway}] does not support [{$capability}]");
    }
}
