<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Core\Api\Support\ApiStoreScope;
use GP247\Core\Api\V2\ApiQuery;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Support\Commerce\ProductPurchaseData;
use GP247\Shop\Api\V2\Resources\ProductResource;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Services\ProductFieldRules;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin API write of a product's selling price, cost and status — the fields an ERP
 * owns. Only those fields, each refused when the product's store turned it off in the
 * product settings (same gating as the product form). The account's store only;
 * permission = write on the Products screen.
 *
 * @aidlc-unit api-admin-write
 * @aidlc-story US-api-admin-write-product-fields
 * @aidlc-adr api-admin-write_write-authorization-as-admin-action
 */
class ProductWriteController extends RootFrontController
{
    /**
     * `PATCH product/{id}` — `{price?, cost?, status?}`, at least one.
     *
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws ValidationException When the body is empty or carries another field.
     */
    public function update(Request $request, $id)
    {
        $product = ApiStoreScope::apply(ShopProduct::query())->where('id', (string) $id)->first();
        if ($product === null) {
            return ApiErrorResponder::notFound();
        }
        $rules = ProductFieldRules::apiRules($product->store_id ?? null);
        // WHY: any key outside the writable set (sku, stock, a field turned off) is an error,
        // not silently dropped — an ERP must learn its mapping is wrong.
        foreach (array_diff(array_keys($request->all()), array_keys($rules)) as $unknown) {
            $rules[$unknown] = 'prohibited';
        }
        $fields = $request->validate($rules);
        if ($fields === []) {
            throw ValidationException::withMessages(['body' => 'Send at least one of: ' . implode(', ', array_keys(ProductFieldRules::apiRules($product->store_id ?? null)))]);
        }
        $product->update($fields);

        $lang = ApiQuery::lang($request);
        $fresh = ShopProduct::with(array_merge(['descriptions', 'images', 'promotionPrice', 'categories:id'], ProductPurchaseData::RELATIONS))
            ->where('id', $product->id)->first();

        return ApiQuery::one($fresh, fn ($p) => (new ProductResource($p, $lang, true, true))->resolve($request));
    }
}
