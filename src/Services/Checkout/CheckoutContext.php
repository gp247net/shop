<?php

namespace GP247\Shop\Services\Checkout;

use Illuminate\Http\Request;

/**
 * Who is placing an order and from where: the signed-in customer (or none for a guest),
 * the client IP, user agent and device type stored on the order, and whether the
 * submit carried any input at all. The web builds it from the request; the REST API
 * builds it from its own request, so CheckoutService never reads request globals.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-006
 * @aidlc-adr storefront_checkout-service-session-contract
 */
final class CheckoutContext
{
    /**
     * @param mixed       $customer   Signed-in customer model, or null for a guest.
     * @param mixed       $customerId Customer id stored on the order (0 for a guest).
     * @param string|null $ip         Client IP.
     * @param string|null $userAgent  User-Agent header.
     * @param string      $deviceType Device type ('desktop', 'phone', …).
     * @param bool        $hasInput   False when the submit carried no input (the web form always posts _token).
     * @param string      $channel    Where the order comes from: `web`, `api`, `admin` (stored when the site has the column).
     */
    public function __construct(
        public readonly mixed $customer,
        public readonly mixed $customerId,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly string $deviceType = '',
        public readonly bool $hasInput = true,
        public readonly string $channel = 'web',
    ) {
    }

    /**
     * Context of a web request: the customer guard, the request's IP and user agent.
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        $customer = customer()->user();

        return new self(
            customer: $customer,
            customerId: $customer->id ?? 0,
            ip: $request->ip(),
            userAgent: $request->header('User-Agent'),
            deviceType: (string) (new \Jenssegers\Agent\Agent())->deviceType(),
            hasInput: (bool) $request->all(),
            channel: 'web',
        );
    }
}
