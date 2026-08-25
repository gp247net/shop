<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only upgrade for existing GP247 2.0 installs: surface the keyword-tags feature on
 * the admin "Shop -> Field config" screen. That screen and its labels are driven entirely
 * by admin_config + languages rows, which the seeder only writes on a fresh install — so a
 * site installed before the keyword-tag feature never gets them from a schema migration.
 *
 * WHY a separate file from 2026_08_25_133422 (the schema rename/create): that migration may
 * already be recorded as "Ran" on a site that upgraded before the toggle existed. Laravel
 * never re-runs an applied migration, so extending it would not reach those installs. A new
 * timestamp is unrun and executes on the next gp247:shop-update.
 *
 * It performs three idempotent, non-destructive patches:
 *   1. Rename the delivery-type toggle key product_tag* -> product_type*, matching the
 *      renamed ShopProduct::product_type column so the existing toggle keeps working.
 *   2. Add the product_tags* toggle rows when absent (value '1' = enabled), so the new
 *      keyword-tags on/off switch appears. Insert-only: never re-enables a toggle an admin
 *      has since turned off.
 *   3. Fix the misleading delivery-type label and add the keyword-tags label (en + vi, the
 *      locales the seeder ships for these keys). The label is corrected only where it still
 *      holds the old wording, so a customized label is never clobbered.
 *
 * The "Product Tag" admin sidebar link is seeded by the separate later migration
 * 2026_08_25_160000_add_product_tag_admin_menu — kept apart because this migration may
 * already be recorded as Ran on a site that upgraded before the menu patch existed.
 *
 * No cron/queue (NFR-AVAIL-001). Re-running is a no-op.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-product-tags
 * @aidlc-adr shop-admin_product-tag-storage
 */
return new class extends Migration
{
    /**
     * Apply the config-row and language-row patches described in the class doc.
     *
     * @return void
     */
    public function up()
    {
        $this->patchConfig();
        $this->patchLanguage();
    }

    /**
     * Align an existing install's admin_config rows with the renamed delivery-type key
     * and the new keyword-tags toggle, so Shop -> Field config renders both correctly.
     *
     * @return void
     */
    private function patchConfig(): void
    {
        $table = GP247_DB_PREFIX.'admin_config';
        $db = DB::connection(GP247_DB_CONNECTION);

        // Rename product_tag* -> product_type* so the delivery-type toggle keeps matching
        // ShopProduct::product_type. Guard on the target being absent to respect the
        // unique(key, store_id) index on a partially-upgraded or re-run install.
        $renames = ['product_tag' => 'product_type', 'product_tag_required' => 'product_type_required'];
        foreach ($renames as $old => $new) {
            if ($db->table($table)->where('key', $old)->exists()
                && !$db->table($table)->where('key', $new)->exists()) {
                $db->table($table)->where('key', $old)->update(['key' => $new]);
            }
        }

        // Add the keyword-tags toggle rows only when missing, so an admin who later turns
        // the feature off is not silently re-enabled by a subsequent gp247:shop-update.
        $rows = [
            ['key' => 'product_tags', 'code' => 'product_config_attribute', 'value' => '1', 'detail' => 'admin.product.config_manager.tags'],
            ['key' => 'product_tags_required', 'code' => 'product_config_attribute_required', 'value' => '0', 'detail' => ''],
        ];
        foreach ($rows as $row) {
            $exists = $db->table($table)
                ->where('key', $row['key'])
                ->where('store_id', GP247_STORE_ID_GLOBAL)
                ->exists();
            if (!$exists) {
                $db->table($table)->insert([
                    'group' => 'gp247_cart',
                    'code' => $row['code'],
                    'key' => $row['key'],
                    'value' => $row['value'],
                    'sort' => 0,
                    'detail' => $row['detail'],
                    'store_id' => GP247_STORE_ID_GLOBAL,
                ]);
            }
        }
    }

    /**
     * Patch the languages rows for the two Field-config labels: fix the misleading
     * delivery-type label and add the keyword-tags label (en + vi).
     *
     * @return void
     */
    private function patchLanguage(): void
    {
        $table = GP247_DB_PREFIX.'languages';
        $db = DB::connection(GP247_DB_CONNECTION);

        // Correct the delivery-type label only where it still holds the old wording, so a
        // customized label is never clobbered and the fix stays idempotent.
        $tagFixes = [
            'en' => ['old' => 'Use Product Tags: download, service, physical', 'new' => 'Use DELIVERY TYPE (physical, download, digital)'],
            'vi' => ['old' => 'Sử dụng Thẻ: download, vật lý, dịch vụ,..', 'new' => 'Sử dụng LOẠI GIAO HÀNG (vật lý, download, số hóa)'],
        ];
        foreach ($tagFixes as $loc => $f) {
            $db->table($table)
                ->where('code', 'admin.product.config_manager.tag')
                ->where('location', $loc)
                ->where('text', $f['old'])
                ->update(['text' => $f['new']]);
        }

        // WHY insert-when-absent: a missing row makes gp247_language_render fall back to
        // trans(), which returns the raw code — so the new toggle would show its language
        // key instead of a label until this row exists.
        $tagsLabels = [
            'en' => 'Use product keyword tags (multi-tag)',
            'vi' => 'Sử dụng thẻ từ khóa sản phẩm (nhiều thẻ)',
        ];
        foreach ($tagsLabels as $loc => $text) {
            $exists = $db->table($table)
                ->where('code', 'admin.product.config_manager.tags')
                ->where('location', $loc)
                ->exists();
            if (!$exists) {
                $db->table($table)->insert([
                    'code' => 'admin.product.config_manager.tags',
                    'text' => $text,
                    'position' => 'admin.product.config_manager',
                    'location' => $loc,
                ]);
            }
        }
    }

    /**
     * No rollback: reverting would drop the keyword-tags toggle and re-introduce the
     * misleading label. Left intentionally empty (data-preserving), mirroring the sibling
     * upgrade migrations.
     *
     * @return void
     */
    public function down()
    {
        // Intentionally empty — see method doc.
    }
};
