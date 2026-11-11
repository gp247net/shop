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
                'slug' => (string) $row->slug,
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
        $this->variants[] = ['attribute_group_id' => '', 'name' => '', 'add_price' => 0, 'slug' => '', 'sort' => 0, 'status' => 1];
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
     * Attribute-group options (id => name) for the variant pickers.
     *
     * @return array<int|string, string>
     */
    public function attributeGroupOptions(): array
    {
        return ShopAttributeGroup::pluck('name', 'id')->all();
    }

    /**
     * Distinct non-empty slugs already used within one attribute group, for the
     * variant slug datalist (pick an existing per-group slug or type a new one).
     * Scoped per group so, e.g., "Color" slugs never mix into "Size" (ADR
     * shop-admin_product-attribute-slug, decision 2).
     *
     * @param  int|string $groupId Attribute group id of the variant row; empty/0 → none.
     * @return array<int, string> Distinct slugs, ascending.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-product-attribute-slug
     * @aidlc-adr shop-admin_product-attribute-slug
     */
    public function attributeSlugOptions($groupId): array
    {
        if ($groupId === '' || $groupId === null) {
            return [];
        }

        return ShopProductAttribute::where('attribute_group_id', $groupId)
            ->where('slug', '<>', '')
            ->distinct()
            ->orderBy('slug')
            ->pluck('slug')
            ->all();
    }
}
