<?php

namespace GP247\Shop\Admin\Livewire\Concerns;

use GP247\Shop\Models\ShopProductTag;

/**
 * Keyword multi-tag editing for the shop-admin ProductManager (US-SADM-product-tags).
 * The form field `tags` is a single comma-separated string (pick existing tags or type
 * new ones); on save it is parsed, each name normalized to its alias, resolved
 * find-or-create against shop_product_tag, then the product↔tag pivot is synced. Applies
 * to EVERY product kind (single/group/build) and is gated by the `product_tags` config.
 * The delivery-type column `product_type` is unrelated and handled elsewhere.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-product-tags
 * @aidlc-adr shop-admin_product-tag-storage, shop-admin_product-tag-input-ux
 */
trait HasProductTags
{
    /**
     * Default keyword-tags form value (merged into formDefaults()).
     *
     * @return array<string, mixed>
     */
    private function tagsDefaults(): array
    {
        return ['tags' => ''];
    }

    /**
     * Keyword-tags form value loaded from an existing product: the attached tag names
     * joined back into the comma-separated string the input edits.
     *
     * @param \GP247\Shop\Models\ShopProduct $model
     * @return array<string, mixed>
     */
    private function tagsFormFrom($model): array
    {
        return ['tags' => $model->tags->pluck('name')->implode(', ')];
    }

    /**
     * Existing active tag names, exposed to the form as datalist suggestions so the
     * admin can pick from what already exists (US-SADM-product-tags: pick-or-add UX).
     *
     * @return array<int, string>
     */
    public function tagSuggestions(): array
    {
        return ShopProductTag::active()->orderBy('name')->pluck('name')->all();
    }
}
