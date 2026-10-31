<?php

namespace GP247\Shop\Api\Middleware;

use Closure;
use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Shop\Api\Support\Commerce\ApiCartRepository;
use GP247\Shop\Api\Support\Commerce\CartOwner;
use GP247\Shop\Api\Support\Commerce\GuestCartToken;
use GP247\Shop\Services\CartService;
use GP247\Shop\Shipping\RateService;
use GP247\Shop\Shipping\ShippingCheckout;
use GP247\Shop\Middleware\CurrencyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs a cart / checkout API request on the website's cart and checkout code by giving it
 * a session that lives only for this request: the cart (and, later, the checkout state)
 * of the cart owner is loaded into a fresh in-memory session, the signed-in customer is
 * put on the `customer` guard for this request only, and what changed is written back to
 * shop_shoppingcart. The website session is never read or written.
 *
 * WHY a session at all: order totals, shipping and payment / total plugins read the
 * checkout straight from session() — giving them the same keys is what keeps the API on
 * the website's money rules without changing any plugin.
 * Invariant: this route group must never get StartSession (it would persist and cookie it).
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-cart-view
 * @aidlc-story US-api-commerce-guest-cart-token
 * @aidlc-adr api-commerce_request-scoped-checkout-session
 */
class CheckoutSessionBridge
{
    public const CART_TOKEN_HEADER = 'X-Cart-Token';
    /** Request attribute holding the CartOwner (null for a guest without a cart yet). */
    public const OWNER_ATTRIBUTE = 'gp247.cart_owner';
    private const DRIVER = 'gp247-api-request';
    private const CART_KEY = 'cart.' . CartService::DEFAULT_INSTANCE;
    public const CHECKOUT_INSTANCE = 'api_checkout';

    /**
     * Checkout state kept between API calls: the web session keys the checkout code and
     * the totals / shipping / promotion plugins read. Lines (`dataCheckout`) are not kept —
     * they are taken from the cart on every call — nor is anything about a placed order.
     */
    public const CHECKOUT_KEYS = [
        'storeCheckout', 'shippingAddress', 'address_process', 'shippingMethod', 'paymentMethod', 'totalMethod', 'dataTotal',
        ShippingCheckout::SESSION_RATE, ShippingCheckout::SESSION_REQUEST, RateService::SESSION_QUOTES, RateService::SESSION_DOWN,
    ];

    /**
     * @param ApiCartRepository $carts
     */
    public function __construct(private ApiCartRepository $carts)
    {
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        $customer = null;
        if ($request->bearerToken() !== null) {
            $customer = Auth::guard('customer-api')->user();
            $scope = (string) config('gp247-config.api.auth.api_scope_user', 'user');
            if (!$customer || (int) $customer->status !== 1 || !$customer->tokenCan($scope)) {
                return ApiErrorResponder::unauthenticated();
            }
        }
        if (!$customer && !gp247_config('shop_allow_guest')) {
            return ApiErrorResponder::respond(401, 'login_required', 'Sign in to use the cart');
        }

        $owner = null;
        $loaded = null;
        if ($customer) {
            $owner = CartOwner::customer($customer);
            $loaded = $this->carts->read($owner, CartService::DEFAULT_INSTANCE);
        } else {
            $cartToken = (string) $request->header(self::CART_TOKEN_HEADER, '');
            if (GuestCartToken::isWellFormed($cartToken)) {
                $owner = CartOwner::guest($cartToken);
                $loaded = $this->carts->read($owner, CartService::DEFAULT_INSTANCE);
                // An unknown or expired token opens nothing: the guest starts a new cart.
                $owner = $loaded === null ? null : $owner;
            }
        }

        $loadedState = $owner ? $this->carts->read($owner, self::CHECKOUT_INSTANCE) : null;
        $request->attributes->set(self::OWNER_ATTRIBUTE, $owner);

        [$session, $restore] = $this->swapIn($request, $customer);
        if ($loaded !== null) {
            $session->put(self::CART_KEY, ApiCartRepository::decode($loaded));
        }
        foreach (self::decodeState($loadedState) as $key => $value) {
            $session->put($key, $value);
        }
        // WHY: API routes skip the web middleware group, so without this the cart and the
        // order would be priced with no currency (an order placed with an empty currency).
        CurrencyMiddleware::apply();
        try {
            $response = $next($request);
            if (!$customer) {
                [$owner, $response] = $this->persistGuestCart($owner, $session, $loaded, $response);
                if ($owner === null) {
                    return $response;
                }
            }

            return $this->persistCheckoutState($owner, $session, $loadedState, $response);
        } finally {
            $restore();
        }
    }

