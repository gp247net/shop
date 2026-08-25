<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent upgrade for existing GP247 2.0 installs: add the per-variant `slug`
 * column to shop_product_attribute. Authored in the admin product form (pick an
 * existing per-group slug or type a new one) and snapshotted into orders as the
 * optional 3rd '__' segment of each option string (name__add_price__slug).
 *
 * WHY this lives in upgrade/, not the Migrations root: gp247:shop-install runs the
 * destructive create-tables migration, which already ships the slug column on a
 * fresh install. This file only patches sites installed before this change; it is
 * wired to gp247:shop-update via --path so create-tables is never touched.
 *
 * The table has NO timestamps and a uuid product_id, so the ALTER only appends one
 * nullable-with-default column and touches nothing else. No data backfill
 * (NFR-MAINT-002: 2.0 has no direct 1.x upgrade) and no cron/queue (NFR-AVAIL-001,
 * shared-host safe). Re-running is a no-op (guarded by hasColumn).
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-product-attribute-slug
 * @aidlc-adr shop-admin_product-attribute-slug
 */
return new class extends Migration
{
    /**
     * Add the slug column only when it is absent, so a re-run or a fresh install
     * (where create-tables already added it) is left untouched.
     *
     * @return void
     */
    public function up()
    {
        $table = GP247_DB_PREFIX.'shop_product_attribute';

        if (!$this->hasColumn($table, 'slug')) {
            Schema::connection(GP247_DB_CONNECTION)->table($table, function (Blueprint $table) {
                // Place after add_price to mirror the create-tables column order;
                // default '' keeps existing rows valid without a backfill pass.
                $table->string('slug', 255)->default('')->after('add_price');
            });
        }
    }

    /**
     * Whether a column exists, via Schema (information_schema) so it works without
     * doctrine/dbal. On any driver error returns false so up() attempts the change
     * (a duplicate would error loudly, preferable to silently skipping a real install).
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
     * No structural rollback: dropping slug would discard authored variant slugs and
     * the snapshots already carried in orders. Left intentionally empty (data-preserving),
     * mirroring the other upgrade migrations.
     *
     * @return void
     */
    public function down()
    {
        // Intentionally empty — see method doc.
    }
};
