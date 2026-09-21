<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent upgrade: seed the heading of the home promotion strip
 * (blocks/shop_flash_sale.blade.php) for sites installed before
 * modification 20260921T231520. Fresh installs get it from DataShopLanguageSeeder.
 *
 * WHY a new code instead of rewriting `front.flash_sale`: the strip lists plain
 * price promotions (ShopProduct::getProductPromotion()), not a time-boxed flash
 * sale — that is the separate ProductFlashSale plugin, which carries its own
 * stock/sold ledger and its own labels. The old code is left in place so a
 * template or plugin still rendering a genuine flash-sale heading keeps its text.
 *
 * The block key itself stays `shop_flash_sale` (it is stored in every installed
 * site's front_layout_block.text — renaming it would make the block vanish); only
 * the visible label changes. The matching block *name* shown in the admin Layout
 * Block screen is renamed by the sibling migration in gp247/front, which owns that
 * table and the GP247Front AppConfig seed.
 *
 * insertOrIgnore keeps any text a site owner already edited. No cron/queue
 * (NFR-AVAIL-001). Runs via gp247:shop-update (--path upgrade/), never the
 * create-tables migration.
 *
 * @aidlc-unit frontend-template-dev
 * @aidlc-story US-TPL-009
 */
return new class extends Migration
{
    /**
     * Seed the vi/en labels of the promotion strip heading.
     *
     * @return void
     */
    public function up()
    {
        DB::connection(GP247_DB_CONNECTION)
            ->table(GP247_DB_PREFIX.'languages')
            ->insertOrIgnore([
                ['code' => 'front.promotion_products', 'text' => 'Sản phẩm khuyến mãi', 'position' => 'front', 'location' => 'vi'],
                ['code' => 'front.promotion_products', 'text' => 'Promotion products',  'position' => 'front', 'location' => 'en'],
            ]);
    }

    /**
     * Drop the seeded rows; the block then falls back to the blade default.
     *
     * @return void
     */
    public function down()
    {
        DB::connection(GP247_DB_CONNECTION)
            ->table(GP247_DB_PREFIX.'languages')
            ->where('code', 'front.promotion_products')
            ->delete();
    }
};
