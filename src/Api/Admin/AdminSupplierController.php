<?php

namespace GP247\Shop\Api\Admin;


use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Core\Api\Support\ApiStoreScope;
use Illuminate\Http\Request;
use GP247\Shop\Models\ShopSupplier;

class AdminSupplierController extends RootFrontController
{
    /**
     * Get the supplier list
     *
     * @return [json] user object
     */
    public function getSupplierList(Request $request)
    {
        $suppliers = ApiStoreScope::apply((new ShopSupplier)->newQuery())
                ->jsonPaginate();
        return response()->json($suppliers, 200);
    }

    /**
     * Get supplier detail
     *
     * @return [json] supplier object
     */
    public function getSupplierDetail(Request $request, $id)
    {
        $supplier = ApiStoreScope::apply((new ShopSupplier)->newQuery())->where('id', $id)
                ->first();
        if (!$supplier) {
            return ApiErrorResponder::notFound();
        }
        return response()->json($supplier, 200);
    }
}
