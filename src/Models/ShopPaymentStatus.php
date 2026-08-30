<?php
#GP247/Shop/Models/ShopPaymentStatus.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopPaymentStatus extends Model
{
    use \GP247\Core\Models\ModelTrait;

    /**
     * ╔══════════════════════════════════════════════════════════════════════╗
     * ║  PAYMENT STATUS — THE SINGLE SCALE                                   ║
     * ╠══════════════════════════════════════════════════════════════════════╣
     * ║  These are the ROW IDS of this table as seeded by the installer —    ║
     * ║  not an arbitrary ordering. They are safe to hard-code because the   ║
     * ║  assumption already exists and is already enforced: these four rows  ║
     * ║  cannot be deleted (PaymentStatusManager), the storefront writes 1   ║
     * ║  on checkout, and the column defaults to 1.                          ║
     * ║                                                                      ║
     * ║  Use the CONSTANTS for business logic — "has this order been paid?"  ║
     * ║  is a rule of the code, not configuration, so it must not depend on  ║
     * ║  a row an admin can rename, and must not cost a query on hot paths   ║
     * ║  (order list, reports, debt filters).                                ║
     * ║                                                                      ║
     * ║  Use the TABLE (getIdAll()) for anything DISPLAYED — labels,         ║
     * ║  dropdowns, badges — so renaming a status, or adding one of your own ║
     * ║  (id >= 5), shows up everywhere. Never hard-code a label map keyed   ║
     * ║  by these numbers.                                                   ║
     * ║                                                                      ║
     * ║  Drift between the seed and these constants is caught by a test, not ║
     * ║  by a runtime lookup (ADR shop-admin_payment-status-enum-alignment). ║
     * ╚══════════════════════════════════════════════════════════════════════╝
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-payment-status-enum-alignment
     * @aidlc-adr shop-admin_payment-status-enum-alignment
     */
    public const UNPAID = 1;
    public const PARTIAL = 2;
    public const PAID = 3;
    public const REFUND = 4;

    /**
     * The ids that carry business meaning, so they may not be deleted.
     *
     * @return array<int, int>
     */
    public static function businessIds(): array
    {
        return [self::UNPAID, self::PARTIAL, self::PAID, self::REFUND];
    }

    /**
     * Derive the payment status of an order from the money on it.
     *
     * The single place this branch is expressed. It previously lived, copied, in
     * AdminOrder::updateSubTotal() and AdminOrderController::postCreate(); the payment
     * ledger would have made that three copies, which is exactly the scattering that
     * NFR-MAINT-status-enum-single-source forbids.
     *
     * Money is compared to the minor unit, not by float equality (closes F8):
     * received/balance are sums derived from many ledger rows (floats), so they carry
     * accumulated error (100 − 33.33 − 66.67 = 1e-14, not 0). A tolerance of half the
     * smallest unit (columns are decimal(15,2) → 0.005) keeps a fully-paid order out of
     * "partial" and a near-zero overpayment out of "refund"
     * (US-SADM-payment-status-float-tolerance).
     *
     * @param float $received Money collected, net of refunds (non-negative magnitude).
     * @param float $balance  Outstanding: total − received (negative means overpaid).
     * @return int One of the shop_payment_status row ids.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-payment-ledger
     * @aidlc-story US-SADM-payment-status-float-tolerance
     * @aidlc-adr shop_order-payment-ledger
     * @aidlc-adr shop-admin_payment-status-enum-alignment
     */
    public static function deriveFrom(float $received, float $balance): int
    {
        // Half the smallest currency unit (columns are decimal(15,2)).
        $epsilon = 0.005;

        if (abs($received) < $epsilon) {
            return self::UNPAID;
        }
        if ($balance < -$epsilon) {
            return self::REFUND;
        }
        if (abs($balance) < $epsilon) {
            return self::PAID;
        }

        return self::PARTIAL;
    }

    public $table = GP247_DB_PREFIX.'shop_payment_status';
    protected $guarded   = [];
    protected $connection = GP247_DB_CONNECTION;
    protected static $listStatus = null;
    public static function getIdAll()
    {
        if (!self::$listStatus) {
            self::$listStatus = self::pluck('name', 'id')->all();
        }
        return self::$listStatus;
    }
}