    /**
     * Write a guest's cart back; a guest who just started a cart gets its token.
     *
     * @param CartOwner|null $owner
     * @param Store          $session
     * @param string|null    $loaded  Content that was read.
     * @param Response       $response
     * @return array{0: CartOwner|null, 1: Response} The owner (null when nothing was kept) and the response.
     */
    private function persistGuestCart(?CartOwner $owner, Store $session, ?string $loaded, $response): array
    {
        $lines = collect($session->get(self::CART_KEY, []));
        if ($owner === null) {
            if ($lines->isEmpty()) {
                return [null, $response];
            }
            $cartToken = GuestCartToken::issue();
            $owner = CartOwner::guest($cartToken);
            $this->carts->sweepExpiredGuests();
            $this->carts->write($owner, CartService::DEFAULT_INSTANCE, $lines->toJson(), null);
            $response->headers->set(self::CART_TOKEN_HEADER, $cartToken);

            return [$owner, $response];
        }

        $content = $lines->toJson();
        if ($content !== $loaded && !$this->carts->write($owner, CartService::DEFAULT_INSTANCE, $content, $loaded)) {
            return [null, $this->conflict()];
        }

        return [$owner, $response];
    }

    /**
     * Write the checkout state (address, chosen methods, quotes, promotions, totals shown)
     * back when it changed.
     *
     * @param CartOwner   $owner
     * @param Store       $session
     * @param string|null $loaded  State that was read.
     * @param Response    $response
     * @return Response
     */
    private function persistCheckoutState(CartOwner $owner, Store $session, ?string $loaded, $response)
    {
        $state = [];
        foreach (self::CHECKOUT_KEYS as $key) {
            if ($session->get($key) !== null) {
                $state[$key] = $session->get($key);
            }
        }
        if ($state === [] && $loaded === null) {
            return $response;
        }
        $content = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($content !== $loaded && !$this->carts->write($owner, self::CHECKOUT_INSTANCE, $content, $loaded)) {
            return $this->conflict();
        }

        return $response;
    }

    /**
     * Checkout state keys from stored JSON (only the known keys).
     *
     * @param string|null $content
     * @return array<string, mixed>
     */
    private static function decodeState(?string $content): array
    {
        $state = $content === null ? [] : json_decode($content, true);

        return is_array($state) ? array_intersect_key($state, array_flip(self::CHECKOUT_KEYS)) : [];
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    private function conflict()
    {
        return ApiErrorResponder::respond(409, 'cart_conflict', 'The cart changed in the meantime; read it again and retry');
    }

    /**
     * Give this request a fresh session that is stored nowhere and put $customer on the
     * `customer` guard; returns that session and the closure that puts everything back.
     *
     * @param Request $request
     * @param mixed   $customer
     * @return array{0: Store, 1: Closure}
     */
    private function swapIn(Request $request, $customer): array
    {
        $manager = app('session');
        $previousDriver = $manager->getDefaultDriver();
        $previousStore = app()->resolved('session.store') ? app('session.store') : null;
        $previousRequestSession = $request->hasSession() ? $request->session() : null;

        // WHY a null handler: the store must never be read from or written to any storage.
        $manager->extend(self::DRIVER, fn () => new NullSessionHandler());
        $manager->setDefaultDriver(self::DRIVER);
        $session = $manager->driver();
        $session->start();
        app()->instance('session.store', $session);
        $request->setLaravelSession($session);

        $guard = Auth::guard('customer');
        $previousUser = $guard->hasUser() ? $guard->user() : null;
        if ($customer) {
            $guard->setUser($customer);
        }

        return [$session, function () use ($manager, $previousDriver, $previousStore, $previousRequestSession, $request, $guard, $previousUser) {
            $manager->setDefaultDriver($previousDriver);
            // WHY: drop only this request's cached driver; forgetDrivers() would also drop
            // the application's own session store.
            (function (string $name) {
                unset($this->drivers[$name]);
            })->call($manager, self::DRIVER);
            $previousStore ? app()->instance('session.store', $previousStore) : app()->forgetInstance('session.store');
            if ($previousRequestSession) {
                $request->setLaravelSession($previousRequestSession);
            }
            $previousUser ? $guard->setUser($previousUser) : $guard->forgetUser();
        }];
    }
}
