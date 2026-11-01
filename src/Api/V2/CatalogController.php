<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\V2\ApiQuery;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Support\Commerce\ProductPurchaseData;
use GP247\Shop\Api\Support\ShopApiVisibility;
use GP247\Shop\Api\V2\Resources\ProductResource;
use GP247\Shop\Api\V2\Resources\ShopResources;
use GP247\Shop\Api\V2\Resources\ShopShapes;
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
 * @aidlc-story US-CMP-api-sparse-fieldsets, US-CMP-api-include-relations
 * @aidlc-adr compat-foundation_rest-api-hardening, compat-foundation_api-http-caching-and-shaping
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
        $shape = ShopShapes::product(false, false, $lang)->resolve($request);
        $query = ShopApiVisibility::products()->with($shape->with(array_merge(['descriptions', 'promotionPrice', 'categories' => self::visibleCategoryIds()], ProductPurchaseData::RELATIONS)));

        return ApiQuery::page($query, $request, fn ($p) => (new ProductResource($p, $lang, false, false, $shape))->resolve($request),
            ProductQuery::filters(), ProductQuery::sorts(), ProductQuery::search(), false, $shape);
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function productDetail(Request $request, $id)
    {
        $lang = ApiQuery::lang($request);
        $shape = ShopShapes::product(false, true, $lang)->resolve($request);
        $product = ShopApiVisibility::products()->with($shape->with(array_merge(['descriptions', 'images', 'promotionPrice', 'categories' => self::visibleCategoryIds()], ProductPurchaseData::RELATIONS)))
            ->where((new ShopProduct)->getTable() . '.id', $id)->first();

        return ApiQuery::one($product, fn ($p) => (new ProductResource($p, $lang, false, true, $shape))->resolve($request), $shape);
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
        $shape = ShopShapes::category()->resolve($request);

        return ApiQuery::page(ShopApiVisibility::categories()->with('descriptions'), $request,
            fn ($c) => ShopResources::category($c, $lang),
            ['parent' => fn (Builder $q, string $v) => $q->where($table . '.parent', $v),
             'top' => fn (Builder $q, string $v) => $q->where($table . '.top', (int) $v)],
            ['sort' => $table . '.sort', 'created_at' => $table . '.created_at'],
            fn (Builder $q, string $term) => $q->whereHas('descriptions', fn ($d) => $d->where('name', 'like', ApiQuery::like($term))), false, $shape);
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

        return ApiQuery::one($category, fn ($c) => ShopResources::category($c, $lang), ShopShapes::category()->resolve($request));
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function brandList(Request $request)
    {
        $shape = ShopShapes::brand()->resolve($request);

        return ApiQuery::page(ShopBrand::query()->where('status', 1), $request, fn ($b) => ShopResources::brand($b),
            [], ['sort' => 'sort', 'name' => 'name'],
            fn (Builder $q, string $term) => $q->where('name', 'like', ApiQuery::like($term)), false, $shape);
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function brandDetail(Request $request, $id)
    {
        $shape = ShopShapes::brand()->resolve($request);

        return ApiQuery::one(ShopBrand::query()->where('status', 1)->where('id', $id)->first(), fn ($b) => ShopResources::brand($b), $shape);
    }
}
