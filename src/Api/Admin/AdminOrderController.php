<?php

namespace GP247\Shop\Api\Admin;

use GP247\Front\Controllers\RootFrontController;
use Illuminate\Http\Request;
use GP247\Shop\Models\ShopOrder;

class AdminOrderController extends RootFrontController
{

    /**
     * Get the order list
     *
     * @return [json] user object
     */
    public function orders(Request $request)
    {
        $orders = (new ShopOrder)
                ->with('details')
                ->with('orderTotal')
                ->jsonPaginate();
        return response()->json($orders, 200);
    }

    /**
     * Get order detail
     *
     * @return [json] order object
     */
    public function orderDetail(Request $request, $id)
    {
        $order = (new ShopOrder)->where('id', $id)
                ->with('details')
                ->with('orderTotal')
                ->first();
        if ($order) {
            $dataReturn = $order;
        } else {
            $dataReturn = [
                'error' => 1,
                'msg' => 'Not found',
                'detail' => 'Order not found or no permission!',
            ];
        }
        return response()->json($dataReturn, 200);
    }
}
