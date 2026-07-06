<?php

namespace GP247\Shop\Admin\Livewire\Concerns;

use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Shop\Models\ShopProductAttribute;

/**
 * Product variants/attributes editing for the shop-admin ProductManager (group F,
 * US-SADM-001) — SINGLE products only. Each variant is an option under an
 * attribute group (name + optional add_price). Persisted delete-then-recreate
 * into ShopProductAttribute (parity with the legacy product controller).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-001
 * @aidlc-adr ADR-006, ADR-007
 */
trait HasProductVariants
{
    /** @var array<int, array<string, mixed>> Variant rows (group/name/add_price/sort/status). */
    public array $variants = [];

    /**
     * Reset variants state (create mode).
     *
     * @return void
     */
    private function resetVariants(): void
    {
        $this->variants = [];
    }

    /**
     * Load an existing product's attributes into variant state.
     *
     * @param \GP247\Shop\Models\ShopProduct $model
     * @return void
     */
    private function fillVariants($model): void
    {
        $this->variants = $model->attributes()->orderBy('sort')->get()->map(static function ($row): array {
            return [
                'attribute_group_id' => (string) $row->attribute_group_id,
                'name' => (string) $row->name,
                'add_price' => (float) $row->add_price,
                'sort' => (int) $row->sort,
                'status' => (int) $row->status,
            ];
        })->all();
    }

    /**
     * Append an empty variant row.
     *
     * @return void
     */
    public function addVariant(): void
    {
        $this->variants[] = ['attribute_group_id' => '', 'name' => '', 'add_price' => 0, 'sort' => 0, 'status' => 1];
    }

    /**
     * Remove a variant row by index.
     *
     * @param int $index
     * @return void
     */
    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    /**
     * Persist variants for a SINGLE product: delete existing then recreate the
     * rows that have both an attribute group and a name.
     *
     * @param \GP247\Shop\Models\ShopProduct $product
     * @return void
     */
    private function persistVariants($product): void
    {
        $product->attributes()->delete();

        $single = defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0;
        if ((int) $product->kind !== (int) $single) {
            return;
        }

        foreach ($this->variants as $variant) {
            $groupId = $variant['attribute_group_id'] ?? '';
            $name = trim((string) ($variant['name'] ?? ''));
            if ($groupId === '' || $name === '') {
                continue;
            }
            $product->attributes()->create(gp247_clean([
                'attribute_group_id' => $groupId,
                'name' => $name,
                'add_price' => (float) ($variant['add_price'] ?? 0),
                'sort' => (int) ($variant['sort'] ?? 0),
                'status' => empty($variant['status']) ? 0 : 1,
            ]));
        }
    }

    /**
     * Attribute-group options (id => name) for the variant pickers.
     *
     * @return array<int|string, string>
     */
    public function attributeGroupOptions(): array
    {
        return ShopAttributeGroup::pluck('name', 'id')->all();
    }
}
