<?php

namespace GP247\Shop\Api\Support\Commerce;

/**
 * Who a cart belongs to: a signed-in customer (stored under the customer id, the same
 * shop_shoppingcart row the website uses) or a guest (stored under the hash of the cart
 * token).
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-cart-view
 * @aidlc-story US-api-commerce-guest-cart-token
 * @aidlc-adr api-commerce_request-scoped-checkout-session
 */
final class CartOwner
{
    /**
     * @param mixed       $customer  Customer model, or null for a guest.
     * @param string|null $tokenHash SHA-256 of the guest cart token, or null for a customer.
     */
    private function __construct(
        private readonly mixed $customer,
        private readonly ?string $tokenHash,
    ) {
    }

    /**
     * Cart of a signed-in customer.
     *
     * @param mixed $customer Customer model (anything with an `id`).
     * @return self
     */
    public static function customer($customer): self
    {
        return new self($customer, null);
    }

    /**
     * Cart of a guest holding $token.
     *
     * @param string $token Raw cart token.
     * @return self
     */
    public static function guest(string $token): self
    {
        return new self(null, GuestCartToken::hash($token));
    }

    /**
     * @return bool
     */
    public function isGuest(): bool
    {
        return $this->customer === null;
    }

    /**
     * @return mixed The customer model, or null for a guest.
     */
    public function customerModel()
    {
        return $this->customer;
    }

    /**
     * Value of shop_shoppingcart.identifier for this owner.
     *
     * @return string
     */
    public function storageKey(): string
    {
        return $this->isGuest() ? 'guest:' . $this->tokenHash : (string) $this->customer->id;
    }

    /**
     * Namespace for the owner's idempotency keys, so two owners using the same key never collide.
     *
     * @return string
     */
    public function idempotencyScope(): string
    {
        return $this->isGuest() ? 'guest:' . $this->tokenHash : 'customer:' . $this->customer->id;
    }
}
