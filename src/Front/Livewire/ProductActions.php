<?php

namespace GP247\Shop\Front\Livewire;

/**
 * Wishlist/compare buttons for the product detail page.
 *
 * The detail screen adds to the shopping cart through its own form (it carries the
 * quantity and the chosen attributes), so it had no way to reach the wishlist or the
 * compare list. This component is ProductCard minus the card: same mount, same
 * AddsProductToCart rules and events (toast + header badges), its own small view.
 *
 * Mounted as @livewire('gp247-shop-front::product-actions', ['productId' => $product->id]).
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-004
 * @aidlc-adr ADR-011, ADR-015
 */
class ProductActions extends ProductCard
{
    /**
     * Add the product to the wishlist or the compare list.
     *
     * @param string $instance 'wishlist'|'compare'
     * @return void
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    public function addToCart(string $instance = 'wishlist'): void
    {
        // WHY: the shopping cart goes through the page form; letting it through here
        // would open a second path into the cart that skips the attribute choice.
        if (!in_array($instance, self::TOGGLE_INSTANCES, true)) {
            $this->errorMessage = gp247_language_render('front.data_notfound');
            $this->dispatch('cart-error', message: $this->errorMessage);
            return;
        }

        parent::addToCart($instance);
    }

    /**
     * View key resolved through the active template (ADR-011).
     *
     * @return string
     */
    protected function templateViewKey(): string
    {
        return 'livewire.shop_product-actions';
    }
}
