<?php

namespace GP247\Shop\Api\Admin;


use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Core\Api\Support\ApiStoreScope;
use Illuminate\Http\Request;
use GP247\Shop\Models\ShopCustomer;
use GP247\Shop\Controllers\Auth\AuthTrait;
use Illuminate\Support\Facades\Validator;

class AdminCustomerController extends RootFrontController
{
    use AuthTrait;
    /**
     * Get the customer list
     *
     * @return [json] user object
     */
    public function getCustomerList(Request $request)
    {
        $customers = ApiStoreScope::apply((new ShopCustomer)->newQuery())
                ->jsonPaginate();
        return response()->json($customers, 200);
    }

    /**
     * Get customer detail
     *
     * @return [json] customer object
     */
    public function getCustomerDetail(Request $request, $id)
    {
        $customer = ApiStoreScope::apply((new ShopCustomer)->newQuery())->where('id', $id)
                ->first();
        if (!$customer) {
            return ApiErrorResponder::notFound();
        }
        return response()->json($customer, 200);
    }
}
