<?php

namespace GP247\Shop\Api\Admin;


use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Core\Api\Support\ApiStoreScope;
use Illuminate\Http\Request;
use GP247\Shop\Models\ShopProduct;

class AdminProductController extends RootFrontController
{
    /**
     * Get the product list
     *
     * @return [json] user object
     */
    public function getProductList(Request $request)
    {
        $products = ApiStoreScope::apply((new ShopProduct)->newQuery())
                ->with('descriptions')
                ->with('images')
                ->with('promotionPrice')
                ->with('attributes')
                ->with('builds')
                ->with('groups')
                ->jsonPaginate();
        return response()->json($products, 200);
    }

    /**
     * Get product detail
     *
     * @return [json] product object
     */
    public function getProductDetail(Request $request, $id)
    {
        $product = ApiStoreScope::apply((new ShopProduct)->newQuery())->where('id', $id)
                ->with('descriptions')
                ->with('images')
                ->with('promotionPrice')
                ->with('attributes')
                ->with('builds')
                ->with('groups')
                ->first();
        if (!$product) {
            return ApiErrorResponder::notFound();
        }
        return response()->json($product, 200);
    }
}
