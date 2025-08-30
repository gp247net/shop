<?php

namespace GP247\Shop\Api\Admin;

use GP247\Front\Controllers\RootFrontController;
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
        $products = (new ShopProduct)
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
        $product = (new ShopProduct)->where('id', $id)
                ->with('descriptions')
                ->with('images')
                ->with('promotionPrice')
                ->with('attributes')
                ->with('builds')
                ->with('groups')
                ->first();
        if ($product) {
            $dataReturn = $product;
        } else {
            $dataReturn = [
                'error' => 1,
                'msg' => 'Not found',
                'detail' => 'Product not found or no permission!',
            ];

        }
        return response()->json($dataReturn, 200);
    }
}
