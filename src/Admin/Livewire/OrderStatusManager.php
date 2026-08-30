<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Shop\Models\ShopOrderStatus;

/**
 * Order-status manager (shop-admin Unit). Built-in statuses (1–7) are protected
 * from deletion, mirroring the legacy AdminOrderStatusController. Gated by
 * `admin_order_status`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-003
 * @aidlc-adr ADR-001, ADR-006, ADR-007
 */
class OrderStatusManager extends AbstractStatusManager
{
    protected ?string $permission = 'admin_order_status';

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function statusModelClass(): string
    {
        return ShopOrderStatus::class;
    }

    /**
     * Statuses an admin may not delete, read from the model that owns them.
     *
     * The ids used to be spelled out here as literals while the code branched on the
     * same numbers elsewhere — the assumption written twice, with nothing keeping the
     * two copies honest. Now the branching code and this guard read the same source
     * (ADR shop-admin_order-cancel-vs-delete; same correction as F18 for payment statuses).
     *
     * @return array<int|string, string>
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-cancel-restock
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    protected function protectedMap(): array
    {
        $map = [];
        foreach (ShopOrderStatus::businessIds() as $id => $name) {
            $map[(string) $id] = $name;
        }

        return $map;
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.order_status.list');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_order_status.index';
    }
}
