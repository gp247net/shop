<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent upgrade for existing GP247 2.0 installs: make the base (functional)
 * currency explicit by adding shop_currency.is_base, then flag the base row.
 *
 * WHY: the base currency — the unit every product price/cost/promotion is stored
 * in — used to be inferred implicitly as "the active currency with exchange_rate=1".
 * That breaks when a site has zero or several rate=1 currencies (seed drift, rate
 * edits), and it gives nothing to lock the base against edit/delete or to anchor a
 * safe rebase flow. is_base makes the base a single, verifiable fact
 * (ADR currency-base-system-scope, US-CMP-base-currency-explicit).
 *
 * WHY this lives in upgrade/, not the Migrations root: gp247:shop-install runs the
 * destructive create-tables migration (which already ships is_base on a fresh
 * install). This file is only for sites installed before this change; it is wired
 * to gp247:shop-update via --path so the create-tables file is never touched.
 *
 * Backfill policy: only when NO row is yet is_base=1, flag the single active
 * exchange_rate=1 currency (the default USD base). With zero or several such
 * candidates the migration does NOT guess — it logs a warning so the admin picks
 * the base explicitly via the "Change base" screen. Re-running is a no-op.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-base-currency-explicit
 * @aidlc-adr currency-base-system-scope
 */
return new class extends Migration
{
    /**
     * Add the is_base column when missing, then backfill the base flag when no
     * base has been designated yet.
     *
     * @return void
     */
    public function up()
    {
        $table = GP247_DB_PREFIX.'shop_currency';

        if (!$this->hasColumn($table, 'is_base')) {
            Schema::connection(GP247_DB_CONNECTION)->table($table, function (Blueprint $blueprint) {
                // Mirrors the create-tables definition (after `status`), default 0.
                $blueprint->tinyInteger('is_base')->default(0)->after('status');
            });
        }

        $this->backfillBase($table);
    }

    /**
     * Flag exactly one base currency when none is set yet.
     *
     * Runs only if no row already has is_base=1 (so a second run, or a site that
     * already picked its base, is untouched). Chooses the base only when there is
     * exactly one active exchange_rate=1 candidate; otherwise logs a warning and
     * leaves the decision to the admin (never guesses).
     *
     * @param string $table Fully prefixed shop_currency table name.
     * @return void
     */
    private function backfillBase(string $table): void
    {
        $connection = DB::connection(GP247_DB_CONNECTION);

        if ($connection->table($table)->where('is_base', 1)->exists()) {
            return;
        }

        $candidates = $connection->table($table)
            ->where('status', 1)
            ->where('exchange_rate', 1)
            ->pluck('code')
            ->all();

        if (count($candidates) === 1) {
            $connection->table($table)
                ->where('code', $candidates[0])
                ->update(['is_base' => 1]);
            return;
        }

        // WHY warn, not guess: 0 candidates (no rate=1 currency) or >=2 (ambiguous)
        // both mean the base cannot be derived safely. Flagging the wrong row would
        // silently mislabel the unit all catalog prices are stored in.
        Log::warning(
            'gp247/shop upgrade add_is_base_to_currency: could not derive a base currency '
            . '(' . count($candidates) . ' active exchange_rate=1 candidates). '
            . 'No is_base flag set — pick the base via the admin Currency screen ("Change base").'
        );
    }

    /**
     * Whether a column exists on the given table, via information_schema so it
     * works without doctrine/dbal. On any driver error, returns false so up() will
     * attempt to add the column (adding a duplicate would error loudly, which is
     * preferable to silently skipping on a real install).
     *
     * @param string $fullTable Fully prefixed table name.
     * @param string $column    Column name to check.
     * @return bool True when the column is present.
     */
    private function hasColumn(string $fullTable, string $column): bool
    {
        try {
            return Schema::connection(GP247_DB_CONNECTION)->hasColumn($fullTable, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * No structural rollback: dropping is_base would discard the explicit base
     * designation and fall back to the fragile implicit convention this upgrade
     * removes. Left intentionally empty (data-preserving).
     *
     * @return void
     */
    public function down()
    {
        // Intentionally empty — see method doc.
    }
};
