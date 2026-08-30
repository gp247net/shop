<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Money-audit (P0–P3) — DATA half of the v3.0 upgrade path for an installed site.
 *
 * Runs AFTER 2026_08_30_150000_order_money_audit_schema.php (later timestamp), so the
 * ledger table and the discount / tax_rate columns already exist. This file makes only
 * DATA changes, and the STEP ORDER BELOW IS LOAD-BEARING:
 *
 *   1. normalize money signs        — turns pre-v3.0 negatives into magnitudes, restates balance
 *   2. backfill the payment ledger  — reads the now-normalised `received`
 *   3. align payment_status scale   — reads the now-normalised `received`
 *   4. backfill line tax_rate       — from the tax amount already on the line
 *   5. backfill line discount share — spreads the now-normalised order `discount`
 *
 * Steps 2, 3 and 5 depend on step 1 having run first; keeping them in one file fixes
 * that order for good. Every step is guarded and idempotent, so a second `gp247:update`
 * — or a core-only install with no shop tables — changes nothing (NFR-MAINT-001).
 *
 * A fresh install never runs this: its data is born in the v3.0 shape. This path only
 * carries a site already running v2.1/v2.2 forward, since GP247 is public from v2.1 and
 * a breaking change must ship its own conversion — no guessing on money
 * (ADR compat-foundation_public-release-migration-policy P1/P2/P4).
 *
 * NO DOWN PATH on any step: the pre-conversion signs, the old payment-status scale and
 * the real payment dates are gone by design and cannot be reconstructed. Rolling back a
 * release means restoring the database backup the release notes ask for.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-public-release-migration-contract
 * @aidlc-story US-CMP-order-transaction-schema
 * @aidlc-story US-SADM-payment-status-enum-alignment
 * @aidlc-story US-SADM-order-discount-pre-tax
 * @aidlc-adr shop-admin_money-sign-convention
 * @aidlc-adr shop_order-payment-ledger
 * @aidlc-adr shop-admin_payment-status-enum-alignment
 * @aidlc-adr shop-admin_order-discount-pre-tax
 * @aidlc-adr compat-foundation_public-release-migration-policy
 */
