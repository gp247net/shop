<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent upgrade: seed the toast shown when a second click on an active
 * wishlist/compare button takes the product back out of that list
 * (ProductCard::toggleList()), for sites installed before this change. Fresh
 * installs get it from DataShopLanguageSeeder.
 *
 * insertOrIgnore keeps any text a site owner already edited. No cron/queue
 * (NFR-AVAIL-001). Runs via gp247:shop-update (--path upgrade/), never the
 * create-tables migration.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-004
 */
return new class extends Migration
{
    /**
     * Seed the vi/en labels of the "removed from list" toast.
     *
     * @return void
     */
    public function up()
    {
        DB::connection(GP247_DB_CONNECTION)
            ->table(GP247_DB_PREFIX.'languages')
            ->insertOrIgnore([
                ['code' => 'cart.remove_from_cart_success', 'text' => 'Đã gỡ khỏi :instance', 'position' => 'cart', 'location' => 'vi'],
                ['code' => 'cart.remove_from_cart_success', 'text' => 'Removed from :instance', 'position' => 'cart', 'location' => 'en'],
            ]);
    }

    /**
     * Drop the seeded rows; the toast then shows the bare language code.
     *
     * @return void
     */
    public function down()
    {
        DB::connection(GP247_DB_CONNECTION)
            ->table(GP247_DB_PREFIX.'languages')
            ->where('code', 'cart.remove_from_cart_success')
            ->delete();
    }
};
