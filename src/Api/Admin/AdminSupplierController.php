<?php

namespace GP247\Shop\Api\Admin;

use GP247\Front\Controllers\RootFrontController;
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
        $suppliers = (new ShopSupplier)
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
        $supplier = (new ShopSupplier)->where('id', $id)
                ->first();
        if ($supplier) {
            $dataReturn = $supplier;
        } else {
            $dataReturn = [
                'error' => 1,
                'msg' => 'Not found',
                'detail' => 'Supplier not found or no permission!',
            ];

        }
        return response()->json($dataReturn, 200);
    }
}
