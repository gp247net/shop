<?php

namespace GP247\Shop\Services\Cart;

/**
 * Cart storage that lives only in memory — for working on a cart outside any session
 * (merging a guest cart into a customer cart at sign-in through the API).
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-cart-merge-on-login
 * @aidlc-adr api-commerce_request-scoped-checkout-session
 */
class ArrayCartStore implements CartStore
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $key, $content): void
    {
        $this->data[$key] = $content;
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }
}
