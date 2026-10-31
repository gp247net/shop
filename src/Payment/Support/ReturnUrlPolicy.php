<?php

namespace GP247\Shop\Payment\Support;

/**
 * Addresses the pay page of a payment request may send the payer back to after the
 * gateway (e.g. the app that started the payment). Only an address on the site's list is
 * used: same scheme, host and port, and a path under the listed path (on a segment
 * boundary). The list is empty by default, so nothing leaves the site until the owner
 * allows it. Never a source of payment status.
 *
 * Configured as `gp247-config.payment.return_urls` (env GP247_PAYMENT_RETURN_URLS),
 * comma / space / new-line separated, e.g. "myshop://checkout, https://app.example.com/return".
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-hosted-payment
 * @aidlc-adr api-commerce_hosted-payment-via-payment-request
 */
final class ReturnUrlPolicy
{
    public const CONFIG = 'gp247-config.payment.return_urls';

    private const MAX_LENGTH = 2048;

    /** Schemes that run code or read local files when followed. */
    private const FORBIDDEN_SCHEMES = ['javascript', 'data', 'vbscript', 'file', 'blob'];

    /**
     * Whether the payer may be sent to this address.
     *
     * @param string|null $url
     * @return bool
     */
    public static function accepts(?string $url): bool
    {
        // WHY reject spaces, control characters and backslashes outright: browsers repair
        // them differently from parse_url, which is how look-alike hosts slip through.
        if ($url === null || $url === '' || strlen($url) > self::MAX_LENGTH || preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            return false;
        }
        $candidate = self::parse($url);
        if ($candidate === null || str_contains($candidate['path'], '/..')) {
            return false;
        }

        foreach (self::entries() as $entry) {
            if ($candidate['scheme'] !== $entry['scheme'] || $candidate['host'] !== $entry['host'] || $candidate['port'] !== $entry['port']) {
                continue;
            }
            $prefix = rtrim($entry['path'], '/');
            if ($prefix === '' || $candidate['path'] === $prefix || str_starts_with($candidate['path'], $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|null $url
     * @return string|null The address when allowed, null otherwise.
     */
    public static function allowed(?string $url): ?string
    {
        return self::accepts($url) ? $url : null;
    }

    /**
     * @return array<int, array{scheme: string, host: string, port: int|null, path: string}>
     */
    private static function entries(): array
    {
        $raw = config(self::CONFIG, '');
        $items = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ((array) $items as $item) {
            $entry = is_string($item) ? self::parse(trim($item)) : null;
            if ($entry !== null) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * @param string $url
     * @return array{scheme: string, host: string, port: int|null, path: string}|null
     */
    private static function parse(string $url): ?array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (in_array($scheme, self::FORBIDDEN_SCHEMES, true)) {
            return null;
        }

        return [
            'scheme' => $scheme,
            'host'   => strtolower($parts['host']),
            'port'   => isset($parts['port']) ? (int) $parts['port'] : null,
            'path'   => (string) ($parts['path'] ?? ''),
        ];
    }
}
