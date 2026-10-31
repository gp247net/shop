<?php

namespace GP247\Shop\Api\Support\Commerce;

/**
 * Idempotency-Key of an order placed through the API, turned into the order's
 * `checkout_token` (unique column): the same key from the same cart owner always maps to
 * the same token, so a resend finds the order already placed instead of placing another.
 * Two owners sending the same string never collide.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-place-order
 * @aidlc-adr api-commerce_idempotency-key
 */
final class IdempotencyKey
{
    public const HEADER = 'Idempotency-Key';

    /**
     * 8–255 printable ASCII characters.
     *
     * @param string $key
     * @return bool
     */
    public static function isWellFormed(string $key): bool
    {
        return (bool) preg_match('/^[\x21-\x7E]{8,255}$/', $key);
    }

    /**
     * Checkout token of the order this key places for this owner.
     *
     * @param CartOwner $owner
     * @param string    $key
     * @return string 64 characters: "api_" + 60 hex.
     */
    public static function checkoutToken(CartOwner $owner, string $key): string
    {
        return 'api_' . substr(hash('sha256', $owner->idempotencyScope() . "\n" . $key), 0, 60);
    }
}
