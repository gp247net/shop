<?php

namespace GP247\Shop\Admin\Livewire\Concerns;

/**
 * Product gallery (sub-images) editing for the shop-admin ProductManager
 * (group F, US-SADM-001). Holds the list of sub-image paths and persists them
 * delete-then-recreate into ShopProductImage (parity with the legacy product
 * controller). The main image stays a product column (form.image).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-001
 * @aidlc-adr ADR-006, ADR-007
 */
trait HasProductImages
{
    /** @var array<int, string> Sub-image paths (the gallery). */
    public array $gallery = [];

    /**
     * Reset the gallery state (create mode).
     *
     * @return void
     */
    private function resetGallery(): void
    {
        $this->gallery = [];
    }

    /**
     * Load an existing product's sub-images into state.
     *
     * @param \GP247\Shop\Models\ShopProduct $model
     * @return void
     */
    private function fillGallery($model): void
    {
        $this->gallery = $model->images()->pluck('image')->map(static fn ($p) => (string) $p)->all();
    }

    /**
     * Append an empty gallery slot (filled by the media picker).
     *
     * @return void
     */
    public function addGalleryImage(): void
    {
        $this->gallery[] = '';
    }

    /**
     * Remove a gallery slot by index.
     *
     * @param int $index
     * @return void
     */
    public function removeGalleryImage(int $index): void
    {
        unset($this->gallery[$index]);
        $this->gallery = array_values($this->gallery);
    }

    /**
     * Persist the gallery for a product: delete existing rows then recreate from
     * the non-empty paths.
     *
     * @param \GP247\Shop\Models\ShopProduct $product
     * @return void
     */
    private function persistImages($product): void
    {
        $product->images()->delete();
        foreach ($this->gallery as $image) {
            $image = trim((string) $image);
            if ($image !== '') {
                $product->images()->create(['image' => gp247_clean($image)]);
            }
        }
    }
}
