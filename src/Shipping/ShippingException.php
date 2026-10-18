<?php

namespace GP247\Shop\Shipping;

use RuntimeException;

/**
 * A carrier refused or could not complete an operation. The message is meant for an
 * admin (already cleaned of secrets by the provider); the core shows it as-is.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 */
class ShippingException extends RuntimeException
{
}
