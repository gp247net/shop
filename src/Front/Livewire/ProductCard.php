<?php

namespace GP247\Shop\Front\Livewire;

use GP247\Front\Livewire\BaseFrontComponent;
use GP247\Shop\Front\Livewire\Concerns\AddsProductToCart;
use GP247\Shop\Models\ShopProduct;

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
        ];
    }
}