return new class extends Migration
{
    /**
     * Run the five data conversions in dependency order.
     *
     * @return void
     */
    public function up(): void
    {
        $connection = GP247_DB_CONNECTION;
        $orders = GP247_DB_PREFIX . 'shop_order';

        // A core-only install has no order table; nothing here applies.
        if (!Schema::connection($connection)->hasTable($orders)) {
            return;
        }

        $this->normalizeMoneySigns($connection);
        $this->backfillLedger($connection);
        $this->alignPaymentStatusScale($connection);
        $this->backfillTaxRate($connection);
        $this->backfillDiscountShares($connection);
    }

    /**
     * Step 1 — restate order money on the v3.0 magnitude contract.
     *
     * v3.0 stores every money value as a NON-NEGATIVE magnitude and applies the sign in
     * the formula (ShopOrderTotal::SIGN_MAP). A pre-v3.0 database (v2.1/v2.2) is not
     * uniformly signed — that inconsistency IS the defect: storefront rows stored
     * `discount`/`received` negative, admin rows stored them positive. Flipping ONLY
     * negatives normalises the storefront rows and leaves the already-correct admin rows
     * alone; v3.0 never writes a negative here, so the sign is a sound discriminator and
     * re-running is a no-op (RISK-BIZ-order-sign-split). The balance step restates the
     * invariant `balance = total - received`, which also repairs the inflated balance a
     * pre-v3.0 admin-created order stored as `total + received` (defect H2).
     *
     * Raw UPDATE…WHERE, not model loading: the conversion must run on shared hosting
     * over any catalogue size with no queue worker and no memory spike (NFR-AVAIL-001).
     *
     * @param string $connection
     * @return void
     */
    private function normalizeMoneySigns(string $connection): void
    {
        $orders = GP247_DB_PREFIX . 'shop_order';
        $totals = GP247_DB_PREFIX . 'shop_order_total';
        $db = DB::connection($connection);

        $db->statement("UPDATE `{$orders}` SET `discount` = -`discount` WHERE `discount` < 0");
        $db->statement("UPDATE `{$orders}` SET `received` = -`received` WHERE `received` < 0");

        if (Schema::connection($connection)->hasTable($totals)) {
            $db->statement(
                "UPDATE `{$totals}` SET `value` = -`value` WHERE `code` IN ('discount', 'received') AND `value` < 0"
            );
        }

        // Runs after the sign flip so `received` is already a magnitude. Orders that
        // already satisfy the invariant are untouched — that is what makes a re-run a no-op.
        $db->statement("UPDATE `{$orders}` SET `balance` = `total` - `received` WHERE `balance` <> `total` - `received`");
    }

    /**
     * Step 2 — seed the ledger with one payment row per order that carries money.
     *
     * `received` keeps its current value while becoming a DERIVED figure (Σ payment −
     * Σ refund). `paid_at` is the ORDER'S CREATION DATE, not a real payment date —
     * nothing in the old data records when money arrived — and is marked derived in
     * `note` rather than guessed (policy P4). Idempotent: orders that already have a
     * ledger row are skipped, so a second run adds nothing. One INSERT…SELECT at the
     * database, so a large catalogue never passes through PHP (NFR-AVAIL-001).
     *
     * @param string $connection
     * @return void
     */
    private function backfillLedger(string $connection): void
    {
        $orders = GP247_DB_PREFIX . 'shop_order';
        $ledger = GP247_DB_PREFIX . 'shop_order_transaction';

        if (!Schema::connection($connection)->hasTable($ledger)) {
            return;
        }

        $db = DB::connection($connection);
        $note = 'Backfill ' . date('Y-m-d') . ': paid_at is the ORDER DATE (derived, not the real payment date)';

        // UUID() from MySQL keeps the whole backfill in one statement. NULLIF on
        // exchange_rate guards a legacy 0/NULL rate: fall back to the amount itself,
        // correct whenever the order is already in the base currency.
        $sql = "
            INSERT INTO `{$ledger}`
                (`id`, `order_id`, `type`, `amount`, `amount_base`, `currency`, `exchange_rate`,
                 `method`, `gateway_transaction_id`, `paid_at`, `customer_id`, `note`,
                 `created_at`, `updated_at`)
            SELECT
                UUID(), o.`id`, 'payment', o.`received`,
                ROUND(o.`received` / COALESCE(NULLIF(o.`exchange_rate`, 0), 1), 2),
                o.`currency`, o.`exchange_rate`, o.`payment_method`, NULL, o.`created_at`,
                o.`customer_id`, ?, NOW(), NOW()
            FROM `{$orders}` o
            WHERE o.`received` <> 0
              AND NOT EXISTS (
                    SELECT 1 FROM `{$ledger}` t WHERE t.`order_id` = o.`id`
              )
        ";

        $db->statement($sql, [$note]);
    }

    /**
     * Step 3 — move orders off the old 0-3 payment-status constant scale onto the
     * shop_payment_status ids (1-4).
     *
     * 1/2/3 exist on both scales and mean different things, so the VALUE alone cannot
     * decide — the money does. `received <> 0` re-derives the status from the amounts
     * (unambiguous); `received = 0 AND status = 0` can only be the old scale, so it
     * becomes 1 (unpaid). Anything else is LEFT ALONE: an order with no money but a
     * status of 3 is an admin marking it paid by hand, and re-deriving it would erase
     * that decision. Runs after steps 1–2 so `received` already agrees with the ledger.
     *
     * @param string $connection
     * @return void
     */
    private function alignPaymentStatusScale(string $connection): void
    {
        $orders = GP247_DB_PREFIX . 'shop_order';
        $db = DB::connection($connection);

        // Money recorded: the amounts say what the status is, whichever scale wrote it.
        // Restating a derivation — a second run computes the same values, updates nothing.
        $db->statement("
            UPDATE `{$orders}`
               SET `payment_status` = CASE
                     WHEN `total` - `received` <  0 THEN 4
                     WHEN `total` - `received` =  0 THEN 3
                     ELSE 2
                   END
             WHERE `received` <> 0
        ");

        // No money and status 0: only the old constant scale ever wrote that.
        $db->statement("UPDATE `{$orders}` SET `payment_status` = 1 WHERE `received` = 0 AND `payment_status` = 0");
    }

    /**
     * Step 4 — recover each line's tax rate from the amount it was charged.
     *
     * The line stored a tax AMOUNT and never a rate; the rate lived only on the product,
     * and products change. tax_rate is derived as tax/total_price where that division is
     * meaningful, and left at 0 otherwise — a rate invented for a zero-priced line would
     * be a guess (policy P4). Idempotent: only rows still reading 0 are touched.
     *
     * @param string $connection
     * @return void
     */
    private function backfillTaxRate(string $connection): void
    {
        $details = GP247_DB_PREFIX . 'shop_order_detail';

        if (!Schema::connection($connection)->hasColumn($details, 'tax_rate')) {
            return;
        }

        DB::connection($connection)->statement("
            UPDATE `{$details}`
               SET `tax_rate` = ROUND(`tax` / `total_price` * 100, 2)
             WHERE `tax_rate` = 0
               AND `tax` <> 0
               AND `total_price` > 0
        ");
    }

    /**
     * Step 5 — spread each order's existing discount across its lines, proportionally.
     *
     * A discount that exists only at order level leaves a partial return with no
     * defensible refund figure (audit F6). Existing amounts are NOT recomputed
     * (documents are immutable, ADR D10); only the derived allocation is filled in, so
     * no figure of money changes. Each line takes its share of the order discount in
     * proportion to its line total, with the rounding remainder on the last line so the
     * shares add up exactly (invariant Σ line discount = order discount). Reads the
     * order `discount` normalised in step 1. Done per order in PHP because the remainder
     * has to land on a specific line; idempotent because it only touches orders whose
     * lines still sum to 0.
     *
     * @param string $connection
     * @return void
     */
    private function backfillDiscountShares(string $connection): void
    {
        $details = GP247_DB_PREFIX . 'shop_order_detail';
        $orders = GP247_DB_PREFIX . 'shop_order';

        if (!Schema::connection($connection)->hasColumn($details, 'discount')) {
            return;
        }

        $db = DB::connection($connection);

        // Only orders that carry a discount and whose lines are still untouched, so a
        // second run — or a run after someone edited an order — changes nothing.
        $pending = $db->table($orders . ' as o')
            ->join($details . ' as d', 'd.order_id', '=', 'o.id')
            ->where('o.discount', '<>', 0)
            ->groupBy('o.id')
            ->havingRaw('SUM(d.discount) = 0')
            ->pluck('o.discount', 'o.id');

        foreach ($pending as $orderId => $discount) {
            $lines = $db->table($details)
                ->where('order_id', $orderId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'total_price']);

            $subtotal = 0.0;
            foreach ($lines as $line) {
                $subtotal += (float) $line->total_price;
            }
            if ($subtotal <= 0) {
                continue;
            }

            $shares = gp247_allocate_discount(
                $lines->pluck('total_price')->map(fn ($v) => (float) $v)->all(),
                min((float) $discount, $subtotal)
            );

            foreach ($lines as $i => $line) {
                $db->table($details)->where('id', $line->id)->update(['discount' => $shares[$i]]);
            }
        }
    }

    /**
     * No down path — the pre-conversion signs, the old payment-status scale and the real
     * payment dates cannot be reconstructed. Rolling back a release means restoring the
     * database backup the release notes ask for.
     *
     * @return void
     */
    public function down(): void
    {
        // Intentionally empty — see the docblock above.
    }
};
