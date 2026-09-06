<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only upgrade for existing installs: remove the standalone "Shop configuration"
 * sidebar link (uri `admin::shop_config`). Shop configuration is now a tab on the core
 * Configuration hub (store_config) via SettingsHubTabRegistry, so a separate menu item
 * is a duplicate entry point (US-SADM-shop-config-into-hub, mod 20260906T104800).
 *
 * The route itself is kept (it redirects to the hub), so any bookmarked link still works;
 * only the menu row is dropped. Idempotent: deleting by uri is a no-op when already gone.
 *
 * No cron/queue (NFR-AVAIL-001).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-shop-config-into-hub
 * @aidlc-adr admin-shell_config-hub-tab-registry
 */
return new class extends Migration
{
    /**
     * Delete the standalone shop_config admin-menu link when present.
     *
     * @return void
     */
    public function up()
    {
        $menu = GP247_DB_PREFIX . 'admin_menu';
        DB::connection(GP247_DB_CONNECTION)
            ->table($menu)
            ->where('uri', 'admin::shop_config')
            ->delete();
    }

    /**
     * No rollback: the link is superseded by the hub tab; re-adding it would restore a
     * duplicate entry point. Left intentionally empty (mirrors sibling upgrade migrations).
     *
     * @return void
     */
    public function down()
    {
        // Intentionally empty — see method doc.
    }
};
