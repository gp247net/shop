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
     * Statuses that carry business meaning and therefore cannot be deleted.
     *
     * WHY built from the constants: the assumption "these four ids mean something to
     * the code" now lives in exactly one place (ShopPaymentStatus). This map used to
     * repeat it as literals, so nothing guaranteed the two stayed in step — and they
     * did not (ADR shop-admin_payment-status-enum-alignment, D4).
     *
     * The labels are the seeded English names, shown only in the "cannot delete"
     * message; what a status is CALLED stays editable, what it MEANS does not.
     *
     * @return array<int|string, string>
     *
     * @aidlc-story US-SADM-payment-status-enum-alignment
     */
    protected function protectedMap(): array
    {
        return [
            (string) ShopPaymentStatus::UNPAID  => 'Unpaid',
            (string) ShopPaymentStatus::PARTIAL => 'Partial payment',
            (string) ShopPaymentStatus::PAID    => 'Paid',
            (string) ShopPaymentStatus::REFUND  => 'Refund',
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
