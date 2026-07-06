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
     * @return array<int|string, string>
     */
    protected function protectedMap(): array
    {
        return [
            '1' => 'New',
            '2' => 'Processing',
            '3' => 'Hold',
            '4' => 'Canceled',
            '5' => 'Done',
            '6' => 'Failed',
            '7' => 'Refunded',
        ];
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
