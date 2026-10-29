<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Support\Commerce\ApiCartRepository;
use GP247\Shop\Api\V2\Resources\CartResource;
use GP247\Shop\Front\Livewire\Concerns\AddsProductToCart;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Services\CartItem;
use GP247\Shop\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Cart of the shopper through API v2 (api/v2/front/cart): read it, add a product (with
 * its attribute choices), change a line's quantity, remove a line. Runs behind
 * CheckoutSessionBridge, so the cart here is the owner's cart (customer or cart token)
 * and every rule is the website's CartService rule.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-cart-view
 * @aidlc-story US-api-commerce-cart-items
 */
class CartController extends RootFrontController
{
    // WHY: the website's store resolution for a cart line (vendor store on a marketplace).
    use AddsProductToCart;

    /**
     * @return JsonResponse
     */
    public function show()
    {
        return response()->json(['data' => CartResource::make($this->cart())]);
    }

    /**
     * Add a product; the same product with the same choices raises that line's quantity.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function addItem(Request $request)
    {
        $request->validate([
            'product_id' => 'required|string|max:100',
            'qty'        => 'required|' . gp247_qty_rule(),
            'options'    => 'nullable|array',
        ]);
        $productId = (string) $request->input('product_id');
        $qty = $request->input('qty');
        $storeId = $this->resolveCartStoreId($productId);
        $product = (new ShopProduct)->getDetail($productId, null, $storeId);
        if (!$product) {
            return ApiErrorResponder::notFound();
        }
        if ($product->kind == GP247_PRODUCT_GROUP || !$product->allowSale()) {
            return ApiErrorResponder::respond(422, 'product_not_for_sale', gp247_language_render('product.dont_allow_sale', ['sku' => $product->sku]));
        }
        // WHY complete + canonical: every attribute group must be chosen and each surcharge
        // comes from the product's own attribute rows, never from the client.
        $options = gp247_cart_options_complete($productId, (array) $request->input('options', []));
        if ($options === false) {
            throw ValidationException::withMessages(['options' => [gp247_language_render('product.please_select_attribute')]]);
        }

        $cart = $this->cart();
        $rowId = CartItem::fromArray(['id' => $productId, 'name' => $product->name, 'options' => $options, 'storeId' => $storeId])->rowId;
        $existing = $cart->get($rowId);
        if (!$existing && $cart->content()->count() >= ApiCartRepository::maxLines()) {
            return ApiErrorResponder::respond(422, 'cart_line_limit', 'The cart holds at most ' . ApiCartRepository::maxLines() . ' lines');
        }
        if ($error = $this->stockError($product, (float) $qty + (float) ($existing->qty ?? 0), $storeId)) {
            return $error;
        }

        $cart->add(['id' => $productId, 'name' => $product->name, 'qty' => $qty, 'storeId' => $storeId, 'options' => $options]);

        return response()->json(['data' => CartResource::make($cart)], 201);
    }

    /**
     * Set a line's quantity; zero or less removes the line.
     *
     * @param Request $request
     * @param string  $rowId
     * @return JsonResponse
     */
    public function updateItem(Request $request, $rowId)
    {
        $request->validate(['qty' => 'required|numeric']);
        $cart = $this->cart();
        $line = $cart->get((string) $rowId);
        if (!$line) {
            return ApiErrorResponder::notFound();
        }
        $qty = $request->input('qty');
        if ((float) $qty <= 0) {
            $cart->remove($line->rowId);

            return response()->json(['data' => CartResource::make($cart)]);
        }
        $request->validate(['qty' => gp247_qty_rule()]);
        $product = (new ShopProduct)->getDetail($line->id, null, $line->storeId);
        if ($product && ($error = $this->stockError($product, (float) $qty, (string) $line->storeId))) {
            return $error;
        }
        $cart->update($line->rowId, $qty);

        return response()->json(['data' => CartResource::make($cart)]);
    }

    /**
     * @param string $rowId
     * @return JsonResponse
     */
    public function removeItem($rowId)
    {
        $cart = $this->cart();
        if (!$cart->get((string) $rowId)) {
            return ApiErrorResponder::notFound();
        }
        $cart->remove((string) $rowId);

        return response()->json(['data' => CartResource::make($cart)]);
    }

    /**
     * 422 when the shop does not sell beyond stock and $qty is more than the stock.
     *
     * @param ShopProduct $product
     * @param float       $qty     Quantity the line would hold.
     * @param string      $storeId
     * @return JsonResponse|null
     */
    private function stockError(ShopProduct $product, float $qty, string $storeId): ?JsonResponse
    {
        if (gp247_config('product_stock', $storeId) && !gp247_config('product_buy_out_of_stock', $storeId) && $qty > (float) $product->stock) {
            return ApiErrorResponder::respond(422, 'insufficient_stock', 'Not enough stock for ' . $product->sku);
        }

        return null;
    }

    /**
     * @return CartService
     */
    private function cart(): CartService
    {
        return (new CartService())->instance(CartService::DEFAULT_INSTANCE);
    }
}
