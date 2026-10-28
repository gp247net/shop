<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\V2\Resources\ShopResources;
use GP247\Shop\Models\ShopCustomerAddress;
use GP247\Shop\Services\CustomerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Address book of the signed-in customer (api/v2/front/member/address). Only the
 * customer's own addresses are reachable — another customer's id answers 404 — and the
 * fields follow the store's `customer_*` settings through CustomerAccountService.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-customer-address
 * @aidlc-adr compat-foundation_api-token-rotation
 */
class AddressController extends RootFrontController
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $customer = $request->user();
        $addresses = (new ShopCustomerAddress)->where('customer_id', $customer->id)->orderBy('created_at')->get();

        return response()->json(['data' => $addresses->map(
            fn ($a) => ShopResources::address($a, (string) $customer->address_id)
        )->values()]);
    }

    /**
     * Add an address; `default: true` makes it the default one.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $customer = $request->user();
        $service = $this->accounts();
        $service->addressValidator($request->all())->validate();
        $address = $service->addAddress($customer, $request->all());

        return response()->json(['data' => ShopResources::address($address, (string) $customer->fresh()->address_id)], 201);
    }

    /**
     * @param Request $request
     * @param string  $id
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $customer = $request->user();
        $service = $this->accounts();
        $address = $service->findAddress($customer, (string) $id);
        if (!$address) {
            return ApiErrorResponder::notFound();
        }
        $service->addressValidator($request->all())->validate();
        $address = $service->updateAddress($customer, $address, $request->all());

        return response()->json(['data' => ShopResources::address($address, (string) $customer->fresh()->address_id)]);
    }

    /**
     * Delete an address; the default address cannot be deleted (422).
     *
     * @param Request $request
     * @param string  $id
     * @return JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        $customer = $request->user();
        $service = $this->accounts();
        $address = $service->findAddress($customer, (string) $id);
        if (!$address) {
            return ApiErrorResponder::notFound();
        }
        $service->deleteAddress($customer, $address, true);

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return CustomerAccountService
     */
    protected function accounts(): CustomerAccountService
    {
        return app(CustomerAccountService::class);
    }
}
