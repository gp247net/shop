<?php

namespace GP247\Shop\Services\Checkout;

/**
 * Outcome of CheckoutService::placeOrder(): the order was placed, an order already
 * placed for this checkout was found again (no second order, no second charge), or the
 * submit was refused for a named reason. The web turns each status into its redirect and
 * message; the REST API turns it into an error code.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-006
 * @aidlc-adr storefront_checkout-service-session-contract
 */
final class PlaceOrderResult
{
    public const PLACED              = 'placed';
    public const REPLAYED            = 'replayed';
    public const CART_EMPTY          = 'cart_empty';
    public const LOGIN_REQUIRED      = 'login_required';
    public const TOKEN_MISSING       = 'token_missing';
    public const INPUT_EMPTY         = 'input_empty';
    public const INVALID_QUANTITY    = 'invalid_quantity';
    public const SHIPPING_UNQUOTABLE = 'shipping_unquotable';
    public const PRICE_CHANGED       = 'price_changed';
    public const CREATE_FAILED       = 'create_failed';

    /**
     * @param string $status  One of the constants above.
     * @param mixed  $orderId Order id when placed or replayed, else null.
     * @param string $message Error message of the order writer (CREATE_FAILED only).
     */
    private function __construct(
        public readonly string $status,
        public readonly mixed $orderId = null,
        public readonly string $message = '',
    ) {
    }

    /**
     * A new order was written.
     *
     * @param mixed $orderId
     * @return self
     */
    public static function placed($orderId): self
    {
        return new self(self::PLACED, $orderId);
    }

    /**
     * This checkout's order already exists; show it again.
     *
     * @param mixed $orderId
     * @return self
     */
    public static function replayed($orderId): self
    {
        return new self(self::REPLAYED, $orderId);
    }

    /**
     * The submit was refused.
     *
     * @param string $status  One of the refusal constants.
     * @param string $message Detail for CREATE_FAILED.
     * @return self
     */
    public static function refused(string $status, string $message = ''): self
    {
        return new self($status, null, $message);
    }

    /**
     * True when a new order was written (the payment step comes next).
     *
     * @return bool
     */
    public function isPlaced(): bool
    {
        return $this->status === self::PLACED;
    }
}
