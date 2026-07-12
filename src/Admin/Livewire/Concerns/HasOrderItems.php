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
        $term = trim($this->productSearch);
        if (strlen($term) < 2) {
            return [];
        }

        $needle = '%' . $term . '%';

        // WHY: a "group" product (kind=2) is a non-sellable container linking to
        // real single/build products (price is always 0, and the storefront hides
        // its Add-to-cart button) — exclude it here to match that same rule.
        return ShopProduct::where('kind', '!=', GP247_PRODUCT_GROUP)
            ->where(function ($query) use ($needle) {
                $query->where('sku', 'like', $needle)
                    ->orWhere('alias', 'like', $needle);
            })
            ->limit(15)
            ->get();
    }

    /**
     * Start adding a new line item.
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
        $product = ShopProduct::find($id);
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
        $this->validate([
            'itemForm.qty' => 'required|' . gp247_qty_rule() . '|gt:0',
            'itemForm.price' => 'nullable|numeric|min:0',
        ]);

        $clean = gp247_clean($this->itemForm);
        $qty = (float) $clean['qty'];
        $price = (float) ($clean['price'] ?? 0);
        $tax = (float) ($clean['tax'] ?? 0);

        $this->editingItemId !== null
            ? $this->updateExistingItem($clean, $qty, $price, $tax)
            : $this->addNewItem($clean, $qty, $price, $tax);

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
     * @return void
     */
    private function updateExistingItem(array $clean, float $qty, float $price, float $tax): void
    {
        $detail = ShopOrderDetail::where('id', $this->editingItemId)
            ->where('order_id', $this->editingId)->first();
        if ($detail === null) {
            return;
        }

        $oldQty = (float) $detail->qty;
        (new ShopOrderDetail)->updateDetail($detail->id, [
            'qty' => $qty,
            'price' => $price,
            'tax' => $tax,
            'total_price' => $qty * $price,
        ]);

        // WHY: keep inventory in sync with the qty delta, as the legacy edit does.
        if ($qty !== $oldQty) {
            ShopProduct::updateStock($detail->product_id, $qty - $oldQty);
        }

        $this->logHistory(
            gp247_language_render('product.edit_product') . ' #' . $detail->id,
            $this->currentOrder()->status ?? 0,
        );
    }

    /**
     * Add a new line item (reusing ShopOrderDetail::addNewDetail, which also
     * decrements stock — parity with the legacy add).
     *
     * @param array<string, mixed> $clean Sanitised item form.
     * @param float $qty
     * @param float $price
     * @param float $tax
     * @return void
     */
    private function addNewItem(array $clean, float $qty, float $price, float $tax): void
    {
        $order = $this->currentOrder();
        if ($order === null) {
            return;
        }

        $productId = (string) ($clean['product_id'] ?? '');
        $product = $productId !== '' ? ShopProduct::find($productId) : null;
        $name = $clean['name'] ?: ($product->name ?? $product->sku ?? '');
        $sku = $clean['sku'] ?: ($product->sku ?? '');

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
