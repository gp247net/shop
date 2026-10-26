<?php

namespace GP247\Shop\Api\Admin;


use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Core\Api\Support\ApiStoreScope;
use Illuminate\Http\Request;
use GP247\Shop\Models\ShopOrder;

class AdminOrderController extends RootFrontController
{

    /**
     * Get the order list (admin API `order/list`).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderList(Request $request)
    {
        $orders = ApiStoreScope::apply((new ShopOrder)->newQuery())
                ->with('details')
                ->with('orderTotal')
                ->jsonPaginate();
        return response()->json($orders, 200);
    }

    /**
     * Get order detail (admin API `order/detail/{id}`).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderDetail(Request $request, $id)
    {
        $order = ApiStoreScope::apply((new ShopOrder)->newQuery())->where('id', $id)
                ->with('details')
                ->with('orderTotal')
                ->first();
        if (!$order) {
            return ApiErrorResponder::notFound();
        }
        return response()->json($order, 200);
    }
}
