<?php

namespace GP247\Shop\Admin\Livewire\Concerns;

use GP247\Shop\Models\ShopProductDownload;

/**
 * Product promotion + download editing for the shop-admin ProductManager
 * (group F, US-SADM-001). The promotion price (1:1 ShopProductPromotion) applies
 * to single/bundle products; the download path (ShopProductDownload) applies when
 * the product tag is "download". Both are persisted delete-then-recreate (parity
 * with the legacy product controller). Physical dimensions/weight are plain
 * product columns handled by the manager's attribute mapping.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-001
 * @aidlc-adr ADR-006, ADR-007
 */
trait HasProductPricing
{
    /**
     * Default pricing/download form values (merged into formDefaults()).
     *
     * @return array<string, mixed>
     */
    private function pricingDefaults(): array
    {
        return [
            'promotion_use' => 0,
            'price_promotion' => 0,
            'price_promotion_start' => '',
            'price_promotion_end' => '',
            'download_path' => '',
        ];
    }

    /**
     * Pricing/download form values loaded from an existing product.
     *
     * @param \GP247\Shop\Models\ShopProduct $model
     * @return array<string, mixed>
     */
    private function pricingFormFrom($model): array
    {
        $promotion = $model->promotionPrice;

        return [
            'promotion_use' => $promotion !== null ? 1 : 0,
            'price_promotion' => $promotion !== null ? (float) $promotion->price_promotion : 0,
            'price_promotion_start' => $promotion !== null && $promotion->date_start ? (string) $promotion->date_start : '',
            'price_promotion_end' => $promotion !== null && $promotion->date_end ? (string) $promotion->date_end : '',
            'download_path' => (string) ($model->downloadPath->path ?? ''),
        ];
    }

    /**
     * Persist the promotion (single/bundle only) and download path for a product.
     *
     * @param \GP247\Shop\Models\ShopProduct $product
     * @param array<string, mixed> $data Sanitised form.
     * @return void
     */
    private function persistPricing($product, array $data): void
    {
        $this->persistPromotion($product, $data);
        $this->persistDownload($product, $data);
    }

    /**
     * @param \GP247\Shop\Models\ShopProduct $product
     * @param array<string, mixed> $data
     * @return void
     */
    private function persistPromotion($product, array $data): void
    {
        $product->promotionPrice()->delete();

        $single = defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0;
        $build = defined('GP247_PRODUCT_BUILD') ? GP247_PRODUCT_BUILD : 1;
        $applies = in_array((int) $product->kind, [(int) $single, (int) $build], true);

        if (!empty($data['promotion_use']) && $applies) {
            $product->promotionPrice()->create(gp247_clean([
                'price_promotion' => (float) ($data['price_promotion'] ?? 0),
                'date_start' => !empty($data['price_promotion_start']) ? $data['price_promotion_start'] : null,
                'date_end' => !empty($data['price_promotion_end']) ? $data['price_promotion_end'] : null,
                'status_promotion' => 1,
            ]));
        }
    }

    /**
     * @param \GP247\Shop\Models\ShopProduct $product
     * @param array<string, mixed> $data
     * @return void
     */
    private function persistDownload($product, array $data): void
    {
        ShopProductDownload::where('product_id', $product->id)->delete();

        $downloadTag = defined('GP247_TAG_DOWNLOAD') ? GP247_TAG_DOWNLOAD : 'download';
        if (($data['tag'] ?? '') === $downloadTag && !empty($data['download_path'])) {
            // WHY: ShopProductDownload declares a composite primaryKey array, so its
            // creating-boot ($model->{getKeyName()}) breaks on create(); insert()
            // with an explicit uuid bypasses it (parity with the legacy controller).
            ShopProductDownload::insert(gp247_clean([
                'id' => gp247_uuid(),
                'product_id' => $product->id,
                'path' => $data['download_path'],
            ]));
        }
    }
}
