<?php

namespace GP247\Shop\Api\Admin;

use GP247\Front\Controllers\RootFrontController;
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
        $brands = (new ShopBrand)
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
        $brand = (new ShopBrand)->where('id', $id)
                ->first();
        if ($brand) {
            $dataReturn = $brand;
        } else {
            $dataReturn = [
                'error' => 1,
                'msg' => 'Not found',
                'detail' => 'Category not found or no permission!',
            ];

        }
        return response()->json($dataReturn, 200);
    }
}
