<?php

namespace GP247\Shop\Commands;

use Illuminate\Console\Command;
use GP247\Shop\Models\ShopCart;
use Carbon\Carbon;

class ShopClearCart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gp247:shop-clear-cart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear cart expire';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ShopCart::where('instance', 'default')->where('updated_at', '<', Carbon::now()->subDays(config('gp247-config.shop.cart_expire.cart')))->delete();
        ShopCart::where('instance', 'wishlist')->where('updated_at', '<', Carbon::now()->subDays(config('gp247-config.shop.cart_expire.wishlist')))->delete();
        ShopCart::where('instance', 'compare')->where('updated_at', '<', Carbon::now()->subDays(config('gp247-config.shop.cart_expire.compare')))->delete();
        \Log::info('Clear cart success!');
        $this->info('Clear cart success!');
        exit;
    }
}
