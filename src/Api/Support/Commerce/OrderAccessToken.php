<?php

namespace GP247\Shop\Api\Support\Commerce;

/**
 * Order access token of a guest order placed through the API: an HMAC of the order id
 * with the application key, handed out once with the order and sent back in the
 * `X-Order-Access` header to read that order's payment status. Nothing is stored; a
 * token opens exactly one order and only its status and amounts.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-payment-status
 * @aidlc-adr api-commerce_guest-access-tokens
 */
final class OrderAccessToken
{
    public const HEADER = 'X-Order-Access';

    /**
     * @param string|int $orderId
     * @return string 43 characters, base64url without padding.
     * @throws \RuntimeException When the application key is not set.
     */
    public static function issue($orderId): string
    {
        $mac = hash_hmac('sha256', 'order-access:' . $orderId, self::key(), true);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }

    /**
     * @param string|int  $orderId
     * @param string|null $token
     * @return bool
     */
    public static function verify($orderId, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        try {
            return hash_equals(self::issue($orderId), $token);
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * @return string Raw application key bytes.
     * @throws \RuntimeException When the application key is not set.
     */
    private static function key(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }
        if ($key === '') {
            throw new \RuntimeException('APP_KEY is not set');
        }

        return $key;
    }
}
