<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent upgrade: correct the English "infomation" spelling of three shop
 * labels for sites installed before DataShopLanguageSeeder was fixed. Only the
 * displayed text changes — the language codes (e.g. `customer.change_infomation`)
 * and the routes/views named after them stay as they are, since renaming them would
 * break templates and plugins that reference them.
 *
 * A label is only corrected while it still holds the exact misspelled text, so a
 * wording the site owner already edited is kept. Runs via gp247:shop-update.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-multi-vendor-pro-vendor-admin-livewire
 */
return new class extends Migration
{
    /**
     * English labels to correct: code => [misspelled, corrected].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private array $labels = [
        'product.product_specifications' => ['Additional infomation', 'Additional information'],
        'customer.update_infomation'     => ['Update infomation', 'Update information'],
        'customer.change_infomation'     => ['Change infomation', 'Change information'],
    ];

    /**
     * @return void
     */
    public function up()
    {
        foreach ($this->labels as $code => [$wrong, $right]) {
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'languages')
                ->where('code', $code)->where('location', 'en')->where('text', $wrong)
                ->update(['text' => $right]);
        }
    }

    /**
     * Reverts only the rows that still hold the text this migration wrote.
     *
     * @return void
     */
    public function down()
    {
        foreach ($this->labels as $code => [$wrong, $right]) {
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'languages')
                ->where('code', $code)->where('location', 'en')->where('text', $right)
                ->update(['text' => $wrong]);
        }
    }
};
