<?php

use GP247\Shop\Support\CustomerPasswordPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Running sites: the customer password policy moves onto the core Password policy screen.
 * Writes the values the shop enforced until now (6–16 characters, every switch off) so the
 * behaviour of sign-up, profile and password reset does not change; the site owner raises
 * them on the screen. A fresh install gets 8–64 from the seeder instead.
 *
 * Add-only (`insertOrIgnore` on key + store): a row already there — a fresh install's or one
 * the owner changed — is kept. Idempotent. down() keeps the rows (they are the site's setting).
 *
 * @aidlc-unit storefront
 * @aidlc-story US-admin-shell-password-policy-single-source
 * @aidlc-adr admin-shell_password-policy-registry
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);
        $db = DB::connection(GP247_DB_CONNECTION);
        if ($schema->hasTable(GP247_DB_PREFIX . 'admin_config')) {
            $db->table(GP247_DB_PREFIX . 'admin_config')->insertOrIgnore(
                CustomerPasswordPolicy::configRows(CustomerPasswordPolicy::LEGACY_MIN, CustomerPasswordPolicy::LEGACY_MAX)
            );
        }
        if ($schema->hasTable(GP247_DB_PREFIX . 'languages')) {
            $db->table(GP247_DB_PREFIX . 'languages')->insertOrIgnore(CustomerPasswordPolicy::languageRows());
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        //
    }
};
