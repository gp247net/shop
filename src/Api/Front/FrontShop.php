<?php
namespace GP247\Shop\Api\Front;


use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Core\Api\Support\ApiFieldAllowlist;
use GP247\Core\Api\Support\ApiVisibility;
use GP247\Shop\Api\Support\ShopApiVisibility;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Support\ShopApiFields;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopProduct;

/**
 * Public catalog REST API (v1). Rows follow storefront visibility of the calling
 * domain's store; payloads keep the allowlisted fields only.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-public-field-allowlist
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
class FrontShop extends RootFrontController
{
    /**
     * display list category root (parent = 0)
     * @return [json]
     */
    public function getCategoryList()
    {
        $itemsList = $this->visibleCategories()
            ->with('descriptions')
            ->jsonPaginate();
        return response()->json(ApiFieldAllowlist::page($itemsList, ShopApiFields::CATEGORY), 200);
    }

    /**
     * Category detail: list category child
     * @param  [int] $id
     * @return [json]
     */
    public function getCategoryDetail($id)
    {
        $productIds = ApiVisibility::idsOf((new ShopProduct)->buildQuery());
        $category = $this->visibleCategories()
            ->with('descriptions')
            ->with(['products' => fn ($query) => $query->whereIn((new ShopProduct)->getTable() . '.id', $productIds)])
            ->where((new ShopCategory)->getTable() . '.id', $id)
            ->first();
        if ($category) {
            return response()->json(ApiFieldAllowlist::one($category, ShopApiFields::CATEGORY), 200);
        } else {
            return ApiErrorResponder::notFound();
        }
    }

    /**
     * All products
     * @return [json]
     */
    public function getProductList()
    {
        $products = $this->visibleProducts()
            ->with('descriptions')
            ->with('promotionPrice')
            ->with('attributes')
            ->with('brand')
            ->with('builds')
            ->with('groups')
            ->jsonPaginate();
        return response()->json(ApiFieldAllowlist::page($products, ShopApiFields::PRODUCT), 200);
    }

    /**
     * product detail
     * @param  [int] $id
     * @return [json]
     */
    public function getProductDetail($id)
    {
        $product = $this->visibleProducts()
            ->with('descriptions')
            ->with('images')
            ->with('promotionPrice')
            ->with('attributes')
            ->with('brand')
            ->with('builds')
            ->with('groups')
            ->where((new ShopProduct)->getTable() . '.id', $id)
            ->first();
        if ($product) {
            return response()->json(ApiFieldAllowlist::one($product, ShopApiFields::PRODUCT), 200);
        } else {
            return ApiErrorResponder::notFound();
        }
    }

    public function getBrandList()
    {
        $itemsList = (new ShopBrand)
            ->where('status', 1)
            ->jsonPaginate();
        return response()->json(ApiFieldAllowlist::page($itemsList, ShopApiFields::BRAND), 200);
    }

    public function getBrandDetail($id)
    {
        $brand = (new ShopBrand)
            ->where('status', 1)
            ->where('id', $id)
            ->first();
        if ($brand) {
            return response()->json(ApiFieldAllowlist::one($brand, ShopApiFields::BRAND), 200);
        } else {
            return ApiErrorResponder::notFound();
        }
    }

    /**
     * Products the storefront of this domain shows (shared with v2).
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function visibleProducts()
    {
        return ShopApiVisibility::products();
    }

    /**
     * Active categories of this domain's store, every level (shared with v2).
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function visibleCategories()
    {
        return ShopApiVisibility::categories();
    }
}
