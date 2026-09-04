<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent upgrade: rename the total-method plugin group configCode "Total" -> "Promotion"
 * on an installed site (modification 20260904T225634).
 *
 * WHY: total-method plugins (coupon/discount, e.g. ShopDiscount) used configCode "Total";
 * it was renamed to "Promotion" so the group name reads as the business concept and matches
 * the plugin-manager filter (US-PLG-config-code-filter). The checkout resolves total plugins
 * by configCode via gp247_extension_get_via_code(); an installed site still carries
 * admin_config rows with code='Total' (written at install time), so without this backfill the
 * checkout would only find them through the legacy dual-read. This aligns the stored code so
 * the primary "Promotion" path matches.
 *
 * NOTE: this touches the PLUGIN configCode column admin_config.code for group='Plugins' ONLY.
 * It is unrelated to the order-total LINE code 'total' in shop_order_total — that is never
 * touched here.
 *
 * Guarded + idempotent: a second gp247:update (or a core-only install with no such rows)
 * changes nothing (NFR-MAINT-001). Runs via gp247:update (--path upgrade/), never the
 * create-tables migration (a fresh install is born with configCode "Promotion").
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-006
 * @aidlc-adr storefront_checkout-total-method-contract
 */
return new class extends Migration
{
    /**
     * Rename plugin configCode Total -> Promotion in admin_config (group=Plugins).
     *
     * @return void
     */
    public function up()
    {
        $table = GP247_DB_PREFIX . 'admin_config';
        if (!Schema::connection(GP247_DB_CONNECTION)->hasTable($table)) {
            return;
        }

        DB::connection(GP247_DB_CONNECTION)
            ->table($table)
            ->where('group', 'Plugins')
            ->where('code', 'Total')
            ->update(['code' => 'Promotion']);
    }

    /**
     * Reverse: Promotion -> Total for group=Plugins. Best-effort — a genuine "Promotion"
     * plugin that never was "Total" cannot be told apart, so rolling back is not exact;
     * the up path is the supported direction.
     *
     * @return void
     */
    public function down()
    {
        $table = GP247_DB_PREFIX . 'admin_config';
        if (!Schema::connection(GP247_DB_CONNECTION)->hasTable($table)) {
            return;
        }

        DB::connection(GP247_DB_CONNECTION)
            ->table($table)
            ->where('group', 'Plugins')
            ->where('code', 'Promotion')
            ->update(['code' => 'Total']);
    }
};
