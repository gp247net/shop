<?php

namespace GP247\Shop\Api\Admin;


use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Core\Api\Support\ApiStoreScope;
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
        $categories = ApiStoreScope::apply((new ShopCategory)->newQuery())
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
        $category = ApiStoreScope::apply((new ShopCategory)->newQuery())->where('id', $id)
                ->with('descriptions')
                ->first();
        if (!$category) {
            return ApiErrorResponder::notFound();
        }
        return response()->json($category, 200);
    }
}
