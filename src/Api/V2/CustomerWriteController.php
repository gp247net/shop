<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Core\Api\Support\ApiStoreScope;
use GP247\Core\Api\V2\ApiQuery;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\V2\Resources\ShopResources;
use GP247\Shop\Models\ShopCustomer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin API lock / unlock of a customer — what a CRM owns. Only the status; the account's
 * store only; permission = write on the Customers screen. A lock bites at once: every
 * customer API request and storefront page checks the status.
 *
 * @aidlc-unit api-admin-write
 * @aidlc-story US-api-admin-write-customer-status
 * @aidlc-adr api-admin-write_write-authorization-as-admin-action
 */
class CustomerWriteController extends RootFrontController
{
    /** Fields an admin API client may write on a customer. */
    private const RULES = ['status' => 'required|integer|in:0,1'];

    /**
     * `PATCH customer/{id}` — `{status: 0 | 1}`.
     *
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws ValidationException When the status is missing or another field is sent.
     */
    public function update(Request $request, $id)
    {
        $customer = ApiStoreScope::apply(ShopCustomer::query())->where('id', (string) $id)->first();
        if ($customer === null) {
            return ApiErrorResponder::notFound();
        }
        $rules = self::RULES;
        // WHY: personal data is edited by the customer or on the admin screen, never by a sync
        // client — any other key is an error, not silently dropped.
        foreach (array_diff(array_keys($request->all()), array_keys($rules)) as $unknown) {
            $rules[$unknown] = 'prohibited';
        }
        $customer->update(['status' => (int) $request->validate($rules)['status']]);

        return ApiQuery::one($customer->fresh(), fn ($c) => ShopResources::customer($c, true));
    }
}
