<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only upgrade for existing installs: normalise the customer config-field labels
 * (position `admin.customer.config_manager`) to sentence case — all lowercase with only
 * the first letter capitalised — for every locale. Fixes shouty labels like
 * "Use MAIN ADDRESS" / "Sử dụng SỐ ĐIỆN THOẠI" seeded on older installs; fresh installs
 * get the corrected text from DataShopLanguageSeeder.
 *
 * Only the field-label rows are touched (not Value/Required/title). Idempotent: the
 * normaliser is a fixed point, so re-running is a no-op. No cron/queue (NFR-AVAIL-001).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-005
 */
return new class extends Migration
{
    /** Field-label codes (suffix under admin.customer.config_manager) to normalise. */
    private const CODES = [
        'first_name', 'email', 'lastname', 'name_kana', 'firstname_kana', 'lastname_kana',
        'lasttname_kana', 'city', 'district', 'address1', 'address2', 'address3', 'company',
        'postcode', 'country', 'group', 'birthday', 'sex', 'phone', 'customer_verify',
    ];

    /**
     * Rewrite each targeted label to sentence case (first letter upper, rest lower).
     *
     * @return void
     */
    public function up()
    {
        $table = GP247_DB_PREFIX . 'languages';
        $codes = array_map(static fn ($c) => 'admin.customer.config_manager.' . $c, self::CODES);

        $rows = DB::connection(GP247_DB_CONNECTION)
            ->table($table)
            ->where('position', 'admin.customer.config_manager')
            ->whereIn('code', $codes)
            ->get();

        foreach ($rows as $row) {
            $normalized = $this->toSentenceCase((string) $row->text);
            if ($normalized !== (string) $row->text) {
                DB::connection(GP247_DB_CONNECTION)
                    ->table($table)
                    ->where('id', $row->id)
                    ->update(['text' => $normalized]);
            }
        }
    }

    /**
     * All-lowercase with the first letter capitalised (multibyte-safe).
     *
     * @param string $text Raw label.
     * @return string
     */
    private function toSentenceCase(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }
        $lower = mb_strtolower($text, 'UTF-8');

        return mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($lower, 1, null, 'UTF-8');
    }

    /**
     * No rollback: the previous shouty casing is not worth restoring.
     *
     * @return void
     */
    public function down()
    {
        // Intentionally empty — cosmetic, non-reversible.
    }
};
