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
     * Parse, resolve (find-or-create) and sync a product's keyword tags from the
     * submitted comma-separated string. No-op when the `product_tags` feature is off,
     * so a disabled feature never mutates existing associations.
     *
     * @param \GP247\Shop\Models\ShopProduct $product
     * @param array<string, mixed> $data Sanitised form.
     * @return void
     */
    private function persistTags($product, array $data): void
    {
        if (!$this->productFieldEnabled('product_tags')) {
            return;
        }

        $product->tags()->sync($this->resolveTagIds((string) ($data['tags'] ?? '')));
    }

    /**
     * Resolve a comma-separated tag string into the set of tag ids, creating any tag
     * that does not yet exist. Names are de-duplicated by their canonical alias so
     * "New Arrival" and "new-arrival" collapse to one tag; blank/slug-less fragments
     * are dropped.
     *
     * @param string $raw Comma-separated tag names as typed by the admin.
     * @return array<int, int> Distinct tag ids to sync onto the pivot.
     */
    private function resolveTagIds(string $raw): array
    {
        $ids = [];
        foreach (explode(',', $raw) as $fragment) {
            $name = trim($fragment);
            if ($name === '') {
                continue;
            }
            $alias = ShopProductTag::normalizeAlias($name);
            // WHY: a name with no slug-able characters (e.g. only punctuation/emoji)
            // yields an empty alias — skip it rather than persist an unusable tag.
            if ($alias === '') {
                continue;
            }
            // WHY: firstOrCreate keys on the unique alias (business key), so concurrent
            // saves and repeated names converge on the same row instead of duplicating.
            $tag = ShopProductTag::firstOrCreate(
                ['alias' => $alias],
                gp247_clean(['name' => $name, 'status' => 1, 'sort' => 0])
            );
            $ids[$tag->id] = $tag->id;
        }

        return array_values($ids);
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
