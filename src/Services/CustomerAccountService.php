<?php

namespace GP247\Shop\Services;

use GP247\Core\Api\Support\ApiSessions;
use GP247\Shop\Models\ShopCustomer;
use GP247\Shop\Models\ShopCustomerAddress;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Customer account rules and writes shared by the storefront forms and REST API v2:
 * register, profile, password, address book. Both channels call this class, so the
 * per-store `customer_*` settings, custom fields, captcha and the "customer created"
 * events apply identically; changing or resetting a password also signs the customer
 * out of every API client.
 *
 * Fields a customer must not set on themselves (`status`, `id`, and `password` outside
 * the password flow) are dropped before the shared mapping helpers see the input.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-CMP-api-customer-register, US-CMP-api-customer-profile, US-CMP-api-customer-address, US-LW-password-change-revokes-api-sessions
 * @aidlc-adr compat-foundation_api-token-rotation
 */
class CustomerAccountService
{
    /** Input keys never taken from the customer on profile and register. */
    private const PROTECTED_KEYS = ['id', 'status', 'store_id', 'email_verified_at', 'remember_token', 'provider', 'provider_id', 'address_id'];

    /**
     * Validator of the registration form (incl. custom fields and, when enabled for
     * the `register` page, the captcha).
     *
     * @param array $data Raw input.
     * @return ValidatorContract
     */
    public function registerValidator(array $data): ValidatorContract
    {
        $data = $this->withoutProtected($data);
        $mapping = gp247_customer_data_insert_mapping($data);

        [$data, $rules] = $this->withCaptcha($data, $mapping['validate'], 'register');

        return Validator::make($data, $rules, $mapping['messages']);
    }

    /**
     * Create the customer and run the "created by client" side effects (welcome and
     * verification mail). Input must have passed registerValidator().
     *
     * @param array $data
     * @return ShopCustomer
     */
    public function register(array $data): ShopCustomer
    {
        $data = $this->withoutProtected($data);
        $data['country'] = strtoupper($data['country'] ?? '');
        $customer = ShopCustomer::createCustomer(gp247_customer_data_insert_mapping($data)['dataInsert']);
        gp247_customer_created_by_client($customer);

        return $customer;
    }

    /**
     * Validator of the profile form of $customer.
     *
     * @param ShopCustomer $customer
     * @param array        $data
     * @return ValidatorContract
     */
    public function profileValidator(ShopCustomer $customer, array $data): ValidatorContract
    {
        $data = $this->profileInput($customer, $data);
        $mapping = gp247_customer_data_edit_mapping($data);

        return Validator::make($data, $mapping['validate'], $mapping['messages']);
    }

    /**
     * Update the profile of $customer. Input must have passed profileValidator().
     *
     * @param ShopCustomer $customer
     * @param array        $data
     * @return ShopCustomer
     */
    public function updateProfile(ShopCustomer $customer, array $data): ShopCustomer
    {
        $mapping = gp247_customer_data_edit_mapping($this->profileInput($customer, $data));
        ShopCustomer::updateInfo($mapping['dataUpdate'], $customer->id);

        return $customer->fresh();
    }

    /**
     * Rules of a new password (per-store strength settings).
     *
     * @return array<string, mixed>
     */
    public function passwordRules(): array
    {
        return [
            'password_old' => 'required',
            'password'     => gp247_customer_validate_password()['password_confirm'],
        ];
    }

    /**
     * Change the password of $customer after checking the old one, then revoke every
     * API credential except $keepTokenId (the API session asking for the change).
     *
     * @param ShopCustomer $customer
     * @param string       $old
     * @param string       $new
     * @param int|null     $keepTokenId
     * @return void
     * @throws ValidationException When the old password is wrong.
     */
    public function changePassword(ShopCustomer $customer, string $old, string $new, ?int $keepTokenId = null): void
    {
        if (!Hash::check($old, $customer->password)) {
            throw ValidationException::withMessages([
                'password_old' => gp247_language_render('customer.password_old_notcorrect'),
            ]);
        }
        $customer->password = bcrypt($new);
        $customer->save();
        ApiSessions::revokeAll($customer, $keepTokenId);
    }

    /**
     * Set a new password through the "forgot password" flow and revoke every API
     * credential. Signing the customer in stays the caller's choice (web: only when
     * active; API: never).
     *
     * @param ShopCustomer $customer
     * @param string       $new
     * @return void
     */
    public function resetPassword(ShopCustomer $customer, string $new): void
    {
        $customer->password = Hash::make($new);
        $customer->setRememberToken(Str::random(60));
        $customer->save();
        event(new \Illuminate\Auth\Events\PasswordReset($customer));
        ApiSessions::revokeAll($customer);
    }

