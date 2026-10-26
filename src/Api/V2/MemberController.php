<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\V2\ApiQuery;
use GP247\Shop\Api\Front\MemberAuthController;
use GP247\Shop\Api\V2\Resources\ShopResources;
use GP247\Shop\Models\ShopOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Customer account of REST API v2 (api/v2/front): login/logout/profile and the
 * customer's own orders. Login rules (throttle, locked accounts, token lifetime) are
 * the v1 ones; only the envelope and shapes differ.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-v2-contract
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
class MemberController extends MemberAuthController
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $response = parent::login($request);

        return $response->getStatusCode() === 200 ? response()->json(['data' => $response->getData(true)]) : $response;
    }

    /**
     * Revoke the current token (POST only in v2).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['revoked' => true]]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        return response()->json(['data' => ShopResources::customer($request->user())]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderDetail(Request $request, $id)
    {
        $order = ShopOrder::query()->with(['details', 'orderTotal'])
            ->where('customer_id', $request->user()->id)->where('id', $id)->first();

        return ApiQuery::one($order, fn ($o) => ShopResources::order($o));
    }
}
