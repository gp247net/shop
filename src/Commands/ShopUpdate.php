<?php

namespace GP247\Shop\Commands;

use Illuminate\Console\Command;
use Throwable;

/**
 * Non-destructive upgrade for an existing GP247 shop install.
 *
 * Unlike gp247:shop-install (which drops and recreates every shop table), this
 * command only applies incremental, idempotent schema/config changes so a live
 * site keeps its data. It runs the upgrade migrations by --path (the shop's
 * create-tables migration is deliberately NOT auto-discovered) and then reminds
 * the operator to refresh language rows via gp247:language-update.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-address-city-district-schema
 * @aidlc-adr shop-admin_address-city-district
 */
class ShopUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gp247:shop-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Non-destructive upgrade of the GP247 shop schema/config for an existing install';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            // WHY: run only the upgrade/ folder, never the sibling create-tables
            // migration which would wipe the database.
            $this->call('migrate', [
                '--path'  => '/vendor/gp247/shop/src/Admin/Database/Migrations/upgrade',
                '--force' => true,
            ]);
            $this->info('---------------> Shop upgrade migrations done!');
        } catch (Throwable $e) {
            gp247_report($e->getMessage());
            $this->error('Shop upgrade failed: '.$e->getMessage());
            return Command::FAILURE;
        }

        // Relabeled + new address labels are refreshed here rather than in the
        // migration so a site owner's edited translations are only overwritten
        // when they explicitly opt into the upsert command.
        $this->line('');
        $this->info('Next step: run "php artisan gp247:language-update" to refresh address labels (city/district + renamed address1/2/3).');

        return Command::SUCCESS;
    }
}
