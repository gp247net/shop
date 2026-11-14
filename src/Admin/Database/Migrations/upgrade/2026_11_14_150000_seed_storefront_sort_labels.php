<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Running sites: the two new options of the storefront sort dropdown — "Featured" (the
 * default order) and "Best selling". Fresh installs get them from DataShopLanguageSeeder;
 * the Japanese text comes from Locales/ja.php, the same file a fresh install reads.
 * insertOrIgnore keeps any text a site owner already edited. Idempotent, no cron/queue.
 *
 * Runs via gp247:shop-update (--path upgrade/), never the create-tables migration.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-005
 */
return new class extends Migration
{
    /**
     * en / vi rows of the new sort labels.
     *
     * @return array<int, array<string, string>>
     */
    public static function rows(): array
    {
        $labels = [
            'filter_sort.featured'     => ['Featured', 'Nổi bật'],
            'filter_sort.best_selling' => ['Best selling', 'Bán chạy'],
        ];
        $rows = [];
        foreach ($labels as $code => [$en, $vi]) {
            foreach (['en' => $en, 'vi' => $vi] as $location => $text) {
                $rows[] = ['code' => $code, 'text' => $text, 'position' => 'filter_sort', 'location' => $location];
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
        if (!Schema::connection(GP247_DB_CONNECTION)->hasTable(GP247_DB_PREFIX . 'languages')) {
            return;
        }
        DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX . 'languages')
            ->insertOrIgnore(array_merge(self::rows(), self::japaneseRows()));
    }

    /**
     * @return void
     */
    public function down()
    {
        // Intentionally empty: the texts are harmless and may have been edited by the site.
    }
};
