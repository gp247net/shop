<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Core\Api\Support\ApiLoginThrottle;
use GP247\Core\Api\Support\ApiRefreshTokens;
use GP247\Core\Api\Support\ApiSessions;
use GP247\Core\Api\Support\LoginChallenges;
use GP247\Core\Api\V2\ApiQuery;
use GP247\Shop\Api\Front\MemberAuthController;
use GP247\Shop\Api\Middleware\CheckoutSessionBridge;
use GP247\Shop\Api\Support\Commerce\ApiCartRepository;
use GP247\Shop\Api\V2\Resources\ShopResources;
use GP247\Shop\Models\ShopOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer sign-in and sessions of REST API v2 (api/v2/front): login (optionally with a
 * rotating refresh token), plugin sign-in challenge, refresh, logout of the whole session,
 * "my sessions", and the customer's own orders. Credential rules (throttle, locked
 * accounts, token lifetime) are the v1 ones; only the envelope and sessions differ.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-v2-contract, US-CMP-api-refresh-token-rotation, US-CMP-api-session-management, US-CMP-api-login-challenge-seam
 * @aidlc-adr compat-foundation_api-token-rotation
 */
class MemberController extends MemberAuthController
{
    private const AUDIENCE = 'customer';

    /**
     * Sign in; `with_refresh: true` adds a refresh token and a short-lived access token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate(['with_refresh' => 'boolean', 'device_name' => 'nullable|string|max:100']);
        $user = $this->authenticate($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $opts = $this->sessionOptions($user, $request);
        if ($challenge = LoginChallenges::gate($user, self::AUDIENCE, $request, $opts)) {
            return $challenge;
        }
        ApiLoginThrottle::succeeded($request, self::AUDIENCE, $this->throttleSubject($request));

        return response()->json(['data' => $this->withGuestCartMerged(ApiSessions::open($user, self::AUDIENCE, $request, $opts), $user, $request)]);
    }

    /**
     * Token options of a v2 sign-in of $user (also used right after register).
     *
     * @param \GP247\Shop\Models\ShopCustomer $user
     * @param Request                         $request
     * @return array
     */
    public function sessionOptions($user, Request $request): array
    {
        return $this->tokenOptions($user, $request) + [
            'with_refresh' => $request->boolean('with_refresh'),
            'device_name'  => $request->input('device_name'),
        ];
    }

    /**
     * Finish a sign-in that required a plugin challenge.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function challenge(Request $request)
    {
        $result = LoginChallenges::complete($request, self::AUDIENCE);
        if ($result instanceof JsonResponse) {
            return $result;
        }
        [$user, $opts] = $result;

        return response()->json(['data' => $this->withGuestCartMerged(ApiSessions::open($user, self::AUDIENCE, $request, $opts), $user, $request)]);
    }

    /**
     * Merge the guest cart the app sent (`X-Cart-Token`) into the account cart of a customer
     * who just signed in or registered, and report it as `cart_merge` (US-api-commerce-cart-merge-on-login).
     *
     * @param array                           $data     Sign-in answer.
     * @param \GP247\Shop\Models\ShopCustomer $customer
     * @param Request                         $request
     * @return array
     *
     * @aidlc-unit api-commerce
     * @aidlc-story US-api-commerce-cart-merge-on-login
     */
    protected function withGuestCartMerged(array $data, $customer, Request $request): array
    {
        $cartToken = (string) $request->header(CheckoutSessionBridge::CART_TOKEN_HEADER, '');
        if ($cartToken !== '' && ($merge = (new ApiCartRepository())->mergeGuestInto($customer, $cartToken)) !== null) {
            $data['cart_merge'] = $merge;
        }

        return $data;
    }

    /**
     * Exchange a refresh token for a new access + refresh pair.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function refresh(Request $request)
    {
        $result = ApiRefreshTokens::rotate($request, self::AUDIENCE);

        return $result instanceof JsonResponse ? $result : response()->json(['data' => $result]);
    }

    /**
     * Revoke the current session (access and refresh token); POST only in v2.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        $session = ApiSessions::forToken($token);
        if ($session) {
            ApiSessions::revoke($session, 'logout');
        } else {
            $token->delete();
        }

        return response()->json(['data' => ['revoked' => true]]);
    }

    /**
     * Live API sessions of the signed-in customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sessions(Request $request)
    {
        $user = $request->user();

        return response()->json(['data' => ApiSessions::listFor($user, (int) $user->currentAccessToken()->id)]);
    }

    /**
     * Revoke one of the signed-in customer's sessions.
     *
     * @param Request $request
     * @param string  $id
     * @return JsonResponse
     */
    public function revokeSession(Request $request, $id)
    {
        $session = ApiSessions::findFor($request->user(), (string) $id);
        if (!$session) {
            return ApiErrorResponder::notFound();
        }
        ApiSessions::revoke($session, 'user');

        return response()->json(['data' => ['revoked' => true]]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request)
    {
        return response()->json(['data' => ShopResources::customer($request->user())]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function orderList(Request $request)
    {
        $query = ShopOrder::query()->with(['details', 'orderTotal'])->where('customer_id', $request->user()->id);

        return ApiQuery::page($query, $request, fn ($o) => ShopResources::order($o),
            ['status' => fn (Builder $q, string $v) => $q->where('status', (int) $v)],
            ['created_at' => 'created_at', 'total' => 'total']);
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return JsonResponse
     */
    public function orderDetail(Request $request, $id)
    {
        $order = ShopOrder::query()->with(['details', 'orderTotal'])
            ->where('customer_id', $request->user()->id)->where('id', $id)->first();

        return ApiQuery::one($order, fn ($o) => ShopResources::order($o));
    }
}
