<?php

namespace GP247\Shop\Commands;

use GP247\Core\Console\GP247Command;
use GP247\Shop\Models\ShopCart;
use Carbon\Carbon;

/**
 * Remove expired cart / wishlist / compare entries based on the configured
 * expiry days. Suitable for a daily scheduler/cron.
 *
 * @aidlc-unit system-cli
 * @aidlc-story US-CLI-005
 * @aidlc-adr system-cli_output-contract
 */
class ShopClearCart extends GP247Command
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
     * @return int Exit code.
     */
    protected function handleGp247(): int
    {
        $deleted = [];
        $deleted['cart'] = ShopCart::where('instance', 'default')
            ->where('updated_at', '<', Carbon::now()->subDays(config('gp247-config.shop.cart_expire.cart')))
            ->delete();
        $deleted['wishlist'] = ShopCart::where('instance', 'wishlist')
            ->where('updated_at', '<', Carbon::now()->subDays(config('gp247-config.shop.cart_expire.wishlist')))
            ->delete();
        $deleted['compare'] = ShopCart::where('instance', 'compare')
            ->where('updated_at', '<', Carbon::now()->subDays(config('gp247-config.shop.cart_expire.compare')))
            ->delete();

        \Log::info('Clear cart success!');
        $this->info('Clear cart success!');

        return $this->respondSuccess(['deleted' => $deleted]);
    }
}
