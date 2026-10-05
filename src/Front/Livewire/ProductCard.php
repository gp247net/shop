<?php

namespace GP247\Shop\Front\Livewire;

use GP247\Front\Livewire\BaseFrontComponent;
use GP247\Shop\Front\Livewire\Concerns\AddsProductToCart;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Services\CartService;

/**
 * Product card actions (add to cart/wishlist/compare) for storefront listings.
 *
 * Replaces the legacy addToCartAjax() jQuery handler (shop_js.blade.php) that
 * shop_product_single.blade.php's buttons called directly — wiring them into
 * the same CartService-backed logic CartManager uses (US-LW-004), via the
 * shared AddsProductToCart trait (ADR-015).
 *
 * One instance is mounted per product card
 * (@livewire('gp247-shop-front::product-card', ['productId' => $product->id])),
 * so it works both inside plain Blade screens (home, product detail "related
 * products") and nested inside ProductFilter's own Livewire tree.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-004
 * @aidlc-adr ADR-011, ADR-015
 */
class ProductCard extends BaseFrontComponent
{
    use AddsProductToCart;

    /** @var array<int, string> Cart instances a second click takes the product out of. */
    protected const TOGGLE_INSTANCES = ['wishlist', 'compare'];

    /** @var string ShopProduct id (UUID — see GP247\Core\Models\UuidTrait). */
    public string $productId;

    /** @var string|null Last error message to surface in the view */
    public ?string $errorMessage = null;

    /**
     * Mount with the product this card represents.
     *
     * @param string $productId
     * @return void
     */
    public function mount(string $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * Add this card's product to a cart instance.
     *
     * @param string $instance 'default'|'wishlist'|'compare'
     * @return void
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    public function addToCart(string $instance = 'default'): void
    {
        $this->reset('errorMessage');

        try {
            $result = $this->attemptAddToCart($this->productId, $instance);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->dispatch('cart-error', message: $this->errorMessage);
            return;
        }

        if ($result['status'] === 'redirect') {
            $this->dispatch('cart-redirect', url: $result['url']);
            return;
        }

        if ($result['status'] === 'error') {
            $this->errorMessage = $result['message'];
            $this->dispatch('cart-error', message: $this->errorMessage);
            return;
        }

        $this->dispatch('cart-updated', count: $result['count'], instance: $instance, message: $result['message']);
    }

    /**
     * Wishlist/compare button click: add the product, or take it back out when it
     * is already in that list (the button is shown active then).
     *
     * @param string $instance 'wishlist'|'compare'
     * @return void
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    public function toggleList(string $instance): void
    {
        $this->reset('errorMessage');

        // WHY: the shopping cart has its own add path (quantity, attributes) and a
        // second click must never empty it.
        if (!in_array($instance, self::TOGGLE_INSTANCES, true)) {
            $this->errorMessage = gp247_language_render('front.data_notfound');
            $this->dispatch('cart-error', message: $this->errorMessage);
            return;
        }

        $cart = (new CartService())->instance($instance);
        $rowIds = $this->matchingRows($cart)->keys();

        if ($rowIds->isEmpty()) {
            $this->addToCart($instance);
            return;
        }

        foreach ($rowIds as $rowId) {
            $cart->remove($rowId);
        }

        $this->dispatch(
            'cart-updated',
            count: $cart->count(),
            instance: $instance,
            message: gp247_language_render('cart.remove_from_cart_success', ['instance' => $instance])
        );
    }

    /**
     * View key resolved through the active template (ADR-011).
     *
     * @return string
     */
    protected function templateViewKey(): string
    {
        return 'livewire.shop_product-card';
    }

    /**
     * Default package view namespace, used when the active template has no override.
     *
     * @return string
     */
    protected function defaultViewNamespace(): string
    {
        return 'gp247-shop-front';
    }

    /**
     * Data passed to the resolved product-card view.
     *
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'product' => (new ShopProduct())->getDetail($this->productId, null, config('app.storeId')),
            'inWishlist' => $this->isInCartInstance('wishlist'),
            'inCompare' => $this->isInCartInstance('compare'),
        ];
    }

    /**
     * Whether this product is already in the given cart instance, so the view can
     * show the wishlist/compare button in its active state.
     *
     * @param string $instance 'wishlist'|'compare'
     * @return bool
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    protected function isInCartInstance(string $instance): bool
    {
        return $this->matchingRows((new CartService())->instance($instance))->isNotEmpty();
    }

    /**
     * This product's rows in a cart instance, keyed by rowId.
     *
     * @param CartService $cart Cart already switched to the wanted instance.
     * @return \Illuminate\Support\Collection
     */
    private function matchingRows(CartService $cart)
    {
        // WHY search() and not content(): content() runs a product query on every
        // call, and a listing mounts one card per product; search() reads the session only.
        return $cart->search(fn ($item) => (string) data_get($item, 'id') === (string) $this->productId);
    }
}
