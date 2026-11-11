<?php

namespace GP247\Shop\Admin\Livewire\Concerns;

/**
 * Product promotion + download editing for the shop-admin ProductManager
 * (group F, US-SADM-001). The promotion price (1:1 ShopProductPromotion) applies
 * to single/bundle products; the download path (ShopProductDownload) applies when
 * the product_type is "download". Both are written by ProductWriter delete-then-recreate (parity
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
}
