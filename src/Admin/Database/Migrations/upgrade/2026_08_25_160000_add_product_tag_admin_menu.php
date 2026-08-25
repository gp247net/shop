<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only upgrade for existing GP247 2.0 installs: add the "Product Tag" link to the
 * admin sidebar so the tag-management screen is reachable from the menu, not only by
 * typing its URL. The admin_menu row and its menu-title label are seeded fresh-install
 * only, so a site installed (or upgraded) before this feature never gets them.
 *
 * WHY a separate file from 2026_08_25_150000 (config + labels): that migration may already
 * be recorded as "Ran" on a site that upgraded before this menu patch existed, and Laravel
 * never re-runs an applied migration. A new timestamp is unrun and executes on the next
 * gp247:shop-update.
 *
 * Idempotent and non-destructive: the menu row is inserted only when absent (keyed on the
 * route uri) and the label rows only when absent, so re-running is a no-op.
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
     * Insert the Product Tag admin-menu link and its menu-title label when missing.
     *
     * @return void
     */
    public function up()
    {
        $menu = GP247_DB_PREFIX.'admin_menu';
        $lang = GP247_DB_PREFIX.'languages';
        $db = DB::connection(GP247_DB_CONNECTION);
        $uri = 'admin::product_tag';

        // Anchor under the Catalog block by its stable key, not a hardcoded id (ids differ
        // per install). If the block is missing (unexpected), skip rather than orphan the row.
        $parentId = $db->table($menu)->where('key', 'ADMIN_SHOP_CATALOG')->value('id');
        if ($parentId === null) {
            return;
        }

        // Insert-only, keyed on the route uri so a re-run never duplicates the link.
        if (!$db->table($menu)->where('uri', $uri)->exists()) {
            $db->table($menu)->insert([
                'parent_id' => $parentId,
                // sort 15 places it between Supplier (20) and Brand (10), matching the seeder.
                'sort' => 15,
                'title' => 'admin.menu_titles.product_tag',
                'icon' => 'fas fa-tags',
                'uri' => $uri,
                'type' => 0,
                'hidden' => 0,
                'key' => null,
            ]);
        }

        // The menu title renders through gp247_language_render; without these rows it would
        // show the raw code "admin.menu_titles.product_tag". Insert per locale when absent.
        $titles = ['en' => 'Product tags', 'vi' => 'Thẻ sản phẩm'];
        foreach ($titles as $loc => $text) {
            $exists = $db->table($lang)
                ->where('code', 'admin.menu_titles.product_tag')
                ->where('location', $loc)
                ->exists();
            if (!$exists) {
                $db->table($lang)->insert([
                    'code' => 'admin.menu_titles.product_tag',
                    'text' => $text,
                    'position' => 'admin.menu_titles',
                    'location' => $loc,
                ]);
            }
        }
    }

    /**
     * No rollback: removing the menu link would only hide a working screen. Left
     * intentionally empty (data-preserving), mirroring the other upgrade migrations.
     *
     * @return void
     */
    public function down()
    {
        // Intentionally empty — see method doc.
    }
};
