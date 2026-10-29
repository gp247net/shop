<?php

namespace GP247\Shop\Api\Support\Commerce;

/**
 * Cart token of a shopper who is not signed in: 256 random bits in base64url, handed out
 * once and sent back in the `X-Cart-Token` header. Only its SHA-256 is ever stored.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-guest-cart-token
 * @aidlc-adr api-commerce_guest-access-tokens
 */
final class GuestCartToken
{
    /**
     * A new token.
     *
     * @return string 43 base64url characters.
     */
    public static function issue(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Whether a client-sent value has the shape of a token (checked before any lookup).
     *
     * @param string $token
     * @return bool
     */
    public static function isWellFormed(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $token);
    }

    /**
     * Stored form of a token.
     *
     * @param string $token
     * @return string 64 hex characters.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
