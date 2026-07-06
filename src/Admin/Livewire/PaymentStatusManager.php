<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Shop\Models\ShopPaymentStatus;

/**
 * Payment-status manager (shop-admin Unit). Built-in statuses (1–4) are
 * protected from deletion, mirroring the legacy AdminPaymentStatusController.
 * Gated by `admin_payment_status`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-003
 * @aidlc-adr ADR-001, ADR-006, ADR-007
 */
class PaymentStatusManager extends AbstractStatusManager
{
    protected ?string $permission = 'admin_payment_status';

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function statusModelClass(): string
    {
        return ShopPaymentStatus::class;
    }

    /**
     * @return array<int|string, string>
     */
    protected function protectedMap(): array
    {
        return [
            '1' => 'Unpaid',
            '2' => 'Partial payment',
            '3' => 'Paid',
            '4' => 'Refund',
        ];
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.payment_status.list');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_payment_status.index';
    }
}
