<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Shop\Models\ShopShippingStatus;

/**
 * Shipping-status manager (shop-admin Unit). Built-in statuses (1–4) are
 * protected from deletion, mirroring the legacy AdminShipingStatusController.
 * Gated by `admin_shipping_status`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-003
 * @aidlc-adr ADR-001, ADR-006, ADR-007
 */
class ShippingStatusManager extends AbstractStatusManager
{
    protected ?string $permission = 'admin_shipping_status';

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function statusModelClass(): string
    {
        return ShopShippingStatus::class;
    }

    /**
     * @return array<int|string, string>
     */
    protected function protectedMap(): array
    {
        $map = [];
        foreach (ShopShippingStatus::businessIds() as $id => $name) {
            $map[(string) $id] = $name;
        }

        return $map;
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.shipping_status.list');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_shipping_status.index';
    }
}
