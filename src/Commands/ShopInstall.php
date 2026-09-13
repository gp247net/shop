<?php

namespace GP247\Shop\Commands;

use GP247\Core\Console\GP247Command;

/**
 * Install the GP247 shop (ecommerce) module: uninstall the old shop, recreate
 * tables, seed initialize + root-store defaults, publish shop front views.
 *
 * @aidlc-unit system-cli
 * @aidlc-story US-CLI-005
 * @aidlc-adr system-cli_output-contract
 */
class ShopInstall extends GP247Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gp247:shop-install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'GP247 shop install';

    /**
     * Execute the console command.
     *
     * @return int Exit code.
     */
    protected function handleGp247(): int
    {
        // Uninstall gp247 shop before install
        $this->runArtisan('gp247:shop-uninstall');

        // Install gp247 shop
        \DB::connection(GP247_DB_CONNECTION)->table('migrations')->where('migration', '00_00_00_create_tables_shop')->delete();

        $this->runArtisan('migrate', ['--path' => '/vendor/gp247/shop/src/Admin/Database/Migrations/00_00_00_create_tables_shop.php']);
        $this->info('---------------> Migrate schema Shop default done!');

        $this->runArtisan('db:seed', ['--class' => '\GP247\Shop\Admin\Database\Seeders\DataShopInitializeSeeder', '--force' => true]);
        $this->info('---------------> Seeding database Shop default done!');

        $this->runArtisan('db:seed', ['--class' => '\GP247\Shop\Admin\Database\Seeders\DataShopDefaultSeeder', '--force' => true]);
        $this->info('---------------> Seeding database for store root done!');

        // WHY no vendor:publish here any more (modification 20260913T200309):
        // shop's storefront views are served from this package through the
        // GP247TemplatePath hint paths, so an install no longer copies them into
        // app/GP247/Templates/GP247Front. Copying them used to freeze them there
        // forever — no composer update could ever reach the site again. A site
        // that wants to edit one publishes just that file:
        //   php artisan gp247:template-publish GP247Front --file=screen/shop_cart.blade.php
        return $this->respondSuccess(['installed' => true]);
    }
}
