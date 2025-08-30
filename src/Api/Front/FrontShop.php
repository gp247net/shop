<?php
namespace GP247\Shop\Api\Front;

use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopSupplier;

class FrontShop extends RootFrontController
{
    /**
     * display list category root (parent = 0)
     * @return [json]
     */
    public function getCategoryList()
    {
        $itemsList = (new ShopCategory)
            ->with('descriptions')
            ->where('status', 1)
            ->jsonPaginate();
        return response()->json($itemsList, 200);
    }

    /**
     * Category detail: list category child
     * @param  [int] $id
     * @return [json]
     */
    public function getCategoryDetail($id)
    {
        $category = (new ShopCategory)
            ->with('descriptions')
            ->with('products')
            ->where('status', 1)
            ->where('id', $id)
            ->first();
        if ($category) {
            return response()->json($category, 200);
        } else {
            return response()->json([], 404);
        }
    }

    /**
     * All products
     * @return [json]
     */
    public function getProductList()
    {
        $products = (new ShopProduct)
            ->with('descriptions')
            ->with('promotionPrice')
            ->with('attributes')
            ->with('brand')
            ->with('builds')
            ->with('groups')
            ->where('status', 1)
            ->jsonPaginate();
        return response()->json($products, 200);
    }

    /**
     * product detail
     * @param  [int] $id
     * @return [json]
     */
    public function getProductDetail($id)
    {
        $product = (new ShopProduct)
            ->with('descriptions')
            ->with('images')
            ->with('promotionPrice')
            ->with('attributes')
            ->with('brand')
            ->with('builds')
            ->with('groups')
            ->where('status', 1)
            ->where('id', $id)
            ->first();
        if ($product) {
            return response()->json($product, 200);
        } else {
            return response()->json('Product not found', 404);
        }
    }

    public function getBrandList()
    {
        $itemsList = (new ShopBrand)
            ->where('status', 1)
            ->jsonPaginate();
        return response()->json($itemsList, 200);
    }

    public function getBrandDetail($id)
    {
        $brand = (new ShopBrand)
            ->where('status', 1)
            ->where('id', $id)
            ->first();
        if ($brand) {
            return response()->json($brand, 200);
        } else {
            return response()->json('Not found', 404);
        }
    }

}
