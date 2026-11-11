<?php

namespace GP247\Shop\Services\Catalog;

use GP247\Shop\Models\ShopCustomer;
use Illuminate\Support\Str;

/**
 * Creates and edits a customer with the rules of the admin Customer screen (the brownfield
 * mapping helpers gp247_customer_data_insert_mapping / _edit_mapping, so the storefront
 * register, the API and every writer agree).
 *
 * Payload (request-shaped, like the screen posts it): email, first_name, last_name, phone,
 * country, postcode, company, sex, birthday, group, first_name_kana, last_name_kana, city,
 * district, ward, address1–3, status, password, password_confirmation, `fields` (custom
 * fields, code => value) and — for an import only — `password_hash`. On edit a field that is
 * absent is left as stored.
 *
 * Passwords of an import (US-data-import-customers, D7): a `password_hash` is kept only when
 * it is a bcrypt or argon2 hash (anything else is an error on `password_hash`); without one
 * the customer gets an unusable random password and signs in after a password reset. The
 * admin screen keeps requiring a password on create.
 *
 * Errors are thrown as a ValidationException keyed by the payload field (`first_name`,
 * `fields.<code>`, `password_hash`); the screen re-keys them to its form.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-catalog-write-service
 * @aidlc-adr shop-admin_catalog-write-service
 */
class CustomerWriter extends CatalogWriter
{
    /** Scalar customer fields. */
    public const FIELDS = [
        'email', 'first_name', 'last_name', 'phone', 'country', 'postcode',
        'company', 'sex', 'birthday', 'group', 'first_name_kana', 'last_name_kana',
        'city', 'district', 'ward', 'address1', 'address2', 'address3',
    ];

    /** Password hash algorithms an import may carry over (password_get_info names). */
    private const ACCEPTED_HASHES = ['bcrypt', 'argon2i', 'argon2id'];

    /**
     * @param WriteContext $ctx Owner store (on edit: the customer's own store) and actor.
     * @param array<string, mixed> $data
     * @param string|null $id Customer to edit.
     * @param array<string, mixed> $options Unused today; kept for the shared writer signature.
     * @return ShopCustomer
     * @throws \Illuminate\Validation\ValidationException
     */
    public function save(WriteContext $ctx, array $data, ?string $id = null, array $options = []): ShopCustomer
    {
        $current = $id !== null ? $this->ownedOrFail(ShopCustomer::class, $id, $ctx) : null;
        [$data, $hash] = $this->prepare($ctx, $data, $current);
        [$mapping, $errors] = $this->mapAndCheck($data, $hash, $current);
        if ($errors !== []) {
            $this->fail($errors);
        }

        return $this->transaction(function () use ($ctx, $mapping, $hash, $current): ShopCustomer {
            if ($current !== null) {
                $customer = ShopCustomer::updateInfo($mapping['dataUpdate'], (string) $current->id);
            } else {
                $insert = $mapping['dataInsert'];
                // WHY: createCustomer does not set store_id; the writer's store is authoritative.
                $insert['store_id'] = $ctx->storeId;
                $customer = ShopCustomer::createCustomer($insert);
                if ($ctx->actor === WriteContext::ACTOR_ADMIN && function_exists('gp247_customer_created_by_admin')) {
                    gp247_customer_created_by_admin($customer);
                }
            }
            if ($hash !== null) {
                // WHY: the hash was made by the source system; storing it as-is lets the
                // customer keep their password (no re-hash, no plain text ever seen).
                $customer->forceFill(['password' => $hash])->save();
            }

            return $customer;
        });
    }

    /**
     * Check a payload without writing.
     *
     * @param WriteContext $ctx
     * @param array<string, mixed> $data
     * @param string|null $id
     * @return array<string, array<int, string>>
     */
    public function validate(WriteContext $ctx, array $data, ?string $id = null): array
    {
        $current = $id !== null ? ShopCustomer::find($id) : null;
        if ($id !== null && ($current === null || (string) $current->store_id !== $ctx->storeId)) {
            return ['store_id' => [(string) gp247_language_render('admin.store.related_wrong_store')]];
        }
        [$data, $hash] = $this->prepare($ctx, $data, $current);

        return $this->mapAndCheck($data, $hash, $current)[1];
    }

    /**
     * Whether $hash is a bcrypt / argon2 hash an import may keep.
     *
     * @param string $hash
     * @return bool
     */
    public static function acceptsHash(string $hash): bool
    {
        return in_array(password_get_info($hash)['algoName'] ?? 'unknown', self::ACCEPTED_HASHES, true);
    }

    /**
     * Fill absent fields from the stored customer (edit) and give an import a random
     * password when it carries none.
     *
     * @param WriteContext $ctx
     * @param array<string, mixed> $data
     * @param ShopCustomer|null $current
     * @return array{0: array<string, mixed>, 1: string|null} [data, hash to keep]
     */
    private function prepare(WriteContext $ctx, array $data, ?ShopCustomer $current): array
    {
        $hash = array_key_exists('password_hash', $data) && (string) $data['password_hash'] !== '' ? (string) $data['password_hash'] : null;
        unset($data['password_hash']);

        if ($current !== null) {
            foreach (self::FIELDS as $field) {
                if (!array_key_exists($field, $data)) {
                    $data[$field] = (string) ($current->{$field} ?? '');
                }
            }
            $data['status'] = array_key_exists('status', $data) ? (empty($data['status']) ? 0 : 1) : (int) $current->status;
            $data['id'] = (string) $current->id;
        } elseif ($ctx->actor === WriteContext::ACTOR_IMPORT && (string) ($data['password'] ?? '') === '') {
            // WHY: an imported customer without a usable hash must not be able to sign in
            // with a guessable password — a long random one forces a password reset.
            $random = Str::random(48);
            $data['password'] = $random;
            $data['password_confirmation'] = $random;
        }
        $data['password'] = (string) ($data['password'] ?? '');
        $data['password_confirmation'] = (string) ($data['password_confirmation'] ?? '');
        $data['store_id'] = $ctx->storeId;

        return [$data, $hash];
    }

    /**
     * Run the brownfield mapping + its validator.
     *
     * @param array<string, mixed> $data
     * @param string|null $hash
     * @param ShopCustomer|null $current
     * @return array{0: array<string, mixed>, 1: array<string, array<int, string>>}
     */
    private function mapAndCheck(array $data, ?string $hash, ?ShopCustomer $current): array
    {
        $mapping = $current !== null ? gp247_customer_data_edit_mapping($data) : gp247_customer_data_insert_mapping($data);
        $errors = $this->check($data, $mapping['validate'], $mapping['messages']);
        if ($hash !== null && !self::acceptsHash($hash)) {
            $errors['password_hash'] = [(string) gp247_language_render('validation.in', ['attribute' => gp247_language_render('customer.password')])];
        }

        return [$mapping, $errors];
    }
}
