<?php

namespace GP247\Shop\Admin\Livewire\Concerns;

use GP247\Shop\Admin\Models\AdminOrder;
use GP247\Shop\Models\ShopOrderDetail;
use GP247\Shop\Models\ShopProduct;

/**
 * Order line-item editing for the shop-admin OrderManager (group E, US-SADM-003).
 *
 * Encapsulates the add / edit / delete of order details — each operation reuses
 * the legacy backend verbatim (ShopOrderDetail::addNewDetail / updateDetail,
 * AdminOrder::updateSubTotal for the subtotal/tax/total/balance + payment-status
 * recalculation, ShopProduct::updateStock for inventory, and an order-history
 * audit row), so behaviour matches AdminOrderController::postAddItem /
 * postEditItem / postDeleteItem. Kept in a trait to keep OrderManager focused
 * (rule coding-style: small, cohesive files).
 *
 * The host component supplies: $editingId (current order id), storeId(),
 * currentOrder(), logHistory(), refreshOrder() and authorizeAction().
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-003
 * @aidlc-adr ADR-001, ADR-006, ADR-007
 */
trait HasOrderItems
{
    /** @var array<int, array<string, mixed>> The order's line items (view state). */
    public array $items = [];

    /** @var array<string, mixed> Add/edit line-item form state. */
    public array $itemForm = [];

    /** @var string|null Id of the line item being edited (null = adding). */
    public ?string $editingItemId = null;

    /**
     * @var bool Whether the add/edit line-item form is revealed. Hidden by
     * default so the panel shows a single "add" button (parity with the
     * create-order screen), only expanding to the form on demand.
     */
    public bool $showItemForm = false;

    /** @var string Product picker search term (sku / alias). */
    public string $productSearch = '';

    /**
     * Empty line-item form state.
     *
     * @return array<string, mixed>
     */
    private function itemDefaults(): array
    {
        return ['product_id' => '', 'name' => '', 'sku' => '', 'qty' => 1, 'price' => 0, 'tax' => 0];
    }

    /**
     * Reset the line-item form back to "add" mode.
     *
     * @return void
     */
    private function resetItemForm(): void
    {
        $this->editingItemId = null;
        $this->itemForm = $this->itemDefaults();
        $this->productSearch = '';
        $this->showItemForm = false;
    }

    /**
     * Reload the current order's line items into view state.
     *
     * @return void
     */
    private function refreshItems(): void
    {
        if ($this->editingId === null) {
            $this->items = [];

            return;
        }

        $this->items = ShopOrderDetail::where('order_id', $this->editingId)
            ->orderBy('id')
            ->get()
            ->map(static function ($row): array {
                return [
                    'id' => (string) $row->id,
                    'product_id' => (string) $row->product_id,
                    'name' => (string) $row->name,
                    'sku' => (string) $row->sku,
                    'qty' => (float) $row->qty,
                    'price' => (float) $row->price,
                    'total_price' => (float) $row->total_price,
                    'tax' => (float) $row->tax,
                ];
            })
            ->all();
    }

    /**
     * Product picker results for the add form (sku / alias match, capped).
     *
     * @return iterable<mixed>
     */
    public function productResults(): iterable
    {
        // WHY: delegate to the shared picker query so the create-order (Alpine) and
        // edit-order (Livewire) screens search identically (sku / alias / name,
        // current locale, GROUP excluded, capped) — single source of truth.
        return ShopProduct::searchForAdminOrderPicker($this->productSearch);
    }

    /**
     * Reveal the line-item form in "add" mode (from the panel's add button).
     *
     * @return void
     */
    public function showAddItem(): void
    {
        $this->resetItemForm();
        $this->showItemForm = true;
    }

    /**
     * Close the line-item form, discarding any in-progress add/edit and
     * collapsing back to the panel's add button.
     *
     * @return void
     */
    public function newItem(): void
    {
        $this->resetItemForm();
    }

    /**
     * Fill the item form from a picked product (price/sku/name prefill).
     *
     * @param int|string $id Product id.
     * @return void
     */
    public function selectProduct($id): void
    {
        // WHY: mirror productResults() which excludes GROUP containers (kind=2,
        // non-sellable) — never prefill the form from one (US-SADM-order-line-integrity).
        $product = ShopProduct::where('id', $id)
            ->where('kind', '!=', GP247_PRODUCT_GROUP)
            ->first();
        if ($product === null) {
            return;
        }

        $this->itemForm['product_id'] = (string) $product->id;
        $this->itemForm['sku'] = (string) $product->sku;
        $this->itemForm['name'] = (string) ($product->getName() ?: $product->sku);
        $this->itemForm['price'] = (float) $product->price;
        $this->productSearch = '';
    }

