<?php

namespace GP247\Shop\Api\Admin;

use GP247\Front\Controllers\RootFrontController;
use Illuminate\Http\Request;
use GP247\Shop\Models\ShopCategory;

class AdminCategoryController extends RootFrontController
{
    /**
     * Get the category list
     *
     * @return [json] user object
     */
    public function getCategoryList(Request $request)
    {
        $categories = (new ShopCategory)
                ->with('descriptions')
                ->jsonPaginate();
        return response()->json($categories, 200);
    }

    /**
     * Get category detail
     *
     * @return [json] category object
     */
    public function getCategoryDetail(Request $request, $id)
    {
        $category = (new ShopCategory)->where('id', $id)
                ->with('descriptions')
                ->first();
        if ($category) {
            $dataReturn = $category;
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
