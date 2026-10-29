<?php

namespace GP247\Shop\Services\Cart;

/**
 * Where CartService keeps cart contents (one Collection of CartItem per key, keys like
 * `cart.default`, `cart.wishlist`, `cart.compare`). The web uses the session; another
 * channel (REST API) can keep the same contents elsewhere without touching CartService.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-004
 * @aidlc-adr storefront_checkout-service-session-contract
 */
interface CartStore
{
    /**
     * Whether anything is stored under $key.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Stored contents under $key, or null.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key);

    /**
     * Store $content under $key.
     *
     * @param string $key
     * @param mixed  $content
     * @return void
     */
    public function put(string $key, $content): void;

    /**
     * Remove what is stored under $key.
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void;
}
