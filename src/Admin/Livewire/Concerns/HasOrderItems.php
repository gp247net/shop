<?php

namespace GP247\Shop\Admin\Livewire\Concerns;

use GP247\Shop\Admin\Models\AdminOrder;
use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Shop\Models\ShopOrderDetail;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductAttribute;

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
     * Attribute groups offered by the product currently in the form, each with
     * its selectable options — drives the per-group <select> in the add/edit form
     * (US-SADM-order-item-attribute-select).
     *
     * @var array<int, array{id: int|string, name: string, options: array<int, array{name: string, add_price: float}>}>
     */
    public array $itemAttrGroups = [];

    /**
     * Empty line-item form state. `attributes` maps attribute_group_id => chosen
     * option name (server rebuilds add_price on save, ADR
     * shop-admin_order-item-attribute-select).
     *
     * @return array<string, mixed>
     */
    private function itemDefaults(): array
    {
        return ['product_id' => '', 'name' => '', 'sku' => '', 'qty' => 1, 'price' => 0, 'tax' => 0, 'attributes' => []];
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
        $this->itemAttrGroups = [];
    }

    /**
     * Load the attribute groups + options a product offers into $itemAttrGroups.
     *
     * @param int|string $productId
     * @return void
     */
    private function loadItemAttrGroups($productId): void
    {
        $rows = ShopProductAttribute::where('product_id', $productId)->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->itemAttrGroups = [];

            return;
        }

        $names = ShopAttributeGroup::pluck('name', 'id');
        $groups = [];
        foreach ($rows->groupBy('attribute_group_id') as $groupId => $options) {
            $groups[] = [
                'id' => $groupId,
                'name' => (string) ($names[$groupId] ?? ('#' . $groupId)),
                'options' => $options->map(static fn ($o): array => [
                    'name' => (string) $o->name,
                    'add_price' => (float) $o->add_price,
                ])->values()->all(),
            ];
        }
        $this->itemAttrGroups = $groups;
    }

    /**
     * Sum of add_price for the currently selected option in each group, using
     * $itemAttrGroups as the authoritative price source (never client input).
     *
     * @param array<int|string, string> $attributes group_id => chosen option name.
     * @return float
     */
    private function selectedAttrAddPrice(array $attributes): float
    {
        $sum = 0.0;
        foreach ($this->itemAttrGroups as $group) {
            $chosen = $attributes[$group['id']] ?? null;
            foreach ($group['options'] as $option) {
                if ($chosen !== null && (string) $option['name'] === (string) $chosen) {
                    $sum += (float) $option['add_price'];
                    break;
                }
            }
        }

        return $sum;
    }

    /**
     * Preselect the first option of every group (parity with the legacy radio
     * default), returning the group_id => name map.
     *
     * @return array<int|string, string>
     */
    private function defaultAttrSelection(): array
    {
        $selection = [];
        foreach ($this->itemAttrGroups as $group) {
            if (!empty($group['options'])) {
                $selection[$group['id']] = (string) $group['options'][0]['name'];
            }
        }

        return $selection;
    }

    /**
     * Re-select an attribute option and adjust the suggested line price by the
     * delta (new add_price − old add_price), so the effective price stays correct
     * without compounding (ADR shop-admin_order-item-attribute-select, D3).
     *
     * @param int|string $groupId
     * @param string $name
     * @return void
     */
    public function setItemAttribute($groupId, string $name): void
    {
        $attributes = $this->itemForm['attributes'] ?? [];
        $oldAddPrice = $this->selectedAttrAddPrice($attributes);
        $attributes[$groupId] = $name;
        $newAddPrice = $this->selectedAttrAddPrice($attributes);

        $this->itemForm['attributes'] = $attributes;
        $this->itemForm['price'] = (float) ($this->itemForm['price'] ?? 0) + ($newAddPrice - $oldAddPrice);
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

        // WHY: preload the attribute-group id => name map once so mapping each
        // line's stored options stays a single query (not N per order line).
        $groups = ShopAttributeGroup::pluck('name', 'id');

        $this->items = ShopOrderDetail::where('order_id', $this->editingId)
            ->orderBy('id')
            ->get()
            ->map(function ($row) use ($groups): array {
                return [
                    'id' => (string) $row->id,
                    'product_id' => (string) $row->product_id,
                    'name' => (string) $row->name,
                    'sku' => (string) $row->sku,
                    'qty' => (float) $row->qty,
                    'price' => (float) $row->price,
                    'total_price' => (float) $row->total_price,
                    'tax' => (float) $row->tax,
                    'attributes' => $this->renderItemAttributes($row->attribute, $groups),
                ];
            })
            ->all();
    }

    /**
     * Build the display-ready attribute list for one order line (read-only).
     *
     * shop_order_detail.attribute is a JSON object keyed by attribute-group id,
     * each value the canonical "name__add_price" string (see ADR
     * storefront_attribute-price-integrity). Reuses gp247_render_option_price so
     * the surcharge format matches cart/storefront exactly (the "(+price)" part
     * shows only when add_price > 0). Returns [] for lines with no attribute
     * (null / '[]' / malformed JSON) so the view renders nothing extra.
     *
     * @param  string|null  $raw     Raw shop_order_detail.attribute JSON value.
     * @param  \Illuminate\Support\Collection<int|string, string>  $groups  Attribute-group id => name map.
     * @return array<int, array{name: string, value: string, slug: string}>  Group label + rendered "name (+price)" + snapshot slug.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-attribute-display
     * @aidlc-story US-SADM-order-attribute-slug
     * @aidlc-adr shop-admin_order-attribute-display
     * @aidlc-adr storefront_order-attribute-slug-encoding
     */
    private function renderItemAttributes(?string $raw, $groups): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === []) {
            return [];
        }

        $out = [];
        foreach ($decoded as $groupId => $optionValue) {
            // WHY: group id may reference a deleted attribute group — fall back to
            // "#<id>" so a stale reference degrades gracefully instead of blank.
            $out[] = [
                'name' => (string) ($groups[$groupId] ?? ('#' . $groupId)),
                'value' => gp247_render_option_price((string) $optionValue),
                // Snapshot slug (3rd '__' segment), empty-safe for legacy 2-segment
                // orders (US-SADM-order-attribute-slug, mod 20260825T135923).
                'slug' => explode('__', (string) $optionValue)[2] ?? '',
            ];
        }

        return $out;
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
        $this->productSearch = '';

        // WHY: load the product's attribute groups, preselect the first option of
        // each (parity with the legacy radio default) and suggest an effective
        // price = base price + selected surcharges. The admin can still override
        // the price (US-SADM-order-item-attribute-select, ADR D3).
        $this->loadItemAttrGroups($product->id);
        $this->itemForm['attributes'] = $this->defaultAttrSelection();
        $this->itemForm['price'] = (float) $product->price + $this->selectedAttrAddPrice($this->itemForm['attributes']);
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

        // WHY: prefill the attribute selection from the stored JSON
        // ({group_id:"name__add_price"}) so the edit form shows what was chosen;
        // add_price is descriptive here and rebuilt from the DB on save.
        $this->loadItemAttrGroups($detail->product_id);
        $stored = json_decode((string) $detail->attribute, true);
        $attributes = [];
        if (is_array($stored)) {
            foreach ($stored as $groupId => $value) {
                $attributes[$groupId] = explode('__', (string) $value)[0];
            }
        }

        $this->editingItemId = (string) $detail->id;
        $this->itemForm = [
            'product_id' => (string) $detail->product_id,
            'name' => (string) $detail->name,
            'sku' => (string) $detail->sku,
            'qty' => (float) $detail->qty,
            'price' => (float) $detail->price,
            'tax' => (float) $detail->tax,
            'attributes' => $attributes,
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
            // WHY: the per-line tax is editable again (US-SADM-order-info-edit,
            // restored from the legacy .edit-item-detail) — never negative.
            'itemForm.tax' => 'nullable|numeric|min:0',
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

        // WHY: rebuild the attribute selection server-side (add_price from the DB,
        // never client input) and require a complete selection when the product
        // has attributes — a partial pick is rejected instead of persisting a line
        // missing its variant (US-SADM-order-item-attribute-select, ADR
        // shop-admin_order-item-attribute-select; NFR-SEC-attribute-price-integrity).
        $options = gp247_cart_options_complete($clean['product_id'] ?? '', $clean['attributes'] ?? []);
        if ($options === false) {
            $this->notify('error', gp247_language_render('product.please_select_attribute'));

            return;
        }
        $attribute = $options === [] ? '[]' : json_encode($options);

        // WHY: the add/update helpers return false when the line is rejected
        // (over-stock hard block or invalid product) — abort the post-steps so we
        // neither recalc totals nor flash a misleading success. The helper already
        // showed the error toast.
        $persisted = $this->editingItemId !== null
            ? $this->updateExistingItem($clean, $qty, $price, $tax, $attribute)
            : $this->addNewItem($clean, $qty, $price, $tax, $attribute);

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
     * @param string $attribute Canonical attribute JSON to persist (or '[]').
     * @return bool True when persisted; false when rejected (detail missing or
     *              over-stock hard block) — caller aborts the post-steps.
     */
    private function updateExistingItem(array $clean, float $qty, float $price, float $tax, string $attribute): bool
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
            'attribute' => $attribute,
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
     * @param string $attribute Canonical attribute JSON to persist (or '[]').
     * @return bool True when persisted; false when rejected (invalid product or
     *              over-stock hard block) — caller aborts the post-steps.
     */
    private function addNewItem(array $clean, float $qty, float $price, float $tax, string $attribute): bool
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
            $this->notify('error', gp247_language_render('admin.display.data_not_found_detail', ['msg' => '#' . $productId]));

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
            'attribute' => $attribute,
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

        // Eloquent delete() triggers ShopOrderDetail::boot() deleting,
        // which calls ShopProduct::updateStock() to restore stock.
        // ⚠️  Do NOT add a manual ShopProduct::updateStock() call here —
        // that would restore stock twice (once from the event, once manually)
        // and silently inflate inventory.
        $detail->delete();

        AdminOrder::updateSubTotal($this->editingId);

        $this->logHistory('Remove item pID#' . $productId, $this->currentOrder()->status ?? 0);
        $this->refreshOrder();
        $this->notify('success', gp247_language_render('action.update_success'));
    }
}
