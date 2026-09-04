<?php

namespace GP247\Shop\Commands;

use GP247\Core\Console\GP247Command;
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
 * @aidlc-story US-CLI-005
 * @aidlc-adr system-cli_output-contract
 */
class ShopUpdate extends GP247Command
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
     * @return int Exit code.
     */
    protected function handleGp247(): int
    {
        try {
            // WHY: run only the upgrade/ folder, never the sibling create-tables
            // migration which would wipe the database.
            $this->runArtisan('migrate', [
                '--path'  => '/vendor/gp247/shop/src/Admin/Database/Migrations/upgrade',
                '--force' => true,
            ]);
            $this->info('---------------> Shop upgrade migrations done!');
        } catch (Throwable $e) {
            gp247_report($e->getMessage());
            return $this->respondFailure('upgrade_failed', 'Shop upgrade failed: '.$e->getMessage());
        }

        // New/renamed language rows shipped by upgrades are refreshed here rather than
        // in the migrations so a site owner's edited translations are only overwritten
        // when they explicitly opt into the upsert command. WHY generic: the hint is not
        // tied to any single upgrade — many upgrades add or rename language keys over time.
        $this->line('');
        $this->info('Next step (optional): run "php artisan gp247:language-update" to refresh language rows for any new or renamed keys (upsert — overwrites edited translations).');

        return $this->respondSuccess([
            'upgraded'  => true,
            'next_step' => 'gp247:language-update',
        ]);
    }
}
