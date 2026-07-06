<?php

namespace GP247\Shop\Front\Livewire\Concerns;

use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Services\CartService;

/**
 * Shared add-to-cart/wishlist/compare logic for storefront Livewire components.
 *
 * Extracted (modification 20260702T210000, ADR-015) so CartManager (cart page)
 * and ProductCard (product listings) apply the exact same validation instead
 * of each re-implementing it — including the MultiVendorPro store-resolution
 * branch that the legacy ShopCartController::addToCartAjax() endpoint had but
 * CartManager was missing.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-004
 * @aidlc-adr ADR-015
 */
trait AddsProductToCart
{
    /**
     * Attempt to add a product to a cart instance, applying the same rules as
     * the legacy ShopCartController::addToCartAjax() endpoint it replaces.
     *
     * @param string $productId ShopProduct id (UUID — see GP247\Core\Models\UuidTrait).
     * @param string $instance 'default'|'wishlist'|'compare'
     * @param int $qty
     * @param string|null $storeId Explicit store context; resolved when null.
     * @return array{status: string, message: ?string, url: ?string, count: ?int}
     *         status is one of: 'added', 'redirect', 'error'.
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    protected function attemptAddToCart(string $productId, string $instance = 'default', int $qty = 1, ?string $storeId = null): array
    {
        $resolvedStoreId = $storeId ?? $this->resolveCartStoreId($productId);

        $product = (new ShopProduct())->getDetail($productId, null, $resolvedStoreId);

        if (!$product) {
            return $this->errorResult(gp247_language_render('front.data_notfound'));
        }

        if ($instance === 'default') {
            // Products with mandatory attribute selection must go through the product page.
            if ($product->attributes->count() || $product->kind == GP247_PRODUCT_GROUP) {
                return ['status' => 'redirect', 'message' => null, 'url' => $product->getUrl(), 'count' => null];
            }

            if (!$product->allowSale()) {
                return $this->errorResult(
                    gp247_language_render('product.dont_allow_sale', ['sku' => $product->sku])
                );
            }
        }

        $cart = (new CartService())->instance($instance);

        // For wishlist/compare, prevent duplicate product entries.
        if ($instance !== 'default') {
            $existingIds = array_keys($cart->content()->groupBy('id')->toArray());
            if (in_array((string) $productId, $existingIds)) {
                return $this->errorResult(
                    gp247_language_render('cart.item_exist_in_cart', ['instance' => $instance])
                );
            }
        }

        $cart->add([
            'id' => $productId,
            'name' => $product->name,
            'qty' => $qty,
            'storeId' => $resolvedStoreId,
        ]);

        return [
            'status' => 'added',
            'message' => gp247_language_render(
                'cart.add_to_cart_success',
                ['instance' => $instance === 'default' ? 'cart' : $instance]
            ),
            'url' => null,
            'count' => $cart->count(),
        ];
    }

    /**
     * Resolve the effective store id for a cart add, honouring MultiVendorPro:
     * a root-store shopper adds to the product's own vendor store rather than
     * the root store itself (ported from the legacy addToCartAjax() endpoint).
     *
     * @param string $productId
     * @return string
     */
    private function resolveCartStoreId(string $productId): string
    {
        $storeId = (string) config('app.storeId');

        if (gp247_config_global('MultiVendorPro') && config('app.storeId') == GP247_STORE_ID_ROOT) {
            $product = (new ShopProduct())->getDetail($productId);
            if ($product) {
                $storeId = (string) $product->stores()->first()->id;
            }
        }

        return $storeId;
    }

    /**
     * Build a standard error result array.
     *
     * @param string $message
     * @return array{status: string, message: ?string, url: ?string, count: ?int}
     */
    private function errorResult(string $message): array
    {
        return ['status' => 'error', 'message' => $message, 'url' => null, 'count' => null];
    }
}
