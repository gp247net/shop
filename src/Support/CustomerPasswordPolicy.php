<?php

namespace GP247\Shop\Support;

use GP247\Core\Support\Password\PasswordAudience;
use GP247\Core\Support\Password\PasswordPolicy;

/**
 * The customer account group of the core Password policy screen: its defaults, the config
 * rows a fresh install and an upgrade write, and its language rows — one source for the
 * provider, the seeders and the upgrade migration.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-admin-shell-password-policy-single-source, US-admin-shell-password-policy-audiences
 * @aidlc-adr admin-shell_password-policy-registry
 */
final class CustomerPasswordPolicy
{
    public const AUDIENCE = 'customer';
    /** Fresh install: customers are not admin accounts (PCI DSS 8.3.6 does not cover them); 8 keeps sign-up easy. */
    public const DEFAULT_MIN = 8;
    public const DEFAULT_MAX = 64;
    /** What the shop enforced before the screen existed — written on upgraded sites so nothing changes for them. */
    public const LEGACY_MIN = 6;
    public const LEGACY_MAX = 16;

    /**
     * The group the shop registers with core.
     *
     * @return PasswordAudience
     */
    public static function audience(): PasswordAudience
    {
        return new PasswordAudience(self::AUDIENCE, 'admin.password_policy.audience_customer', self::DEFAULT_MIN, self::DEFAULT_MAX);
    }

    /**
     * Site-wide (GLOBAL) config rows of the customer policy, every character switch off.
     *
     * @param int $min
     * @param int $max
     * @return list<array<string, mixed>>
     */
    public static function configRows(int $min, int $max): array
    {
        $values = ['min' => (string) $min, 'max' => (string) $max, 'letter' => '0', 'mixedcase' => '0', 'number' => '0', 'symbol' => '0'];
        $details = ['min' => 'min', 'max' => 'max', 'letter' => 'letter', 'mixedcase' => 'mixed', 'number' => 'number', 'symbol' => 'symbol'];
        $rows = [];
        foreach (PasswordPolicy::FIELDS as $sort => $field) {
            $rows[] = [
                'group'    => 'global',
                'code'     => 'password_policy',
                'key'      => PasswordPolicy::key(self::AUDIENCE, $field),
                'value'    => $values[$field],
                'sort'     => $sort,
                'detail'   => 'admin.password_policy.' . $details[$field],
                'store_id' => (string) GP247_STORE_ID_GLOBAL,
            ];
        }

        return $rows;
    }

    /**
     * Language rows (en / vi / ja) of the customer block title.
     *
     * @return list<array{code: string, text: string, position: string, location: string}>
     */
    public static function languageRows(): array
    {
        $rows = [];
        foreach (['en' => 'Customers', 'vi' => 'Khách hàng', 'ja' => '顧客'] as $location => $text) {
            $rows[] = ['code' => 'admin.password_policy.audience_customer', 'text' => $text, 'position' => 'admin.password_policy', 'location' => $location];
        }

        return $rows;
    }
}
