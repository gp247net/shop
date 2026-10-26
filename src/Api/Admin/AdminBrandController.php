<?php

namespace GP247\Shop\Api\Admin;


use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Core\Api\Support\ApiStoreScope;
use Illuminate\Http\Request;
use GP247\Shop\Models\ShopBrand;

class AdminBrandController extends RootFrontController
{
    /**
     * Get the brand list
     *
     * @return [json] user object
     */
    public function getBrandList(Request $request)
    {
        $brands = ApiStoreScope::apply((new ShopBrand)->newQuery())
                ->jsonPaginate();
        return response()->json($brands, 200);
    }

    /**
     * Get brand detail
     *
     * @return [json] brand object
     */
    public function getBrandDetail(Request $request, $id)
    {
        $brand = ApiStoreScope::apply((new ShopBrand)->newQuery())->where('id', $id)
                ->first();
        if (!$brand) {
            return ApiErrorResponder::notFound();
        }
        return response()->json($brand, 200);
    }
}
