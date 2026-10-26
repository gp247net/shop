<?php

namespace GP247\Shop\Api\Front;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Core\Api\Support\ApiFieldAllowlist;
use GP247\Core\Api\Support\ApiLoginThrottle;
use GP247\Core\Api\Support\ApiTokenLifetime;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Support\ShopApiFields;
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
     * (email + IP); the token always expires.
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-api-login-throttle, US-CMP-api-token-lifetime, US-LW-customer-locked-login-block
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);

        $email = (string) $request->input('email');
        $subject = ApiLoginThrottle::subject(ShopCustomer::where('email', $email)->value('id'), $email);
        if ($blocked = ApiLoginThrottle::attempt($request, 'customer', $subject)) {
            return $blocked;
        }

        $credentials = request(['email', 'password']);
        // WHY: a locked customer gets the same answer as a wrong password (no account probing).
        $credentials['status'] = 1;

        if (!$this->guard()->attempt($credentials)) {
            return ApiErrorResponder::respond(401, 'unauthenticated', 'Unauthorized');
        }
        ApiLoginThrottle::succeeded($request, 'customer', $subject);

        $user = $this->guard()->user();
        $scope = explode(',', config('gp247-config.api.auth.api_scope_user'));

        ApiTokenLifetime::purgeExpired($user);
        $days = $request->remember_me
            ? config('gp247-config.api.auth.api_remember')
            : config('gp247-config.api.auth.api_token_expire_default');
        $tokenResult = $user->createToken('Client:'.$user->email.'- '.now(), $scope, ApiTokenLifetime::expiresAt($days));
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
