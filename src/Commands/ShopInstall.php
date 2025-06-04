<?php

namespace GP247\Shop\Commands;

use Illuminate\Console\Command;
use Throwable;
use DB;
use Illuminate\Support\Facades\Storage;

class ShopInstall extends Command
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
     * @return mixed
     */
    public function handle()
    {
        // Uninstall gp247 shop before install
        $this->call('gp247:shop-uninstall');
        
        // Install gp247 shop
        \DB::connection(GP247_DB_CONNECTION)->table('migrations')->where('migration', '00_00_00_create_tables_shop')->delete();

        $this->call('migrate', ['--path' => '/vendor/gp247/shop/src/DB/migrations/00_00_00_create_tables_shop.php']);
        $this->info('---------------> Migrate schema Shop default done!');

        $this->call('db:seed', ['--class' => '\GP247\Shop\DB\seeders\DataShopInitializeSeeder', '--force' => true]);
        $this->info('---------------> Seeding database Shop default done!');

        $this->call('db:seed', ['--class' => '\GP247\Shop\DB\seeders\DataShopDefaultSeeder', '--force' => true]);
        $this->info('---------------> Seeding database for store root done!');

        // Copy template default
        $this->call('vendor:publish', ['--tag' => 'gp247:view-shop-front']);

        $this->welcome();
    }

    private function welcome()
    {
        return Command::SUCCESS;
    }

}
