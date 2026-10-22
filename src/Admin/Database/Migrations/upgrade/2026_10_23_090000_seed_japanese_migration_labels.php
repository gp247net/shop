<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent upgrade: give Japanese text to shop labels that an upgrade
 * migration (not the language seeder) put in the database — the shipping layer
 * of 2026_10_17_100000_install_shop_shipping (shipment, shipping settings,
 * ward). Their Japanese text lives in Locales/ja.php with every other shop
 * string; each row copies the position of the English row already in the
 * database, so only labels the site really has get a twin.
 *
 * Runs via gp247:shop-update, and on a fresh install right after the shipping
 * migration (ShopInstall). insertOrIgnore keeps text a site owner edited.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-japanese-default-locale
 * @aidlc-adr compat-foundation_locale-data-files
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        $file = __DIR__.'/../../Seeders/Locales/ja.php';
        $texts = is_file($file) ? require $file : [];
        if (!is_array($texts) || !$texts) {
            return;
        }

        $db = DB::connection(GP247_DB_CONNECTION);
        $table = GP247_DB_PREFIX.'languages';
        $positions = [];
        foreach (array_chunk(array_keys($texts), 500) as $codes) {
            $positions += $db->table($table)->where('location', 'en')->whereIn('code', $codes)
                ->pluck('position', 'code')->all();
        }
        $rows = [];
        foreach ($positions as $code => $position) {
            $rows[] = ['code' => $code, 'text' => (string) $texts[$code], 'position' => $position, 'location' => 'ja'];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            $db->table($table)->insertOrIgnore($chunk);
        }
    }

    /**
     * Japanese rows are kept on rollback: they may hold the owner's edits and are
     * inert without the ja language.
     *
     * @return void
     */
    public function down()
    {
    }
};
