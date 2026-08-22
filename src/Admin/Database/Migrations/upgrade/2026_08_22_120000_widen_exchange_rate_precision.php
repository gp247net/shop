<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent upgrade for existing GP247 2.0 installs: widen exchange_rate to a
 * unified decimal(16,6) on the three tables that carry it.
 *
 * WHY: the original shop_order.exchange_rate was decimal(15,2), which truncated
 * a small snapshot rate (a large currency priced against a small-unit base, e.g.
 * VND base + USD ≈ 0.00004) to 0.00 — the order permanently lost its exchange
 * rate. shop_currency / shop_order_detail were float (imprecise, and a different
 * type). decimal(16,6) fixes both the truncation and the type mismatch
 * (RISK-TECH-exchange-rate-truncation, NFR-MAINT-exchange-rate-type-consistency,
 * ADR compat-foundation_exchange-rate-precision).
 *
 * WHY this lives in upgrade/, not the Migrations root: gp247:shop-install runs
 * the destructive create-tables migration (which already ships decimal(16,6) on
 * a fresh install). This file is only for sites installed before this change and
 * must keep their data — it is wired to gp247:shop-update, run by --path so the
 * create-tables file is never touched. Changing a column type preserves the rows
 * (values not needing >2 decimals are unaffected; already-truncated 0.00 rates
 * are NOT recoverable and are intentionally not backfilled).
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-exchange-rate-precision
 * @aidlc-adr compat-foundation_exchange-rate-precision
 */
return new class extends Migration
{
    /**
     * Target scale (decimal digits) for exchange_rate. A column already at this
     * scale is left untouched so the migration is a no-op on re-run.
     */
    private const TARGET_SCALE = 6;

    /**
     * Tables carrying exchange_rate, and whether the column is nullable there.
     * shop_currency's is NOT NULL (every currency has a rate); the two order
     * snapshots stay nullable, matching the create-tables definition.
     *
     * @var array<string, bool> table (unprefixed) => nullable
     */
    private array $tables = [
        'shop_currency' => false,
        'shop_order' => true,
        'shop_order_detail' => true,
    ];

    /**
     * Widen exchange_rate to decimal(16,6) where it is not already, on each table.
     *
     * @return void
     */
    public function up()
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);

        foreach ($this->tables as $table => $nullable) {
            $fullName = GP247_DB_PREFIX.$table;

            // WHY: skip the (potentially heavy on a live table) ALTER when the
            // column is already at the target scale, so re-running is a no-op.
            if ($this->currentScale($fullName) === self::TARGET_SCALE) {
                continue;
            }

            $schema->table($fullName, function (Blueprint $blueprint) use ($nullable) {
                $column = $blueprint->decimal('exchange_rate', 16, 6);
                if ($nullable) {
                    $column->nullable();
                }
                $column->change();
            });
        }
    }

    /**
     * Read the current decimal scale of a table's exchange_rate column.
     *
     * Uses information_schema so it works without doctrine/dbal. Returns null
     * when the scale cannot be determined (e.g. a driver without that view), in
     * which case up() falls through and re-applies the type — still safe, as
     * changing a column to its existing definition does not error.
     *
     * @param string $fullTable Fully prefixed table name.
     * @return int|null The numeric scale, or null when unknown.
     */
    private function currentScale(string $fullTable): ?int
    {
        try {
            $connection = DB::connection(GP247_DB_CONNECTION);
            $row = $connection->selectOne(
                'SELECT NUMERIC_SCALE AS scale FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$connection->getDatabaseName(), $fullTable, 'exchange_rate']
            );
        } catch (\Throwable $e) {
            return null;
        }

        return isset($row->scale) ? (int) $row->scale : null;
    }

    /**
     * No structural rollback: the pre-upgrade types (decimal(15,2) / float) held
     * fewer or imprecise decimals, so reverting could lose the very precision this
     * migration restored. Left intentionally empty (data-preserving).
     *
     * @return void
     */
    public function down()
    {
        // Intentionally empty — see method doc.
    }
};