    /**
     * Load an existing line item into the form for editing.
     *
     * @param int|string $id Order-detail id.
     * @return void
     */
    public function editItem($id): void
    {
        $detail = ShopOrderDetail::where('id', $id)->where('order_id', $this->editingId)->first();
        if ($detail === null) {
            return;
        }

        $this->editingItemId = (string) $detail->id;
        $this->itemForm = [
            'product_id' => (string) $detail->product_id,
            'name' => (string) $detail->name,
            'sku' => (string) $detail->sku,
            'qty' => (float) $detail->qty,
            'price' => (float) $detail->price,
            'tax' => (float) $detail->tax,
        ];
        $this->showItemForm = true;
    }

    /**
     * Validate and persist the line-item form (add or edit), then recalculate
     * the order totals and log the change.
     *
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     * @throws \Illuminate\Validation\ValidationException When qty/price invalid.
     */
    public function saveItem(): void
    {
        $this->authorizeAction('update');
        if ($this->editingId === null) {
            return;
        }

        // WHY: qty format (integer|numeric) is config-driven — product_qty_decimal
        // (modification 20260705T093328, ADR-016); gt:0 stays on top of it.
        $rules = [
            'itemForm.qty' => 'required|' . gp247_qty_rule() . '|gt:0',
            'itemForm.price' => 'nullable|numeric|min:0',
        ];

        // WHY: a new line must be tied to a real product — an empty product_id
        // used to slip through and create a "ghost" line with blank sku/name/0
        // price (US-SADM-order-line-integrity, regression of the legacy
        // postAddItem guard). The name is derived from the product in addNewItem
        // (getName() ?: sku), so it is not a required manual input; the model-level
        // guard is the final backstop against an empty name. On edit the product
        // is fixed and the name is not rewritten, so no product_id/name rule there.
        if ($this->editingItemId === null) {
            $rules['itemForm.product_id'] = 'required|exists:' . (new ShopProduct)->getTable() . ',id';
        }

        $this->validate($rules);

        $clean = gp247_clean($this->itemForm);
        $qty = (float) $clean['qty'];
        $price = (float) ($clean['price'] ?? 0);
        $tax = (float) ($clean['tax'] ?? 0);

        // WHY: the add/update helpers return false when the line is rejected
        // (over-stock hard block or invalid product) — abort the post-steps so we
        // neither recalc totals nor flash a misleading success. The helper already
        // showed the error toast.
        $persisted = $this->editingItemId !== null
            ? $this->updateExistingItem($clean, $qty, $price, $tax)
            : $this->addNewItem($clean, $qty, $price, $tax);

        if (! $persisted) {
            return;
        }

        AdminOrder::updateSubTotal($this->editingId);
        $this->resetItemForm();
        $this->refreshOrder();
        $this->notify('success', gp247_language_render('action.update_success'));
    }

    /**
     * Update an existing line item (reusing legacy stock/total recalculation).
     *
     * @param array<string, mixed> $clean Sanitised item form.
     * @param float $qty
     * @param float $price
     * @param float $tax
     * @return bool True when persisted; false when rejected (detail missing or
     *              over-stock hard block) — caller aborts the post-steps.
     */
    private function updateExistingItem(array $clean, float $qty, float $price, float $tax): bool
    {
        $detail = ShopOrderDetail::where('id', $this->editingItemId)
            ->where('order_id', $this->editingId)->first();
        if ($detail === null) {
            return false;
        }

        $oldQty = (float) $detail->qty;
        $delta = $qty - $oldQty;

        // WHY: unified hard block (ADR shop-admin_order-stock-parity, revised
        // 2026-08-16) — when increasing qty beyond stock, reject BEFORE writing /
        // decrementing stock so nothing partial is applied. Enable
        // product_buy_out_of_stock to sell beyond stock.
        if ($delta > 0) {
            $product = ShopProduct::find($detail->product_id);
            if ($product !== null && !$product->hasStockForOrder($delta)) {
                $this->notify('error', gp247_language_render('cart.item_over_qty', ['sku' => $detail->sku, 'qty' => $qty]));

                return false;
            }
        }

        (new ShopOrderDetail)->updateDetail($detail->id, [
            'qty' => $qty,
            'price' => $price,
            'tax' => $tax,
            'total_price' => $qty * $price,
        ]);

        // WHY: keep inventory in sync with the qty delta, as the legacy edit does.
        if ($qty !== $oldQty) {
            ShopProduct::updateStock($detail->product_id, $delta);
        }

        $this->logHistory(
            gp247_language_render('product.edit_product') . ' #' . $detail->id,
            $this->currentOrder()->status ?? 0,
        );

        return true;
    }

