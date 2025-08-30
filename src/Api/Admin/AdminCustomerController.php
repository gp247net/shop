<?php

namespace GP247\Shop\Api\Admin;

use GP247\Front\Controllers\RootFrontController;
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
        $customers = (new ShopCustomer)
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
        $customer = (new ShopCustomer)->where('id', $id)
                ->first();
        if ($customer) {
            $dataReturn = $customer;
        } else {
            $dataReturn = [
                'error' => 1,
                'msg' => 'Not found',
                'detail' => 'Customer not found or no permission!',
            ];

        }
        return response()->json($dataReturn, 200);
    }
}
