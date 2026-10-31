<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cart and checkout through REST API v2: the channel an order came from (web / api /
 * admin), the "ordering through the API" switch — OFF on fresh installs and on upgrades,
 * a site's own choice is never overwritten — and its label on the API screen.
 * Idempotent and add-only: runs again on an upgraded site without change.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-checkout-switch
 * @aidlc-adr api-commerce_switch-registry
 */
return new class extends Migration
{
    public const SWITCH_KEY = 'api_v2_front_checkout';

    /**
     * Labels of the switch on the API screen (en / vi; the Japanese text comes from Locales/ja.php,
     * the same file a fresh install reads).
     *
     * @return array<int, array<string, string>>
     */
    public static function rows(): array
    {
        $labels = [
            'switch_checkout' => ['Ordering through the API (app / headless)', 'Đặt đơn qua API (app / headless)'],
        ];
        $rows = [];
        foreach ($labels as $code => [$en, $vi]) {
            foreach (['en' => $en, 'vi' => $vi] as $location => $text) {
                $rows[] = ['code' => 'admin.api_connection.' . $code, 'text' => $text, 'position' => 'admin.api_connection', 'location' => $location];
            }
        }

        return $rows;
    }

    /**
     * Japanese twins of rows(), read from the shop's Japanese locale file.
     *
     * @return array<int, array<string, string>>
     */
    private static function japaneseRows(): array
    {
        $file = __DIR__ . '/../../Seeders/Locales/ja.php';
        $texts = is_file($file) ? require $file : [];
        $rows = [];
        foreach (self::rows() as $row) {
            if ($row['location'] === 'en' && is_array($texts) && isset($texts[$row['code']])) {
                $rows[] = ['location' => 'ja', 'text' => (string) $texts[$row['code']]] + $row;
            }
        }

        return $rows;
    }

    /**
     * @return void
     */
    public function up()
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);
        $db = DB::connection(GP247_DB_CONNECTION);

        // WHY nullable without default: an instant column add on MySQL 8 / MariaDB 10.3+ even on a
        // large order table; orders placed before this stay NULL (unknown channel).
        if ($schema->hasTable(GP247_DB_PREFIX . 'shop_order') && !$schema->hasColumn(GP247_DB_PREFIX . 'shop_order', 'channel')) {
            $schema->table(GP247_DB_PREFIX . 'shop_order', function (Blueprint $table) {
                $table->string('channel', 10)->nullable();
            });
        }

        if ($schema->hasTable(GP247_DB_PREFIX . 'admin_config')) {
            // WHY insertOrIgnore: unique (key, store_id) keeps a value the site already chose.
            $db->table(GP247_DB_PREFIX . 'admin_config')->insertOrIgnore([
                'group' => 'global', 'code' => 'api_config', 'key' => self::SWITCH_KEY, 'value' => '0', 'sort' => 7,
                'detail' => 'admin.api_connection.switch_checkout', 'store_id' => GP247_STORE_ID_GLOBAL,
            ]);
        }

        if ($schema->hasTable(GP247_DB_PREFIX . 'languages')) {
            $db->table(GP247_DB_PREFIX . 'languages')->insertOrIgnore(array_merge(self::rows(), self::japaneseRows()));
        }
    }

    /**
     * Removes the switch row and its labels. The `channel` column is kept on purpose: dropping it
     * would lose the channel of every order placed since (irreversible).
     *
     * @return void
     */
    public function down()
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);
        if ($schema->hasTable(GP247_DB_PREFIX . 'admin_config')) {
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX . 'admin_config')
                ->where('store_id', GP247_STORE_ID_GLOBAL)->where('key', self::SWITCH_KEY)->delete();
        }
    }
};
