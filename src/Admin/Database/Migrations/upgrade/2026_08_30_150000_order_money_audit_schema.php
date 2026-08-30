<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money-audit (P0–P3) — SCHEMA half of the v3.0 upgrade path for an installed site.
 *
 * This file makes every STRUCTURAL change the money-audit needs; its sibling
 * `2026_08_30_150100_order_money_audit_data.php` makes every DATA change and runs
 * AFTER it (later timestamp), so the backfills there always find these columns and
 * this table already in place. Splitting the upgrade into schema-then-data keeps each
 * half independently readable and lets the data half assume the shape exists.
 *
 * A fresh install gets all of this from 00_00_00_create_tables_shop.php; this path
 * only exists to carry a site already running v2.1/v2.2 forward, delivered idempotently by
 * `gp247:shop-update` (ADR compat-foundation_public-release-migration-policy).
 *
 * Every step is guarded (hasTable / hasColumn / information_schema), so a second run —
 * or a core-only install with no shop tables — is a safe no-op (NFR-MAINT-001).
 *
 * WHAT IT DOES
 * ------------
 * - shop_order_transaction: the payment ledger — one row per payment or refund. What
 *   the customer paid is a SEQUENCE (when, how much, which method, which gateway ref),
 *   which a single `received` column could never carry (ADR shop_order-payment-ledger).
 *   Seeding from `received` is a DATA step and lives in the sibling file.
 * - shop_order_detail.discount: each line's share of the order discount, so a partial
 *   return has a defensible per-line refund figure (ADR shop-admin_order-discount-pre-tax).
 * - shop_order_detail.tax_rate decimal(8,4): the durable rate the line's tax is derived
 *   from. (8,4) — not (5,2) — matches shop_tax.value so a fractional rate (8.375%) is
 *   not truncated and lineTaxFor() can recompute exactly (US-CMP-tax-rate-precision).
 * - shop_order.stock_returned_at: marks that an order's goods are back in the warehouse,
 *   so cancel → re-open → cancel cannot hand the same goods back twice
 *   (ADR shop-admin_order-cancel-vs-delete).
 * - shop_order.checkout_token: one checkout session → at most one order (unique), so a
 *   double-submit / Back / refresh cannot create a second order. NULL is allowed and
 *   MySQL permits many NULLs, so admin/legacy orders are unaffected
 *   (ADR storefront_checkout-idempotency).
 * - shop_order_history.content → text: audit entries carry full addresses and
 *   before→after diffs that overflowed varchar(300) (US-SADM-order-audit-trail).
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-order-transaction-schema
 * @aidlc-story US-SADM-order-discount-pre-tax
 * @aidlc-story US-CMP-tax-rate-precision
 * @aidlc-story US-SADM-order-cancel-restock
 * @aidlc-story US-CMP-checkout-token-schema
 * @aidlc-story US-SADM-order-audit-trail
 * @aidlc-adr shop_order-payment-ledger
 * @aidlc-adr shop-admin_order-discount-pre-tax
 * @aidlc-adr shop-admin_order-cancel-vs-delete
 * @aidlc-adr storefront_checkout-idempotency
 * @aidlc-adr compat-foundation_public-release-migration-policy
 */
return new class extends Migration
{
    /**
     * Apply every structural change the money-audit needs, each guarded so a second
     * run or a core-only install is a no-op.
     *
     * @return void
     */
    public function up(): void
    {
        $connection = GP247_DB_CONNECTION;
        $schema = Schema::connection($connection);

        $orders = GP247_DB_PREFIX . 'shop_order';
        $details = GP247_DB_PREFIX . 'shop_order_detail';
        $history = GP247_DB_PREFIX . 'shop_order_history';
        $ledger = GP247_DB_PREFIX . 'shop_order_transaction';

        // A core-only install has no shop tables; nothing here applies.
        if (!$schema->hasTable($orders)) {
            return;
        }

        // Payment ledger. `amount`/`amount_base` are always magnitudes, mirroring the
        // sign contract of ADR shop-admin_money-sign-convention.
        if (!$schema->hasTable($ledger)) {
            $schema->create($ledger, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id')->index();
                $table->enum('type', ['payment', 'refund']);
                $table->decimal('amount', 15, 2);
                $table->decimal('amount_base', 15, 2)->default(0);
                $table->string('currency', 10)->nullable();
                $table->decimal('exchange_rate', 16, 6)->nullable();
                $table->string('method', 100)->nullable();
                $table->string('gateway_transaction_id', 100)->nullable()->unique();
                $table->dateTime('paid_at')->nullable()->index();
                $table->uuid('admin_id')->nullable();
                $table->uuid('customer_id')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Order-line discount share + durable tax rate. tax_rate is created directly at
        // (8,4) — the earlier upgrade split (add at 5,2 then widen) is collapsed here.
        if ($schema->hasTable($details)) {
            if (!$schema->hasColumn($details, 'discount')) {
                $schema->table($details, function (Blueprint $table) {
                    $table->decimal('discount', 15, 2)->default(0)->after('total_price');
                });
            }
            if (!$schema->hasColumn($details, 'tax_rate')) {
                $schema->table($details, function (Blueprint $table) {
                    $table->decimal('tax_rate', 8, 4)->default(0)->after('tax');
                });
            }
        }

        // Restock marker.
        if (!$schema->hasColumn($orders, 'stock_returned_at')) {
            $schema->table($orders, function (Blueprint $table) {
                $table->timestamp('stock_returned_at')->nullable()->after('status');
            });
        }

        // Checkout-idempotency token + unique index.
        if (!$schema->hasColumn($orders, 'checkout_token')) {
            $schema->table($orders, function (Blueprint $table) {
                $table->string('checkout_token', 64)->nullable()->after('transaction')->unique();
            });
        }

        // Widen audit content varchar(300) → text. Guarded on the live column type so a
        // second run does nothing; widening never loses data.
        if ($schema->hasTable($history) && $schema->hasColumn($history, 'content')) {
            $dataType = $schema->getConnection()
                ->table('information_schema.columns')
                ->where('table_schema', $schema->getConnection()->getDatabaseName())
                ->where('table_name', $history)
                ->where('column_name', 'content')
                ->value('DATA_TYPE');

            if ($dataType === null || strtolower((string) $dataType) !== 'text') {
                $schema->table($history, function (Blueprint $table) {
                    $table->text('content')->change();
                });
            }
        }
    }

    /**
     * No down path. The ledger is a financial record, and the columns/widenings are
     * additive or data-preserving — reversing any of them would destroy history no
     * other table holds. Rolling back a release means restoring the database backup the
     * release notes tell site owners to take
     * (ADR compat-foundation_public-release-migration-policy).
     *
     * @return void
     */
    public function down(): void
    {
        // Intentionally empty — see the docblock above.
    }
};
