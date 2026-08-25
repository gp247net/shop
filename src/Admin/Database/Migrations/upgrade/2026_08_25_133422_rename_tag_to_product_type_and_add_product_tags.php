<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent upgrade for existing GP247 2.0 installs: rename the mislabelled
 * shop_product.tag column (which stores the delivery TYPE: physical/download/digital)
 * to product_type, and add the new keyword-tag taxonomy (shop_product_tag +
 * shop_product_tag_pivot).
 *
 * The matching admin_config toggle rows and languages labels (data the seeder only
 * writes on a fresh install) are patched by the separate, later upgrade migration
 * 2026_08_25_150000_seed_product_tags_config_and_labels — kept apart so it still runs
 * on a site that already applied this schema migration before the toggle existed.
 *
 * WHY the rename: "tag" collided with the newly requested keyword-tag feature. The
 * column never held keywords — it gates download_path, ShopProductDownload creation,
 * order download links and storefront virtual-type filtering. Renaming (not dropping)
 * preserves all of that; the GP247_TAG_* constants keep working via aliases.
 *
 * WHY this lives in upgrade/, not the Migrations root: gp247:shop-install runs the
 * destructive create-tables migration, which already ships product_type and the two
 * tag tables on a fresh install. This file only patches sites installed before this
 * change; it is wired to gp247:shop-update via --path so create-tables is never touched.
 *
 * No data backfill (NFR-MAINT-002: 2.0 has no direct 1.x upgrade) and no cron/queue
 * (NFR-AVAIL-001). Re-running is a no-op.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-product-type-rename
 * @aidlc-story US-CMP-product-tag-schema
 * @aidlc-adr compat-foundation_product-type-rename
 * @aidlc-adr shop-admin_product-tag-storage
 */
return new class extends Migration
{
    /**
     * Rename tag -> product_type when needed, then create the tag tables when absent.
     *
     * @return void
     */
    public function up()
    {
        $product = GP247_DB_PREFIX.'shop_product';

        // Rename only when the legacy column still exists and the new one does not,
        // so a partially-upgraded or re-run install is left untouched.
        if ($this->hasColumn($product, 'tag') && !$this->hasColumn($product, 'product_type')) {
            Schema::connection(GP247_DB_CONNECTION)->table($product, function (Blueprint $table) {
                // renameColumn preserves the existing index/default from create-tables.
                $table->renameColumn('tag', 'product_type');
            });
        }

        $tag = GP247_DB_PREFIX.'shop_product_tag';
        if (!$this->hasTable($tag)) {
            Schema::connection(GP247_DB_CONNECTION)->create($tag, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 100);
                $table->string('alias', 120)->unique();
                $table->tinyInteger('status')->default(1)->index();
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }

        $pivot = GP247_DB_PREFIX.'shop_product_tag_pivot';
        if (!$this->hasTable($pivot)) {
            Schema::connection(GP247_DB_CONNECTION)->create($pivot, function (Blueprint $table) {
                $table->uuid('product_id');
                $table->unsignedInteger('tag_id');
                // Unique pair also indexes product_id (leftmost); index tag_id for the
                // reverse lookup used by storefront filter-by-tag (NFR-PERF-product-tag-filter).
                $table->unique(['product_id', 'tag_id']);
                $table->index('tag_id');
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
     * Whether a table exists. Returns false on driver error (so up() attempts create()).
     *
     * @param string $fullTable Fully prefixed table name.
     * @return bool True when the table is present.
     */
    private function hasTable(string $fullTable): bool
    {
        try {
            return Schema::connection(GP247_DB_CONNECTION)->hasTable($fullTable);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * No structural rollback: renaming back and dropping the tag tables would discard
     * the new taxonomy and re-introduce the "tag" naming collision. Left intentionally
     * empty (data-preserving), mirroring the other upgrade migrations.
     *
     * @return void
     */
    public function down()
    {
        // Intentionally empty — see method doc.
    }
};
