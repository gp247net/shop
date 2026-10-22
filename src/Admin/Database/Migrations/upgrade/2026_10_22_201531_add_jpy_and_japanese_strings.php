<?php

use GP247\Shop\Admin\Database\Seeders\DataShopLanguageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent upgrade for installed sites: add the Japanese Yen (JPY) currency and
 * the Japanese (ja) shop strings.
 *
 * JPY's default rate is declared against USD (150 JPY = 1 USD) but stored against
 * the site's own base currency: base USD => 150; another base with a USD row at
 * rate r => 150 × r; no usable USD rate => added switched off with the USD figure
 * as a placeholder and a warning — never a guessed rate
 * (NFR-MAINT-breaking-change-migration (d)). An existing JPY row is never touched.
 * Strings are insertOrIgnore'd, so text the site owner edited is kept.
 * Fresh installs get both from DataShopInitializeSeeder / DataShopLanguageSeeder:
 * nothing happens here while the target table is still empty.
 *
 * Runs via gp247:shop-update (--path upgrade/), never the create-tables migration.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-jpy-default-currency, US-CMP-japanese-default-locale
 * @aidlc-adr compat-foundation_locale-data-files
 */
return new class extends Migration
{
    /** Default JPY per 1 USD. */
    private const JPY_PER_USD = 150;

    /**
     * @return void
     */
    public function up()
    {
        $db = DB::connection(GP247_DB_CONNECTION);
        $this->addYen($db);
        $this->addJapaneseStrings($db);
    }

    /**
     * Insert JPY when the site has currencies but no JPY yet.
     *
     * @param \Illuminate\Database\ConnectionInterface $db
     * @return void
     */
    private function addYen($db)
    {
        $table = GP247_DB_PREFIX.'shop_currency';
        if (!$db->table($table)->exists() || $db->table($table)->where('code', 'JPY')->exists()) {
            return;
        }

        $baseCode = $db->table($table)->where('is_base', 1)->value('code');
        $usdRate = (float) $db->table($table)->where('code', 'USD')->value('exchange_rate');
        $rate = null;
        if ($baseCode === 'USD') {
            $rate = self::JPY_PER_USD;
        } elseif ($baseCode !== null && $usdRate > 0) {
            $rate = round(self::JPY_PER_USD * $usdRate, 6);
        }

        $db->table($table)->insert([
            'name' => 'Japanese Yen',
            'code' => 'JPY',
            'symbol' => '¥',
            'exchange_rate' => $rate ?? self::JPY_PER_USD,
            'precision' => 0,
            'symbol_first' => 1,
            'thousands' => ',',
            'status' => $rate === null ? 0 : 1,
            'is_base' => 0,
            'sort' => (int) $db->table($table)->max('sort') + 1,
        ]);

        if ($rate === null) {
            gp247_report('JPY was added switched off: the exchange rate could not be derived from the base currency ('
                .($baseCode ?? 'none').') because the site has no usable USD rate. Set the JPY rate, then enable it.');
        }
    }

    /**
     * Insert the Japanese shop strings when the language table is in use.
     *
     * @param \Illuminate\Database\ConnectionInterface $db
     * @return void
     */
    private function addJapaneseStrings($db)
    {
        $table = GP247_DB_PREFIX.'languages';
        if (!$db->table($table)->exists()) {
            return;
        }
        $rows = array_values(array_filter(
            (new DataShopLanguageSeeder())->dataLang(),
            fn ($row) => $row['location'] === 'ja'
        ));
        foreach (array_chunk($rows, 500) as $chunk) {
            $db->table($table)->insertOrIgnore($chunk);
        }
    }

    /**
     * WARNING: removes JPY only while no order uses it. Japanese strings are kept
     * (they may hold the owner's edits and are inert without the language).
     *
     * @return void
     */
    public function down()
    {
        $db = DB::connection(GP247_DB_CONNECTION);
        if ($db->table(GP247_DB_PREFIX.'shop_order')->where('currency', 'JPY')->exists()) {
            return;
        }
        $db->table(GP247_DB_PREFIX.'shop_currency')->where('code', 'JPY')->where('is_base', 0)->delete();
    }
};
