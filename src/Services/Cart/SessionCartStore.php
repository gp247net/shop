<?php

namespace GP247\Shop\Services\Cart;

/**
 * Cart storage of the web storefront: the Laravel session, under the same dot-notation
 * keys CartService has always used (`cart.default` …), so existing sessions keep working.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-004
 * @aidlc-adr storefront_checkout-service-session-contract
 */
class SessionCartStore implements CartStore
{
    /**
     * {@inheritDoc}
     */
    public function has(string $key): bool
    {
        return session()->has($key);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key)
    {
        return session()->get($key);
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $key, $content): void
    {
        session([$key => $content]);
    }

    /**
     * {@inheritDoc}
     */
    public function forget(string $key): void
    {
        session()->forget($key);
    }
}
