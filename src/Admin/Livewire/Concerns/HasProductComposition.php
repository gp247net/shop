<?php

namespace GP247\Shop\Admin\Livewire\Concerns;

use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductBuild;
use GP247\Shop\Models\ShopProductGroup;

/**
 * Product composition editing for the shop-admin ProductManager (group F,
 * US-SADM-001): BUILD (bundle) components with quantities, and GROUP members.
 * Components are picked via a TailAdmin-only product search (no Select2/jQuery,
 * rule ui-tailadmin P2) and persisted delete-then-recreate into ShopProductBuild
 * / ShopProductGroup (parity with the legacy product controller).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-001
 * @aidlc-adr ADR-006, ADR-007
 */
trait HasProductComposition
{
    /** @var array<int, array<string, mixed>> Bundle components (product_id + quantity). */
    public array $buildItems = [];

    /** @var array<int, array<string, mixed>> Group members (product_id). */
    public array $groupItems = [];

    /** @var string Product-picker search term for composition. */
    public string $compositionSearch = '';

    /**
     * Reset composition state (create mode).
     *
     * @return void
     */
    private function resetComposition(): void
    {
        $this->buildItems = [];
        $this->groupItems = [];
        $this->compositionSearch = '';
    }

    /**
     * Load an existing product's build/group rows into state.
     *
     * @param \GP247\Shop\Models\ShopProduct $model
     * @return void
     */
    private function fillComposition($model): void
    {
        $this->buildItems = $model->builds()->get()->map(static function ($row): array {
            return ['product_id' => (string) $row->product_id, 'quantity' => (float) $row->quantity];
        })->all();

        $this->groupItems = $model->groups()->get()->map(static function ($row): array {
            return ['product_id' => (string) $row->product_id];
        })->all();
    }

    /**
     * Product-picker results (sku/alias match, excluding the edited product).
     *
     * @return iterable<mixed>
     */
    public function compositionResults(): iterable
    {
        $term = trim($this->compositionSearch);
        if (strlen($term) < 2) {
            return [];
        }

        $needle = '%' . $term . '%';

        return ShopProduct::where(static fn ($q) => $q->where('sku', 'like', $needle)->orWhere('alias', 'like', $needle))
            ->when($this->editingId !== null, fn ($q) => $q->where('id', '<>', $this->editingId))
            ->limit(15)
            ->get();
    }

    /**
     * Add a bundle component (default quantity 1).
     *
     * @param int|string $id Product id.
     * @return void
     */
    public function addBuildItem($id): void
    {
        $this->buildItems[] = ['product_id' => (string) $id, 'quantity' => 1];
        $this->compositionSearch = '';
    }

    /**
     * Remove a bundle component by index.
     *
     * @param int $index
     * @return void
     */
    public function removeBuildItem(int $index): void
    {
        unset($this->buildItems[$index]);
        $this->buildItems = array_values($this->buildItems);
    }

    /**
     * Add a group member.
     *
     * @param int|string $id Product id.
     * @return void
     */
    public function addGroupItem($id): void
    {
        $this->groupItems[] = ['product_id' => (string) $id];
        $this->compositionSearch = '';
    }

    /**
     * Remove a group member by index.
     *
     * @param int $index
     * @return void
     */
    public function removeGroupItem(int $index): void
    {
        unset($this->groupItems[$index]);
        $this->groupItems = array_values($this->groupItems);
    }

    /**
     * Validation rules for the active kind (build/group require ≥1 component).
     *
     * @return array<string, mixed>
     */
    private function compositionRules(): array
    {
        $kind = (int) ($this->form['kind'] ?? 0);
        if ($kind === (defined('GP247_PRODUCT_BUILD') ? GP247_PRODUCT_BUILD : 1)) {
            // WHY: per-component quantity format follows product_qty_decimal
            // (modification 20260705T093328, ADR-016) — previously unvalidated.
            return [
                'buildItems' => 'required|array|min:1',
                'buildItems.*.quantity' => 'required|' . gp247_qty_rule(),
            ];
        }
        if ($kind === (defined('GP247_PRODUCT_GROUP') ? GP247_PRODUCT_GROUP : 2)) {
            return ['groupItems' => 'required|array|min:1'];
        }

        return [];
    }

    /**
     * Persist build/group rows by kind (delete-then-recreate), parity with legacy.
     *
     * @param \GP247\Shop\Models\ShopProduct $product
     * @return void
     */
    private function persistComposition($product): void
    {
        $product->builds()->delete();
        $product->groups()->delete();

        $kind = (int) $product->kind;
        if ($kind === (defined('GP247_PRODUCT_BUILD') ? GP247_PRODUCT_BUILD : 1)) {
            foreach ($this->buildItems as $item) {
                $pid = $item['product_id'] ?? '';
                if ($pid !== '') {
                    $product->builds()->create(gp247_clean([
                        'product_id' => $pid,
                        'quantity' => (float) ($item['quantity'] ?? 1),
                    ]));
                }
            }
        } elseif ($kind === (defined('GP247_PRODUCT_GROUP') ? GP247_PRODUCT_GROUP : 2)) {
            foreach ($this->groupItems as $item) {
                $pid = $item['product_id'] ?? '';
                if ($pid !== '') {
                    $product->groups()->create(gp247_clean(['product_id' => $pid]));
                }
            }
        }
    }
}
