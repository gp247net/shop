<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiStoreScope;
use GP247\Core\Api\V2\ApiQuery;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Support\Commerce\ProductPurchaseData;
use GP247\Shop\Api\V2\Resources\ProductResource;
use GP247\Shop\Api\V2\Resources\ShopResources;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopCustomer;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Admin data of REST API v2 (api/v2/core). Every route is behind `api.admin:<screen>`
 * (RBAC + tenant fence + store resolution, shared with v1); every query is narrowed
 * by ApiStoreScope.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-v2-contract, US-CMP-api-v2-query-parameters
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
class AdminController extends RootFrontController
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function productList(Request $request)
    {
        $lang = ApiQuery::lang($request);
        $query = ApiStoreScope::apply(ShopProduct::query())->with(array_merge(['descriptions', 'promotionPrice', 'categories:id'], ProductPurchaseData::RELATIONS));

        return ApiQuery::page($query, $request, fn ($p) => (new ProductResource($p, $lang, true))->resolve($request),
            ProductQuery::filters(true), ProductQuery::sorts(), ProductQuery::search());
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function productDetail(Request $request, $id)
    {
        $lang = ApiQuery::lang($request);
        $product = ApiStoreScope::apply(ShopProduct::query())->with(array_merge(['descriptions', 'images', 'promotionPrice', 'categories:id'], ProductPurchaseData::RELATIONS))->where('id', $id)->first();

        return ApiQuery::one($product, fn ($p) => (new ProductResource($p, $lang, true, true))->resolve($request));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function categoryList(Request $request)
    {
        $lang = ApiQuery::lang($request);

        return ApiQuery::page(ApiStoreScope::apply(ShopCategory::query())->with('descriptions'), $request,
            fn ($c) => ShopResources::category($c, $lang),
            ['parent' => fn (Builder $q, string $v) => $q->where('parent', $v), 'status' => fn (Builder $q, string $v) => $q->where('status', (int) $v)],
            ['sort' => 'sort', 'created_at' => 'created_at'],
            fn (Builder $q, string $term) => $q->whereHas('descriptions', fn ($d) => $d->where('name', 'like', ApiQuery::like($term))));
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function categoryDetail(Request $request, $id)
    {
        $lang = ApiQuery::lang($request);

        return ApiQuery::one(ApiStoreScope::apply(ShopCategory::query())->with('descriptions')->where('id', $id)->first(),
            fn ($c) => ShopResources::category($c, $lang));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function brandList(Request $request)
    {
        return ApiQuery::page(ApiStoreScope::apply(ShopBrand::query()), $request, fn ($b) => ShopResources::brand($b),
            ['status' => fn (Builder $q, string $v) => $q->where('status', (int) $v)], ['sort' => 'sort', 'name' => 'name'],
            fn (Builder $q, string $term) => $q->where('name', 'like', ApiQuery::like($term)));
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function brandDetail(Request $request, $id)
    {
        return ApiQuery::one(ApiStoreScope::apply(ShopBrand::query())->where('id', $id)->first(), fn ($b) => ShopResources::brand($b));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function supplierList(Request $request)
    {
        return ApiQuery::page(ApiStoreScope::apply(ShopSupplier::query()), $request, fn ($s) => ShopResources::supplier($s),
            ['status' => fn (Builder $q, string $v) => $q->where('status', (int) $v)], ['sort' => 'sort', 'name' => 'name'],
            fn (Builder $q, string $term) => $q->where(fn ($w) => $w->where('name', 'like', ApiQuery::like($term))->orWhere('email', 'like', ApiQuery::like($term))));
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function supplierDetail(Request $request, $id)
    {
        return ApiQuery::one(ApiStoreScope::apply(ShopSupplier::query())->where('id', $id)->first(), fn ($s) => ShopResources::supplier($s));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerList(Request $request)
    {
        return ApiQuery::page(ApiStoreScope::apply(ShopCustomer::query()), $request, fn ($c) => ShopResources::customer($c, true),
            ['status' => fn (Builder $q, string $v) => $q->where('status', (int) $v), 'group' => fn (Builder $q, string $v) => $q->where('group', $v)],
            ['created_at' => 'created_at', 'email' => 'email'],
            fn (Builder $q, string $term) => $q->where(fn ($w) => $w->where('email', 'like', ApiQuery::like($term))
                ->orWhere('phone', 'like', ApiQuery::like($term))
                ->orWhere('first_name', 'like', ApiQuery::like($term))
                ->orWhere('last_name', 'like', ApiQuery::like($term))));
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerDetail(Request $request, $id)
    {
        return ApiQuery::one(ApiStoreScope::apply(ShopCustomer::query())->where('id', $id)->first(), fn ($c) => ShopResources::customer($c, true));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderList(Request $request)
    {
        $query = ApiStoreScope::apply(ShopOrder::query())->with(['details', 'orderTotal']);

        return ApiQuery::page($query, $request, fn ($o) => ShopResources::order($o, true),
            ['status' => fn (Builder $q, string $v) => $q->where('status', (int) $v),
             'payment_status' => fn (Builder $q, string $v) => $q->where('payment_status', (int) $v),
             'shipping_status' => fn (Builder $q, string $v) => $q->where('shipping_status', (int) $v),
             'customer_id' => fn (Builder $q, string $v) => $q->where('customer_id', $v)],
            ['created_at' => 'created_at', 'total' => 'total'],
            fn (Builder $q, string $term) => $q->where(fn ($w) => $w->where('id', $term)
                ->orWhere('email', 'like', ApiQuery::like($term))
                ->orWhere('phone', 'like', ApiQuery::like($term))));
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderDetail(Request $request, $id)
    {
        return ApiQuery::one(ApiStoreScope::apply(ShopOrder::query())->with(['details', 'orderTotal'])->where('id', $id)->first(),
            fn ($o) => ShopResources::order($o, true));
    }
}
