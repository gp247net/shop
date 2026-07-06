<?php

namespace GP247\Shop\Front\Livewire;

use GP247\Front\Livewire\BaseFrontComponent;
use GP247\Shop\Front\Livewire\Concerns\AddsProductToCart;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Services\CartService;

/**
 * Livewire component for storefront cart interactions.
 *
 * Handles add/update/remove/clear operations for the default cart, wishlist,
 * and compare instances. Delegates persistence entirely to CartService; this
 * component is the reactive UI orchestration layer only.
 *
 * Emits browser events so the page mini-cart (rendered separately) can react
 * without a full-page reload. addToCart() shares its validation (allowSale,
 * stock, mandatory attributes, MultiVendorPro store resolution) with
 * ProductCard via the AddsProductToCart trait (ADR-015) — the legacy
 * cart.add_ajax endpoint it replaced has been removed.
 *
 * Extends BaseFrontComponent (ADR-011): the view is resolved through the
 * active template first, falling back to this package's default view, so a
 * Template Developer can override the HTML without touching this class.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-004
 * @aidlc-adr ADR-011, ADR-015
 */
class CartManager extends BaseFrontComponent
{
    use AddsProductToCart;

    /** @var string CartService instance name: default | wishlist | compare */
    public string $instance = 'default';

    /** @var string|null Last error message to surface in the view */
    public ?string $errorMessage = null;

    /** @var string|null Last success message */
    public ?string $successMessage = null;

    /**
     * Mount with an optional instance (default = shopping cart).
     *
     * @param string $instance
     * @return void
     */
    public function mount(string $instance = 'default'): void
    {
        $this->instance = $instance;
    }

    /**
     * Add a product to the current cart instance.
     *
     * Validates allowSale() (status, stock, date_available, kind) before
     * delegating to CartService::add(). For default instance, products with
     * mandatory attributes must be selected on the product page first — the
     * component redirects there. For wishlist/compare, a duplicate product
     * dispatches cart-error rather than being added again.
     *
     * @param string $productId ShopProduct id (UUID — see GP247\Core\Models\UuidTrait).
     * @param int|float $qty Decimal only accepted when product_qty_decimal is enabled (ADR-016).
     * @param string|null $storeId  Explicit store context; resolved from config when null.
     * @return void
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     * @aidlc-adr ADR-016
     */
    public function addToCart(string $productId, int|float $qty = 1, ?string $storeId = null): void
    {
        $this->reset(['errorMessage', 'successMessage']);

        try {
            $result = $this->attemptAddToCart($productId, $this->instance, $qty, $storeId);
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

        $this->successMessage = $result['message'];
        $this->dispatch('cart-updated', count: $result['count'], instance: $this->instance, message: $result['message']);
    }

    /**
     * Update the quantity of an existing cart item.
     *
     * Enforces the stock check from config('product_buy_out_of_stock').
     * Setting qty to 0 or below removes the item.
     *
     * @param string $rowId
     * @param int|float $qty Decimal only accepted when product_qty_decimal is enabled (ADR-016).
     * @return void
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     * @aidlc-adr ADR-016
     */
    public function updateQty(string $rowId, int|float $qty): void
    {
        $this->reset(['errorMessage', 'successMessage']);

        $cart = (new CartService())->instance($this->instance);
        $item = $cart->get($rowId);

        if (!$item) {
            $this->errorMessage = gp247_language_render('front.data_notfound');
            $this->dispatch('cart-error', message: $this->errorMessage);
            return;
        }

        // WHY: CartService::update() sets qty directly, bypassing CartItem::setQuantity()'s
        // guard — enforce product_qty_decimal (ADR-016) here instead.
        if ($qty > 0 && !gp247_qty_decimal_enabled() && (float) $qty != floor((float) $qty)) {
            $this->errorMessage = gp247_language_render('cart.qty_must_be_whole_number');
            $this->dispatch('cart-error', message: $this->errorMessage);
            return;
        }

        if ($qty > 0) {
            $product = (new ShopProduct())->getDetail($item->id, null, $item->storeId ?? config('app.storeId'));
            if ($product && $product->stock < $qty && !gp247_config('product_buy_out_of_stock', $item->storeId ?? config('app.storeId'))) {
                $this->errorMessage = gp247_language_render(
                    'cart.item_over_qty',
                    ['sku' => $product->sku, 'qty' => $qty]
                );
                $this->dispatch('cart-error', message: $this->errorMessage);
                return;
            }
        }

        $cart->update($rowId, $qty);
        $this->dispatch('cart-updated', count: $cart->count(), instance: $this->instance);
    }

    /**
     * Remove a single item from the cart.
     *
     * @param string $rowId
     * @return void
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    public function removeItem(string $rowId): void
    {
        $this->reset(['errorMessage', 'successMessage']);

        $cart = (new CartService())->instance($this->instance);
        $item = $cart->get($rowId);

        if (!$item) {
            return;
        }

        $cart->remove($rowId);
        $this->dispatch('cart-updated', count: $cart->count(), instance: $this->instance);
    }

    /**
     * Destroy all items in the current cart instance.
     *
     * @return void
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    public function clearCart(): void
    {
        (new CartService())->instance($this->instance)->destroy();
        $this->dispatch('cart-updated', count: 0, instance: $this->instance);
    }

    /**
     * Return cart items grouped by store — required for multi-vendor (US-LW-004 AC3).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getItemsGroupByStore(): \Illuminate\Support\Collection
    {
        return (new CartService())->instance($this->instance)->getItemsGroupByStore();
    }

    /**
     * Return the current item count for mini-cart display.
     *
     * @return int
     */
    public function getCount(): int
    {
        return (int) (new CartService())->instance($this->instance)->count();
    }

    /**
     * View key resolved through the active template (ADR-011).
     *
     * @return string
     */
    protected function templateViewKey(): string
    {
        return 'livewire.shop_cart-manager';
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
     * Data passed to the resolved cart-manager view.
     *
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $cart    = new CartService();
        $service = $cart->instance($this->instance);

        return [
            'itemsByStore' => $service->getItemsGroupByStore(),
            'count'        => $service->count(),
            'instance'     => $this->instance,
        ];
    }
}