    /**
     * Validator of an address of the address book.
     *
     * @param array $data
     * @return ValidatorContract
     */
    public function addressValidator(array $data): ValidatorContract
    {
        $mapping = gp247_customer_address_mapping($data);

        return Validator::make($mapping['dataAddress'], $mapping['validate'], $mapping['messages']);
    }

    /**
     * Add an address to the book of $customer; `default` makes it the default address.
     *
     * @param ShopCustomer $customer
     * @param array        $data Input that passed addressValidator().
     * @return ShopCustomerAddress
     */
    public function addAddress(ShopCustomer $customer, array $data): ShopCustomerAddress
    {
        $address = $customer->addresses()->save(
            new ShopCustomerAddress(gp247_clean(gp247_customer_address_mapping($data)['dataAddress']))
        );
        $this->applyDefault($customer, $address, $data);

        return $address;
    }

    /**
     * Update an address of $customer.
     *
     * @param ShopCustomer        $customer
     * @param ShopCustomerAddress $address Address already scoped to $customer.
     * @param array               $data    Input that passed addressValidator().
     * @return ShopCustomerAddress
     */
    public function updateAddress(ShopCustomer $customer, ShopCustomerAddress $address, array $data): ShopCustomerAddress
    {
        $address->update(gp247_clean(gp247_customer_address_mapping($data)['dataAddress']));
        $this->applyDefault($customer, $address, $data);

        return $address->fresh();
    }

    /**
     * Address $id of $customer, or null (another customer's id is "not found").
     *
     * @param ShopCustomer $customer
     * @param string       $id
     * @return ShopCustomerAddress|null
     */
    public function findAddress(ShopCustomer $customer, string $id): ?ShopCustomerAddress
    {
        return (new ShopCustomerAddress)->where('customer_id', $customer->id)->where('id', $id)->first();
    }

    /**
     * Delete an address of $customer.
     *
     * @param ShopCustomer        $customer
     * @param ShopCustomerAddress $address
     * @param bool                $keepDefault Refuse to delete the default address (API).
     * @return void
     * @throws ValidationException When $keepDefault and $address is the default one.
     */
    public function deleteAddress(ShopCustomer $customer, ShopCustomerAddress $address, bool $keepDefault = false): void
    {
        if ($keepDefault && (string) $customer->address_id === (string) $address->id) {
            throw ValidationException::withMessages([
                'address' => 'The default address cannot be deleted; set another address as default first',
            ]);
        }
        $address->delete();
    }

    /**
     * Profile input: the customer's own id for the e-mail unique rule, without the
     * protected keys and without the password (changed through changePassword() only).
     *
     * @param ShopCustomer $customer
     * @param array        $data
     * @return array
     */
    private function profileInput(ShopCustomer $customer, array $data): array
    {
        $data = $this->withoutProtected($data);
        unset($data['password'], $data['password_confirmation']);
        // WHY: gp247_customer_data_edit_mapping() builds the e-mail unique-ignore-self
        // rule from `id` and 500s without it.
        $data['id'] = $customer->id;

        return $data;
    }

    /**
     * $data without the keys a customer must not set on themselves.
     *
     * @param array $data
     * @return array
     */
    private function withoutProtected(array $data): array
    {
        return array_diff_key($data, array_flip(self::PROTECTED_KEYS));
    }

    /**
     * Add the captcha rule when the site enabled captcha for $page (as the web forms do).
     *
     * @param array  $data
     * @param array  $rules
     * @param string $page Captcha page key (register, forgot).
     * @return array{0: array, 1: array} Data and rules to validate.
     */
    private function withCaptcha(array $data, array $rules, string $page): array
    {
        $captcha = gp247_captcha_method();
        if ($captcha && in_array($page, gp247_captcha_page())) {
            $data['captcha_field'] = $data[$captcha->getField()] ?? '';
            $rules['captcha_field'] = ['required', 'string', new \GP247\Core\Rules\CaptchaRule];
        }
        return [$data, $rules];
    }

    /**
     * Captcha-aware validator for the "forgot password" request.
     *
     * @param array $data
     * @return ValidatorContract
     */
    public function forgotValidator(array $data): ValidatorContract
    {
        [$data, $rules] = $this->withCaptcha($data, ['email' => 'required|string|email'], 'forgot');

        return Validator::make($data, $rules);
    }

    /**
     * Make $address the default one of $customer when $data['default'] is truthy.
     *
     * @param ShopCustomer        $customer
     * @param ShopCustomerAddress $address
     * @param array               $data
     * @return void
     */
    private function applyDefault(ShopCustomer $customer, ShopCustomerAddress $address, array $data): void
    {
        if (!empty($data['default'])) {
            $customer->forceFill(['address_id' => $address->id])->save();
        }
    }
}
