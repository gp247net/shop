<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\V2\ApiQuery;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Support\ShopApiVisibility;
use GP247\Shop\Api\V2\Resources\ProductResource;
use GP247\Shop\Api\V2\Resources\ShopResources;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Public catalog of REST API v2 (api/v2/front): products, categories, brands of the
 * calling domain's storefront, with declared filters, sorts, search and language.
 * Visibility is shared with v1 (ShopApiVisibility).
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-v2-contract, US-CMP-api-v2-query-parameters
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
class CatalogController extends RootFrontController
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function productList(Request $request)
    {
        $lang = ApiQuery::lang($request);
        $query = ShopApiVisibility::products()->with(['descriptions', 'promotionPrice', 'categories' => self::visibleCategoryIds()]);

        return ApiQuery::page($query, $request, fn ($p) => (new ProductResource($p, $lang))->resolve($request),
            ProductQuery::filters(), ProductQuery::sorts(), ProductQuery::search());
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function productDetail(Request $request, $id)
    {
        $lang = ApiQuery::lang($request);
        $product = ShopApiVisibility::products()->with(['descriptions', 'images', 'promotionPrice', 'categories' => self::visibleCategoryIds()])
            ->where((new ShopProduct)->getTable() . '.id', $id)->first();

        return ApiQuery::one($product, fn ($p) => (new ProductResource($p, $lang, false, true))->resolve($request));
    }

    /**
     * Eager-load constraint: only ids of categories the storefront shows.
     *
     * @return \Closure
     */
    private static function visibleCategoryIds(): \Closure
    {
        $table = (new \GP247\Shop\Models\ShopCategory)->getTable();

        return fn ($q) => $q->select($table . '.id')
            ->whereIn($table . '.id', ShopApiVisibility::categories()->reorder()->select($table . '.id')->toBase());
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function categoryList(Request $request)
    {
        $lang = ApiQuery::lang($request);
        $table = (new \GP247\Shop\Models\ShopCategory)->getTable();

        return ApiQuery::page(ShopApiVisibility::categories()->with('descriptions'), $request,
            fn ($c) => ShopResources::category($c, $lang),
            ['parent' => fn (Builder $q, string $v) => $q->where($table . '.parent', $v),
             'top' => fn (Builder $q, string $v) => $q->where($table . '.top', (int) $v)],
            ['sort' => $table . '.sort', 'created_at' => $table . '.created_at'],
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
        $category = ShopApiVisibility::categories()->with('descriptions')
            ->where((new \GP247\Shop\Models\ShopCategory)->getTable() . '.id', $id)->first();

        return ApiQuery::one($category, fn ($c) => ShopResources::category($c, $lang));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function brandList(Request $request)
    {
        return ApiQuery::page(ShopBrand::query()->where('status', 1), $request, fn ($b) => ShopResources::brand($b),
            [], ['sort' => 'sort', 'name' => 'name'],
            fn (Builder $q, string $term) => $q->where('name', 'like', ApiQuery::like($term)));
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function brandDetail(Request $request, $id)
    {
        return ApiQuery::one(ShopBrand::query()->where('status', 1)->where('id', $id)->first(), fn ($b) => ShopResources::brand($b));
    }
}
