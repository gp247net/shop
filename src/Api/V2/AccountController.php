<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Core\Api\Support\ApiLoginThrottle;
use GP247\Core\Api\Support\ApiSessions;
use GP247\Core\Api\Support\LoginChallenges;
use GP247\Shop\Api\V2\Resources\ShopResources;
use GP247\Shop\Models\ShopCustomer;
use GP247\Shop\Services\CustomerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Customer account of REST API v2 (api/v2/front): register, forgot/reset password,
 * profile, password change and e-mail verification. Every rule and write goes through
 * CustomerAccountService — the service behind the storefront forms — so both channels
 * apply the same per-store settings, custom fields, captcha and events.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-customer-register, US-CMP-api-customer-password-recovery, US-CMP-api-customer-profile, US-CMP-api-customer-email-verification
 * @aidlc-adr compat-foundation_api-token-rotation
 */
class AccountController extends MemberController
{
    /**
     * Create an account and sign it in (same answer as login, HTTP 201).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate(['with_refresh' => 'boolean', 'device_name' => 'nullable|string|max:100']);
        $service = $this->accounts();
        $service->registerValidator($request->all())->validate();
        $customer = $service->register($request->all());

        $opts = $this->sessionOptions($customer, $request);
        if ($challenge = LoginChallenges::gate($customer, 'customer', $request, $opts)) {
            return $challenge;
        }

        $data = ['customer' => ShopResources::profile($customer->fresh())]
            + ApiSessions::open($customer, 'customer', $request, $opts);
        return response()->json(['data' => $data], 201);
    }

    /**
     * Send the password-reset mail. Always the same answer, whether the e-mail has an
     * account or not, so the endpoint cannot be used to probe accounts.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $this->accounts()->forgotValidator($request->all())->validate();
        try {
            Password::broker('customer_password')->sendResetLink(['email' => (string) $request->input('email')]);
        } catch (\Throwable $e) {
            // WHY: a mail failure must not reveal that the account exists.
            gp247_report($e);
        }

        return response()->json(['data' => ['sent' => true]]);
    }

    /**
     * Set a new password with the token from the reset mail; revokes every API session.
     * Never signs in (the client calls login with the new password).
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|string|email',
            'password' => array_merge(gp247_customer_validate_password()['password'], ['confirmed']),
        ]);
        $email = (string) $request->input('email');
        $subject = ApiLoginThrottle::subject(ShopCustomer::where('email', $email)->value('id'), $email);
        if ($blocked = ApiLoginThrottle::attempt($request, 'customer', $subject)) {
            return $blocked;
        }

        $status = Password::broker('customer_password')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            fn ($customer, $password) => $this->accounts()->resetPassword($customer, $password)
        );
        if ($status !== Password::PASSWORD_RESET) {
            // WHY one message: "no such user" vs "bad token" would reveal registered e-mails.
            throw ValidationException::withMessages(['token' => __(Password::INVALID_TOKEN)]);
        }
        ApiLoginThrottle::succeeded($request, 'customer', $subject);

        return response()->json(['data' => ['reset' => true]]);
    }

    /**
     * Profile of the signed-in customer, with verification state and default address.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request)
    {
        return response()->json(['data' => ShopResources::profile($request->user())]);
    }

    /**
     * Update the profile (only the fields configured for the store).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $customer = $request->user();
        $service = $this->accounts();
        $service->profileValidator($customer, $request->all())->validate();
        $customer = $service->updateProfile($customer, $request->all());

        return response()->json(['data' => ShopResources::profile($customer)]);
    }

    /**
     * Change the password; this session stays signed in, every other one is revoked.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword(Request $request)
    {
        $service = $this->accounts();
        Validator::make($request->all(), $service->passwordRules())->validate();
        $customer = $request->user();
        $subject = ApiLoginThrottle::subject($customer->id, '');
        if ($blocked = ApiLoginThrottle::attempt($request, 'customer', $subject)) {
            return $blocked;
        }

        $service->changePassword(
            $customer,
            (string) $request->input('password_old'),
            (string) $request->input('password'),
            (int) $customer->currentAccessToken()->id
        );
        ApiLoginThrottle::succeeded($request, 'customer', $subject);

        return response()->json(['data' => ['changed' => true]]);
    }

    /**
     * Send the e-mail verification link again when the store requires verification.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resendVerification(Request $request)
    {
        $customer = $request->user();
        $required = (bool) $customer->hasVerifiedEmail();
        if ($required) {
            $customer->sendEmailVerify();
        }

        return response()->json(['data' => ['sent' => $required]]);
    }

    /**
     * @return CustomerAccountService
     */
    protected function accounts(): CustomerAccountService
    {
        return app(CustomerAccountService::class);
    }
}
