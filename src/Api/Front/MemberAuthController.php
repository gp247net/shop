<?php

namespace GP247\Shop\Api\Front;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Core\Api\Support\ApiFieldAllowlist;
use GP247\Core\Api\Support\ApiLoginThrottle;
use GP247\Core\Api\Support\ApiTokenLifetime;
use GP247\Core\Api\Support\LoginChallenges;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Support\ShopApiFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use GP247\Shop\Models\ShopCustomer;
use GP247\Shop\Models\ShopOrder;
use Illuminate\Support\Facades\Validator;
use GP247\Shop\Controllers\Auth\AuthTrait;

class MemberAuthController extends RootFrontController
{
    use AuthTrait;

    /**
     * Login user and create token
     *
     * Only active customers (status 1) sign in; failed attempts are limited per
     * (email + IP); the token always expires. A sign-in challenge registered by a
     * plugin cannot be completed on v1, so it is refused with `challenge_required`.
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-api-login-throttle, US-CMP-api-token-lifetime, US-LW-customer-locked-login-block, US-CMP-api-login-challenge-seam
     */
    public function login(Request $request)
    {
        $user = $this->authenticate($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($refused = LoginChallenges::refuseForV1($user, 'customer', $request)) {
            return $refused;
        }
        ApiLoginThrottle::succeeded($request, 'customer', $this->throttleSubject($request));

        $opts = $this->tokenOptions($user, $request);
        ApiTokenLifetime::purgeExpired($user);
        $tokenResult = $user->createToken($opts['name'], $opts['abilities'], ApiTokenLifetime::expiresAt($opts['days']));
        $token = $tokenResult->plainTextToken;
        $accessToken = $tokenResult->accessToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'scopes' => $accessToken->abilities,
            'expires_at' => Carbon::parse(
                $accessToken->expires_at
            )->toDateTimeString()
        ]);
    }

    /**
     * Validate the credentials of an active customer, under the login throttle.
     *
     * @param Request $request
     * @return ShopCustomer|JsonResponse The customer, or the 401/429 response.
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-api-login-throttle, US-LW-customer-locked-login-block
     */
    protected function authenticate(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);

        if ($blocked = ApiLoginThrottle::attempt($request, 'customer', $this->throttleSubject($request))) {
            return $blocked;
        }

        $credentials = request(['email', 'password']);
        // WHY: a locked customer gets the same answer as a wrong password (no account probing).
        $credentials['status'] = 1;

        if (!$this->guard()->attempt($credentials)) {
            return ApiErrorResponder::respond(401, 'unauthenticated', 'Unauthorized');
        }
        return $this->guard()->user();
    }

    /**
     * Login throttle subject of the typed e-mail.
     *
     * @param Request $request
     * @return string
     */
    protected function throttleSubject(Request $request): string
    {
        $email = (string) $request->input('email');
        return ApiLoginThrottle::subject(ShopCustomer::where('email', $email)->value('id'), $email);
    }

    /**
     * Abilities, name and lifetimes of the token issued to $user.
     *
     * @param ShopCustomer $user
     * @param Request      $request
     * @return array{abilities: string[], name: string, days: int, refresh_days: int}
     */
    protected function tokenOptions(ShopCustomer $user, Request $request): array
    {
        return [
            'abilities'    => explode(',', (string) config('gp247-config.api.auth.api_scope_user')),
            'name'         => 'Client:' . $user->email . '- ' . now(),
            'days'         => (int) ($request->remember_me
                ? config('gp247-config.api.auth.api_remember')
                : config('gp247-config.api.auth.api_token_expire_default')),
            'refresh_days' => (int) config('gp247-config.api.auth.api_refresh_days', 30),
        ];
    }

    /**
     * Validate data input
     */
    protected function validator(array $data)
    {
        $dataMapp = $this->mappingValidator($data);
        return Validator::make($data, $dataMapp['validate'], $dataMapp['messages']);
    }


    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-api-token-lifetime
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return ApiTokenLifetime::withLogoutDeprecation($request, response()->json([
            'error' => 0,
            'msg' => 'Successfully logged out'
        ]));
    }

    /**
     * Profile of the signed-in customer (allowlisted fields).
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-api-public-field-allowlist
     */
    public function getInfo(Request $request)
    {
        return response()->json(ApiFieldAllowlist::one($request->user(), ShopApiFields::MEMBER_INFO));
    }

    protected function guard()
    {
        return Auth::guard('customer');
    }

    /**
     * Orders of the signed-in customer (allowlisted fields).
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-api-public-field-allowlist
     */
    public function getOrderList(Request $request)
    {
        $customer = $request->user();
        if ($customer) {
            $orders = (new ShopOrder)
            ->with('details')
            ->with('orderTotal')
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->jsonPaginate();
            return response()->json(ApiFieldAllowlist::page($orders, ShopApiFields::MEMBER_ORDER), 200);
        }
        return ApiErrorResponder::unauthenticated();
    }

    /**
     * One order of the signed-in customer (allowlisted fields).
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-api-public-field-allowlist
     */
    public function getOrderDetail(Request $request, $id)
    {
        $customer = $request->user();
        if ($customer) {
            $order = (new ShopOrder)
            ->with('details')
            ->with('orderTotal')
            ->where('customer_id', $customer->id)
            ->where('id', $id)
            ->first();
            if (!$order) {
                return ApiErrorResponder::notFound();
            }
            return response()->json(ApiFieldAllowlist::one($order, ShopApiFields::MEMBER_ORDER), 200);
        }
        return ApiErrorResponder::unauthenticated();
    }
}
