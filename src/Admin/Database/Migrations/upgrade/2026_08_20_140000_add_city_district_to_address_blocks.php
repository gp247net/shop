<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use GP247\Core\Models\AdminConfig;

/**
 * Idempotent upgrade for existing GP247 2.0 installs: add the config-gated
 * administrative address columns (city / district) to the three denormalized
 * address blocks, and seed their (default-off) config toggles.
 *
 * WHY this lives in a dedicated upgrade/ subdirectory, not the Migrations root:
 * gp247:shop-install runs the single create-tables migration by explicit
 * --path (which DROPS and recreates every shop table). A fresh install already
 * gets city/district from that file, so this migration is only for sites that
 * were installed before this change and must NOT lose data. Keeping it in its
 * own folder lets `migrate --path=.../upgrade` run it alone without touching the
 * destructive create-tables file. It is wired to the gp247:shop-update command.
 *
 * Schema changes are the only concern here. The default-off config rows are
 * upserted via insertOrIgnore (new keys, so safe). Language relabeling of
 * address1/2/3 + the new city/district labels is handled separately by
 * `php artisan gp247:language-update` (upsert), so it is intentionally not
 * duplicated in this migration.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-address-city-district-schema
 * @aidlc-adr shop-admin_address-city-district
 */
return new class extends Migration
{
    /**
     * Tables carrying the denormalized customer address block.
     *
     * shop_order is a snapshot taken at checkout (it does not join the address
     * book), so the new columns must exist on it independently.
     *
     * @var string[]
     */
    private array $addressTables = [
        'shop_order',
        'shop_customer',
        'shop_customer_address',
    ];

    /**
     * Add city/district columns where missing, then seed default-off config.
     *
     * Every step is guarded so re-running the migration is a no-op.
     *
     * @return void
     */
    public function up()
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);

        foreach ($this->addressTables as $table) {
            $fullName = GP247_DB_PREFIX.$table;
            $schema->table($fullName, function (Blueprint $blueprint) use ($schema, $fullName) {
                // WHY: guard each column independently so a partial previous run
                // (e.g. interrupted) still converges to the full schema.
                if (!$schema->hasColumn($fullName, 'city')) {
                    $blueprint->string('city', 100)->nullable();
                }
                if (!$schema->hasColumn($fullName, 'district')) {
                    $blueprint->string('district', 100)->nullable();
                }
            });
        }

        $this->seedConfig();
    }

    /**
     * Insert the four default-off config toggles unless they already exist.
     *
     * Mirrors DataShopInitializeSeeder::dataConfigShop() so an upgraded site
     * matches a fresh install (QĐ-4, default '0').
     *
     * @return void
     */
    private function seedConfig(): void
    {
        AdminConfig::insertOrIgnore([
            ['group' => 'gp247_cart', 'code' => 'customer_config_attribute', 'key' => 'customer_city', 'value' => '0', 'sort' => '2', 'detail' => 'admin.customer.config_manager.city', 'store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart', 'code' => 'customer_config_attribute_required', 'key' => 'customer_city_required', 'value' => '0', 'sort' => '2', 'detail' => '', 'store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart', 'code' => 'customer_config_attribute', 'key' => 'customer_district', 'value' => '0', 'sort' => '2', 'detail' => 'admin.customer.config_manager.district', 'store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart', 'code' => 'customer_config_attribute_required', 'key' => 'customer_district_required', 'value' => '0', 'sort' => '2', 'detail' => '', 'store_id' => GP247_STORE_ID_GLOBAL],
        ]);
    }

    /**
     * Drop the two columns if present. Config and language rows are left in place
     * so a site owner's customisations survive a rollback.
     *
     * @return void
     */
    public function down()
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);

        foreach ($this->addressTables as $table) {
            $fullName = GP247_DB_PREFIX.$table;
            $schema->table($fullName, function (Blueprint $blueprint) use ($schema, $fullName) {
                foreach (['city', 'district'] as $column) {
                    if ($schema->hasColumn($fullName, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
