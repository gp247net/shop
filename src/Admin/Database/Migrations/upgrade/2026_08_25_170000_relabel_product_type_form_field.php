<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only upgrade for existing GP247 2.0 installs: rename the product-edit form label
 * for the delivery-type dropdown from "Tags" to "Delivery type". The field is bound to
 * ShopProduct::product_type (physical/download/digital); the old "Tags" label sat right
 * next to the new "Keyword tags" input and was mistaken for it.
 *
 * WHY a separate file from 2026_08_25_150000 (which relabels the config-screen copy of
 * this toggle): that migration may already be recorded as "Ran", and Laravel never re-runs
 * an applied migration. A new timestamp is unrun and executes on the next gp247:shop-update.
 * These are two distinct languages rows: config screen uses admin.product.config_manager.tag,
 * the form field uses product.tag.
 *
 * Idempotent and non-destructive: the label is changed only where it still holds the old
 * wording, so a customized label is never clobbered and re-running is a no-op.
 *
 * No cron/queue (NFR-AVAIL-001).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-product-tags
 * @aidlc-adr shop-admin_product-tag-storage
 */
return new class extends Migration
{
    /**
     * Correct the product.tag form label (en + vi) where it still holds the old wording.
     *
     * @return void
     */
    public function up()
    {
        $table = GP247_DB_PREFIX.'languages';
        $db = DB::connection(GP247_DB_CONNECTION);

        $fixes = [
            'en' => ['old' => 'Tags', 'new' => 'Delivery type'],
            'vi' => ['old' => 'Các thẻ', 'new' => 'Loại giao hàng'],
        ];
        foreach ($fixes as $loc => $f) {
            $db->table($table)
                ->where('code', 'product.tag')
                ->where('location', $loc)
                ->where('text', $f['old'])
                ->update(['text' => $f['new']]);
        }
    }

    /**
     * No rollback: reverting would re-introduce the misleading "Tags" label. Left
     * intentionally empty (data-preserving), mirroring the other upgrade migrations.
     *
     * @return void
     */
    public function down()
    {
        // Intentionally empty — see method doc.
    }
};