    /**
     * Add a new line item (reusing ShopOrderDetail::addNewDetail, which also
     * decrements stock — parity with the legacy add).
     *
     * @param array<string, mixed> $clean Sanitised item form.
     * @param float $qty
     * @param float $price
     * @param float $tax
     * @return bool True when persisted; false when rejected (invalid product or
     *              over-stock hard block) — caller aborts the post-steps.
     */
    private function addNewItem(array $clean, float $qty, float $price, float $tax): bool
    {
        $order = $this->currentOrder();
        if ($order === null) {
            return false;
        }

        $productId = (string) ($clean['product_id'] ?? '');
        $product = $productId !== '' ? ShopProduct::find($productId) : null;

        // WHY: every order line must map to a real, sellable product. Reject a
        // missing product or a GROUP container (kind=2, non-sellable, price always
        // 0 — same rule the product picker applies) instead of persisting a ghost
        // line (US-SADM-order-line-integrity). Validation already blocks this on
        // the happy path; this is the defensive backstop for the addNewItem path.
        if ($product === null || (int) $product->kind === GP247_PRODUCT_GROUP) {
            $this->notify('error', gp247_language_render('admin.data_not_found_detail', ['msg' => '#' . $productId]));

            return false;
        }

        // WHY: unified hard block (ADR shop-admin_order-stock-parity, revised
        // 2026-08-16) — reject over-stock BEFORE writing the line; admin now blocks
        // exactly like the storefront. Enable product_buy_out_of_stock to sell
        // beyond stock.
        if (!$product->hasStockForOrder($qty)) {
            $this->notify('error', gp247_language_render('cart.item_over_qty', ['sku' => $product->sku, 'qty' => $qty]));

            return false;
        }

        // WHY: product name lives on the description relation, so use getName()
        // (as selectProduct does) — $product->name is usually null.
        $name = $clean['name'] ?: ($product->getName() ?: $product->sku);
        $sku = $clean['sku'] ?: $product->sku;

        $row = [
            'id' => gp247_uuid(),
            'order_id' => $this->editingId,
            'product_id' => $productId,
            'name' => $name,
            'sku' => $sku,
            'qty' => $qty,
            'price' => $price,
            'total_price' => $qty * $price,
            'tax' => $tax,
            'attribute' => '[]',
            'currency' => $order->currency,
            'exchange_rate' => $order->exchange_rate,
            'created_at' => gp247_time_now(),
        ];
        (new ShopOrderDetail)->addNewDetail([$row]);

        // WHY: history is rendered as raw HTML; escape the product-derived name so
        // a crafted name cannot inject markup into the admin timeline.
        $this->logHistory(gp247_language_render('product.add_product') . ': ' . e($name), $order->status);

        return true;
    }

    /**
     * Delete a line item, restore its stock and recalculate the order totals.
     *
     * @param int|string $id Order-detail id.
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     */
    public function deleteItem($id): void
    {
        $this->authorizeAction('update');
        if ($this->editingId === null) {
            return;
        }

        $detail = ShopOrderDetail::where('id', $id)->where('order_id', $this->editingId)->first();
        if ($detail === null) {
            return;
        }

        $productId = $detail->product_id;
        $qty = (float) $detail->qty;
        $detail->delete();

        AdminOrder::updateSubTotal($this->editingId);
        ShopProduct::updateStock($productId, -$qty);

        $this->logHistory('Remove item pID#' . $productId, $this->currentOrder()->status ?? 0);
        $this->refreshOrder();
        $this->notify('success', gp247_language_render('action.update_success'));
    }
}
