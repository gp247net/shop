<?php
use GP247\Shop\Models\ShopCustomer;
use GP247\Core\Models\AdminCustomField;
use GP247\Core\Models\AdminCountry;


if (!function_exists('customer') && !in_array('customer', config('gp247_functions_except', []))) {
    /**
     * Customer login information
     */
    function customer()
    {
        return auth()->guard('customer');
    }
}

/**
 * Send email reset password
 */
if (!function_exists('gp247_customer_sendmail_reset_notification') && !in_array('gp247_customer_sendmail_reset_notification', config('gp247_functions_except', []))) {
    function gp247_customer_sendmail_reset_notification(string $token, string $emailReset)
    {
            $url = gp247_route_front('customer.password_reset', ['token' => $token]);
            $dataView = [
                'title' => gp247_language_render('email.forgot_password.title'),
                'reason_sendmail' => gp247_language_render('email.forgot_password.reason_sendmail'),
                'note_sendmail' => gp247_language_render('email.forgot_password.note_sendmail', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]),
                'note_access_link' => gp247_language_render('email.forgot_password.note_access_link', ['reset_button' => gp247_language_render('email.forgot_password.reset_button'), 'url' => $url]),
                'reset_link' => $url,
                'reset_button' => gp247_language_render('email.forgot_password.reset_button'),
            ];
    
            $config = [
                'to' => $emailReset,
                'subject' => gp247_language_render('email.forgot_password.reset_button'),
            ];
            $subPath = 'email.forgot_password';
            $view = gp247_shop_process_view('GP247TemplatePath::'.gp247_store_info('template'),$subPath);
            gp247_mail_send($view, $dataView, $config, []);
    }
}


/**
 * Send email verify
 */
if (!function_exists('gp247_customer_sendmail_verify') && !in_array('gp247_customer_sendmail_verify', config('gp247_functions_except', []))) {
    function gp247_customer_sendmail_verify(string $emailVerify, string $userId)
    {

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'customer.verify_process',
            \Carbon\Carbon::now()->addMinutes(config('auth.verification', 60)),
            [
                'id' => $userId,
                'token' => sha1($emailVerify),
            ]
        );

        $dataView = [
            'title' => gp247_language_render('email.verification_content.title'),
            'reason_sendmail' => gp247_language_render('email.verification_content.reason_sendmail'),
            'note_sendmail' => gp247_language_render('email.verification_content.note_sendmail', ['count' => config('auth.verification')]),
            'note_access_link' => gp247_language_render('email.verification_content.note_access_link', ['reset_button' => gp247_language_render('customer.verify_email.button_verify'), 'url' => $url]),
            'url_verify' => $url,
            'button' => gp247_language_render('customer.verify_email.button_verify'),
        ];

        $config = [
            'to' => $emailVerify,
            'subject' => gp247_language_render('customer.verify_email.button_verify'),
        ];

        $subPath = 'email.customer_verify';
        $view = gp247_shop_process_view('GP247TemplatePath::'.gp247_store_info('template'),$subPath);
        gp247_mail_send($view, $dataView, $config, $dataAtt = []);
        return true;
    }
}

/**
 * Send email welcome
 */
if (!function_exists('gp247_customer_sendmail_welcome') && !in_array('gp247_customer_sendmail_welcome', config('gp247_functions_except', []))) {
    function gp247_customer_sendmail_welcome(ShopCustomer $customer)
    {
        if (gp247_config('welcome_customer')) {
                $dataView = [
                    'title' => gp247_language_render('email.welcome_customer.title'),
                    'first_name' => $customer['first_name'],
                    'last_name' => $customer['last_name'],
                ];
                $config = [
                    'to' => $customer['email'],
                    'subject' => gp247_language_render('email.welcome_customer.title'),
                ];
                $subPath = 'email.welcome_customer';
                $view = gp247_shop_process_view('GP247TemplatePath::'.gp247_store_info('template'),$subPath);
                gp247_mail_send($view, $dataView, $config, $dataAtt = []);      
        }
    }
}

/**
 * Mapping data address of customer
 *
 * @param   [type]  $dataRaw  [$dataRaw description]
 *
 * @return  [array]              [return description]
 */
if (!function_exists('gp247_customer_address_mapping') && !in_array('gp247_customer_address_mapping', config('gp247_functions_except', []))) {
    function gp247_customer_address_mapping(array $dataRaw)
    {
        $dataAddress = [
            'first_name' => $dataRaw['first_name'] ?? '',
            'address1' => $dataRaw['address1'] ?? '',
        ];
        $validate = [
            'first_name' => config('validation.customer.first_name', 'required|string|max:100'),
            'address1' => config('validation.customer.address1_required', 'required|string|max:100'),
        ];
        if (gp247_config('customer_lastname')) {
            $validate['last_name'] = config('validation.customer.last_name_required', 'required|string|max:100');
            $dataAddress['last_name'] = $dataRaw['last_name']??'';
        }
        if (gp247_config('customer_address2')) {
            $validate['address2'] = config('validation.customer.address2_required', 'required|string|max:100');
            $dataAddress['address2'] = $dataRaw['address2']??'';
        }
        if (gp247_config('customer_address3')) {
            $validate['address3'] = config('validation.customer.address3_required', 'required|string|max:100');
            $dataAddress['address3'] = $dataRaw['address3']??'';
        }
        if (gp247_config('customer_phone')) {
            $validate['phone'] = config('validation.customer.phone_required', 'required|regex:/^0[^0][0-9\-]{6,12}$/');
            $dataAddress['phone'] = $dataRaw['phone']??'';
        }
        if (gp247_config('customer_country')) {
            $validate['country'] = config('validation.customer.country_required', 'required|string|min:2');
            $dataAddress['country'] = $dataRaw['country']??'';
        }
        if (gp247_config('customer_postcode')) {
            $validate['postcode'] = config('validation.customer.postcode_null', 'nullable|min:5');
            $dataAddress['postcode'] = $dataRaw['postcode']??'';
        }

        $messages = [
            'last_name.required'  => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.last_name')]),
            'first_name.required' => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.first_name')]),
            'address1.required'   => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.address1')]),
            'address2.required'   => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.address2')]),
            'address3.required'   => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.address3')]),
            'phone.required'      => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.phone')]),
            'country.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.country')]),
            'postcode.required'   => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.postcode')]),
            'phone.regex'         => gp247_language_render('customer.phone_regex'),
            'postcode.min'        => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('customer.postcode')]),
            'country.min'         => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('customer.country')]),
            'first_name.max'      => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.first_name')]),
            'address1.max'        => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.address1')]),
            'address2.max'        => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.address2')]),
            'address3.max'        => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.address3')]),
            'last_name.max'       => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.last_name')]),
        ];

        $dataMap = [
            'validate' => $validate,
            'messages' => $messages,
            'dataAddress' => $dataAddress
        ];
        return $dataMap;
    }
}


/**
 * Mapping data customer before insert
 *
 * @param   [array]  $dataRaw  [$dataRaw description]
 *
 * @return  [array]              [return description]
 */
if (!function_exists('gp247_customer_data_insert_mapping') && !in_array('gp247_customer_data_insert_mapping', config('gp247_functions_except', []))) {
    function gp247_customer_data_insert_mapping(array $dataRaw)
    {
        $dataInsert = [
            'first_name' => $dataRaw['first_name'] ?? '',
            'email' => $dataRaw['email'],
            'password' => bcrypt($dataRaw['password']),
        ];

        $validate = [
            'first_name' => config('validation.customer.first_name', 'required|string|max:100'),
            'email' => config('validation.customer.email', 'required|string|email|max:255').'|unique:"'.ShopCustomer::class.'",email',
            'password' => gp247_customer_validate_password()['password_confirm'],
        ];

        if (isset($dataRaw['status'])) {
            $dataInsert['status']  = $dataRaw['status'];
        }

        //Custom fields
        $customFields = (new AdminCustomField)->getCustomField($type = 'shop_customer');
        if ($customFields) {
            foreach ($customFields as $field) {
                if ($field->required) {
                    $validate['fields.'.$field->code] = 'required';
                }
            }
        }

        if (gp247_config('customer_lastname')) {
            if (gp247_config('customer_lastname_required')) {
                $validate['last_name'] = config('validation.customer.last_name_required', 'required|string|max:100');
            } else {
                $validate['last_name'] = config('validation.customer.last_name_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['last_name'])) {
                $dataInsert['last_name'] = $dataRaw['last_name'];
            }
        }

        if (gp247_config('customer_address1')) {
            if (gp247_config('customer_address1_required')) {
                $validate['address1'] = config('validation.customer.address1_required', 'required|string|max:100');
            } else {
                $validate['address1'] = config('validation.customer.address1_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['address1'])) {
                $dataInsert['address1'] = $dataRaw['address1'];
            }
        }

        if (gp247_config('customer_address2')) {
            if (gp247_config('customer_address2_required')) {
                $validate['address2'] = config('validation.customer.address2_required', 'required|string|max:100');
            } else {
                $validate['address2'] = config('validation.customer.address2_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['address2'])) {
                $dataInsert['address2'] = $dataRaw['address2'];
            }
        }

        if (gp247_config('customer_address3')) {
            if (gp247_config('customer_address3_required')) {
                $validate['address3'] = config('validation.customer.address3_required', 'required|string|max:100');
            } else {
                $validate['address3'] = config('validation.customer.address3_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['address3'])) {
                $dataInsert['address3'] = $dataRaw['address3'];
            }
        }


        if (gp247_config('customer_phone')) {
            if (gp247_config('customer_phone_required')) {
                $validate['phone'] = config('validation.customer.phone_required', 'regex:/^0[^0][0-9\-]{6,12}$/');
            } else {
                $validate['phone'] = config('validation.customer.phone_null', 'nullable|regex:/^0[^0][0-9\-]{6,12}$/');
            }
            if (!empty($dataRaw['phone'])) {
                $dataInsert['phone'] = $dataRaw['phone'];
            }
        }

        if (gp247_config('customer_country')) {
            $arraycountry = (new AdminCountry)->pluck('code')->toArray();
            if (gp247_config('customer_country_required')) {
                $validate['country'] = config('validation.customer.country_required', 'required|string|min:2').'|in:'. implode(',', $arraycountry);
            } else {
                $validate['country'] = config('validation.customer.country_null', 'nullable|string|min:2').'|in:'. implode(',', $arraycountry);
            }
            if (!empty($dataRaw['country'])) {
                $dataInsert['country'] = $dataRaw['country'];
            }
        }

        if (gp247_config('customer_postcode')) {
            if (gp247_config('customer_postcode_required')) {
                $validate['postcode'] = config('validation.customer.postcode_required', 'required|min:5');
            } else {
                $validate['postcode'] = config('validation.customer.postcode_null', 'nullable|min:5');
            }
            if (!empty($dataRaw['postcode'])) {
                $dataInsert['postcode'] = $dataRaw['postcode'];
            }
        }

        if (gp247_config('customer_company')) {
            if (gp247_config('customer_company_required')) {
                $validate['company'] = config('validation.customer.company_required', 'required|string|max:100');
            } else {
                $validate['company'] = config('validation.customer.company_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['company'])) {
                $dataInsert['company'] = $dataRaw['company'];
            }
        }

        if (gp247_config('customer_sex')) {
            if (gp247_config('customer_sex_required')) {
                $validate['sex'] = config('validation.customer.sex_required', 'required|integer|max:10');
            } else {
                $validate['sex'] = config('validation.customer.sex_null', 'nullable|integer|max:10');
            }
            if (!empty($dataRaw['sex'])) {
                $dataInsert['sex'] = $dataRaw['sex'];
            }
        }

        if (gp247_config('customer_birthday')) {
            if (gp247_config('customer_birthday_required')) {
                $validate['birthday'] = config('validation.customer.birthday_required', 'required|date|date_format:Y-m-d');
            } else {
                $validate['birthday'] = config('validation.customer.birthday_null', 'nullable|date|date_format:Y-m-d');
            }
            if (!empty($dataRaw['birthday'])) {
                $dataInsert['birthday'] = $dataRaw['birthday'];
            }
        }

        if (gp247_config('customer_group')) {
            if (gp247_config('customer_group_required')) {
                $validate['group'] = config('validation.customer.group_required', 'required|integer|max:10');
            } else {
                $validate['group'] = config('validation.customer.group_null', 'nullable|integer|max:10');
            }
            if (!empty($dataRaw['group'])) {
                $dataInsert['group'] = $dataRaw['group'];
            }
        }

        if (gp247_config('customer_name_kana')) {
            if (gp247_config('customer_name_kana_required')) {
                $validate['first_name_kana'] = config('validation.customer.name_kana_required', 'required|string|max:100');
                $validate['last_name_kana'] = config('validation.customer.name_kana_required', 'required|string|max:100');
            } else {
                $validate['first_name_kana'] = config('validation.customer.name_kana_null', 'nullable|string|max:100');
                $validate['last_name_kana'] = config('validation.customer.name_kana_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['first_name_kana'])) {
                $dataInsert['first_name_kana'] = $dataRaw['first_name_kana'];
            }
            if (!empty($dataRaw['last_name_kana'])) {
                $dataInsert['last_name_kana'] = $dataRaw['last_name_kana'];
            }
        }

        if (!empty($dataRaw['fields'])) {
            $dataInsert['fields'] = $dataRaw['fields'];
        }

        $messages = [
            'last_name.required'   => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.last_name')]),
            'first_name.required'  => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.first_name')]),
            'email.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.email')]),
            'password.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.password')]),
            'address1.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.address1')]),
            'address2.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.address2')]),
            'address3.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.address3')]),
            'phone.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.phone')]),
            'country.required'     => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.country')]),
            'postcode.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.postcode')]),
            'company.required'     => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.company')]),
            'sex.required'         => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.sex')]),
            'birthday.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.birthday')]),
            'email.email'          => gp247_language_render('validation.email', ['attribute'=> gp247_language_render('customer.email')]),
            'phone.regex'          => gp247_language_render('customer.phone_regex'),
            'password.confirmed'   => gp247_language_render('validation.confirmed', ['attribute'=> gp247_language_render('customer.password')]),
            'postcode.min'         => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('customer.postcode')]),
            'country.min'          => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('customer.country')]),
            'first_name.max'       => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.first_name')]),
            'email.max'            => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.email')]),
            'address1.max'         => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.address1')]),
            'address2.max'         => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.address2')]),
            'address3.max'         => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.address3')]),
            'last_name.max'        => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.last_name')]),
            'birthday.date'        => gp247_language_render('validation.date', ['attribute'=> gp247_language_render('customer.birthday')]),
            'birthday.date_format' => gp247_language_render('validation.date_format', ['attribute'=> gp247_language_render('customer.birthday')]),
            'password.min' => gp247_language_render('validation.password.min', ['attribute'=> gp247_language_render('customer.password')]),
            'password.max' => gp247_language_render('validation.password.max', ['attribute'=> gp247_language_render('customer.password')]),
            'password.letters' => gp247_language_render('validation.password.letters', ['attribute'=> gp247_language_render('customer.password')]),
            'password.mixed' => gp247_language_render('validation.password.mixed', ['attribute'=> gp247_language_render('customer.password')]),
            'password.numbers' => gp247_language_render('validation.password.numbers', ['attribute'=> gp247_language_render('customer.password')]),
            'password.symbols' => gp247_language_render('validation.password.symbols', ['attribute'=> gp247_language_render('customer.password')]),
        ];
        $dataMap = [
            'validate' => $validate,
            'messages' => $messages,
            'dataInsert' => $dataInsert
        ];
        return $dataMap;
    }
}

/**
 * Mapping data customer before edit
 *
 * @param   [array]  $dataRaw  [$dataRaw description]
 *
 * @return  [array]              [return description]
 */
if (!function_exists('gp247_customer_data_edit_mapping') && !in_array('gp247_customer_data_edit_mapping', config('gp247_functions_except', []))) {
    function gp247_customer_data_edit_mapping(array $dataRaw)
    {
        $dataUpdate = [
            'first_name' => $dataRaw['first_name'],
        ];
        if (isset($dataRaw['status'])) {
            $dataUpdate['status']  = $dataRaw['status'];
        }
        $validate = [
            'first_name' => config('validation.customer.first_name', 'required|string|max:100'),
            'password' => gp247_customer_validate_password()['password_nullable'],
        ];

        //Custom fields
        $customFields = (new AdminCustomField)->getCustomField($type = 'shop_customer');
        if ($customFields) {
            foreach ($customFields as $field) {
                if ($field->required) {
                    $validate['fields.'.$field->code] = 'required';
                }
            }
            $dataUpdate['fields'] = $dataRaw['fields'] ?? [];
        }

        if (!empty($dataRaw['password'])) {
            $dataUpdate['password'] = bcrypt($dataRaw['password']);
        }
        if (!empty($dataRaw['email'])) {
            $dataUpdate['email'] = $dataRaw['email'];
            $validate['email'] = config('validation.customer.email', 'required|string|email|max:255').'|unique:"'.ShopCustomer::class.'",email,'.$dataRaw['id'].',id';
        }
        //Dont update id
        unset($dataRaw['id']);

        if (gp247_config('customer_lastname')) {
            if (gp247_config('customer_lastname_required')) {
                $validate['last_name'] = config('validation.customer.last_name_required', 'required|string|max:100');
            } else {
                $validate['last_name'] = config('validation.customer.last_name_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['last_name'])) {
                $dataUpdate['last_name'] = $dataRaw['last_name'];
            }
        }
        if (gp247_config('customer_address1')) {
            if (gp247_config('customer_address1_required')) {
                $validate['address1'] = config('validation.customer.address1_required', 'required|string|max:100');
            } else {
                $validate['address1'] = config('validation.customer.address1_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['address1'])) {
                $dataUpdate['address1'] = $dataRaw['address1'];
            }
        }

        if (gp247_config('customer_address2')) {
            if (gp247_config('customer_address2_required')) {
                $validate['address2'] = config('validation.customer.address2_required', 'required|string|max:100');
            } else {
                $validate['address2'] = config('validation.customer.address2_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['address2'])) {
                $dataUpdate['address2'] = $dataRaw['address2'];
            }
        }

        if (gp247_config('customer_address3')) {
            if (gp247_config('customer_address3_required')) {
                $validate['address3'] = config('validation.customer.address3_required', 'required|string|max:100');
            } else {
                $validate['address3'] = config('validation.customer.address3_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['address3'])) {
                $dataUpdate['address3'] = $dataRaw['address3'];
            }
        }


        if (gp247_config('customer_phone')) {
            if (gp247_config('customer_phone_required')) {
                $validate['phone'] = config('validation.customer.phone_required', 'regex:/^0[^0][0-9\-]{6,12}$/');
            } else {
                $validate['phone'] = config('validation.customer.phone_null', 'nullable|regex:/^0[^0][0-9\-]{6,12}$/');
            }
            if (!empty($dataRaw['phone'])) {
                $dataUpdate['phone'] = $dataRaw['phone'];
            }
        }

        if (gp247_config('customer_country')) {
            $arraycountry = (new AdminCountry)->pluck('code')->toArray();
            if (gp247_config('customer_country_required')) {
                $validate['country'] = config('validation.customer.country_required', 'required|string|min:2').'|in:'. implode(',', $arraycountry);
            } else {
                $validate['country'] = config('validation.customer.country_null', 'nullable|string|min:2').'|in:'. implode(',', $arraycountry);
            }
            if (!empty($dataRaw['country'])) {
                $dataUpdate['country'] = $dataRaw['country'];
            }
        }

        if (gp247_config('customer_postcode')) {
            if (gp247_config('customer_postcode_required')) {
                $validate['postcode'] = config('validation.customer.postcode_required', 'required|min:5');
            } else {
                $validate['postcode'] = config('validation.customer.postcode_null', 'nullable|min:5');
            }
            if (!empty($dataRaw['postcode'])) {
                $dataUpdate['postcode'] = $dataRaw['postcode'];
            }
        }

        if (gp247_config('customer_company')) {
            if (gp247_config('customer_company_required')) {
                $validate['company'] = config('validation.customer.company_required', 'required|string|max:100');
            } else {
                $validate['company'] = config('validation.customer.company_null', 'nullable|string|max:100');
            }
            if (!empty($dataRaw['company'])) {
                $dataUpdate['company'] = $dataRaw['company'];
            }
        }

        if (gp247_config('customer_sex')) {
            if (gp247_config('customer_sex_required')) {
                $validate['sex'] = config('validation.customer.sex_required', 'required|integer|max:10');
            } else {
                $validate['sex'] = config('validation.customer.sex_null', 'nullable|integer|max:10');
            }
            if (!empty($dataRaw['sex'])) {
                $dataUpdate['sex'] = $dataRaw['sex'];
            }
        }

        if (gp247_config('customer_birthday')) {
            if (gp247_config('customer_birthday_required')) {
                $validate['birthday'] = config('validation.customer.birthday_required', 'required|date|date_format:Y-m-d');
            } else {
                $validate['birthday'] = config('validation.customer.birthday_null', 'nullable|date|date_format:Y-m-d');
            }
            if (!empty($dataRaw['birthday'])) {
                $dataUpdate['birthday'] = $dataRaw['birthday'];
            }
        }

        if (gp247_config('customer_group')) {
            if (gp247_config('customer_group_required')) {
                $validate['group'] = config('validation.customer.group_required', 'required|integer|max:10');
            } else {
                $validate['group'] = config('validation.customer.group_null', 'nullable|integer|max:10');
            }
            if (!empty($dataRaw['group'])) {
                $dataUpdate['group'] = $dataRaw['group'];
            }
        }

        if (gp247_config('customer_name_kana')) {
            if (gp247_config('customer_name_kana_required')) {
                $validate['first_name_kana'] = config('validation.customer.name_kana_required', 'required|string|max:100');
                $validate['last_name_kana'] = config('validation.customer.name_kana_required', 'required|string|max:100');
            } else {
                $validate['first_name_kana'] = config('validation.customer.name_kana_null', 'nullable|string|max:100');
                $validate['last_name_kana'] = config('validation.customer.name_kana_null', 'nullable|string|max:100');
            }
            $dataUpdate['first_name_kana'] = $dataRaw['first_name_kana']?? '';
            $dataUpdate['last_name_kana'] = $dataRaw['last_name_kana']?? '';
        }

        $messages = [
            'last_name.required'   => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.last_name')]),
            'first_name.required'  => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.first_name')]),
            'email.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.email')]),
            'password.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.password')]),
            'address1.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.address1')]),
            'address2.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.address2')]),
            'address3.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.address3')]),
            'phone.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.phone')]),
            'country.required'     => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.country')]),
            'postcode.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.postcode')]),
            'company.required'     => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.company')]),
            'sex.required'         => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.sex')]),
            'birthday.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.birthday')]),
            'email.email'          => gp247_language_render('validation.email', ['attribute'=> gp247_language_render('customer.email')]),
            'phone.regex'          => gp247_language_render('customer.phone_regex'),
            'password.confirmed'   => gp247_language_render('validation.confirmed', ['attribute'=> gp247_language_render('customer.password')]),
            'postcode.min'         => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('customer.postcode')]),
            'country.min'          => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('customer.country')]),
            'first_name.max'       => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.first_name')]),
            'email.max'            => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.email')]),
            'address1.max'         => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.address1')]),
            'address2.max'         => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.address2')]),
            'address3.max'         => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.address3')]),
            'last_name.max'        => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('customer.last_name')]),
            'birthday.date'        => gp247_language_render('validation.date', ['attribute'=> gp247_language_render('customer.birthday')]),
            'birthday.date_format' => gp247_language_render('validation.date_format', ['attribute'=> gp247_language_render('customer.birthday')]),
            'password.min' => gp247_language_render('validation.password.min', ['attribute'=> gp247_language_render('customer.password')]),
            'password.max' => gp247_language_render('validation.password.max', ['attribute'=> gp247_language_render('customer.password')]),
            'password.letters' => gp247_language_render('validation.password.letters', ['attribute'=> gp247_language_render('customer.password')]),
            'password.mixedCase' => gp247_language_render('validation.password.mixedCase', ['attribute'=> gp247_language_render('customer.password')]),
            'password.numbers' => gp247_language_render('validation.password.numbers', ['attribute'=> gp247_language_render('customer.password')]),
            'password.symbols' => gp247_language_render('validation.password.symbols', ['attribute'=> gp247_language_render('customer.password')]),
        ];
        $dataMap = [
            'validate' => $validate,
            'messages' => $messages,
            'dataUpdate' => $dataUpdate
        ];
        return $dataMap;
    }
}

/**
 * Process after customer created by client
 */
if (!function_exists('gp247_customer_created_by_client') && !in_array('gp247_customer_created_by_client', config('gp247_functions_except', []))) {
    function gp247_customer_created_by_client(ShopCustomer $user)
    {
        //Send email welcome
        gp247_customer_sendmail_welcome($user);

        //Send email verify
        $user->sendEmailVerify();
    }
}

/**
 * Process after customer created by admin
 */
if (!function_exists('gp247_customer_created_by_admin') && !in_array('gp247_customer_created_by_admin', config('gp247_functions_except', []))) {
    function gp247_customer_created_by_admin(ShopCustomer $user)
    {
        //
    }
}

/**
 * Process validate password
 */
if (!function_exists('gp247_customer_validate_password') && !in_array('gp247_customer_validate_password', config('gp247_functions_except', []))) {
    function gp247_customer_validate_password()
    {
        $passwordValidate = \Illuminate\Validation\Rules\Password::min(gp247_config('customer_password_min', null, 6));
        if (gp247_config('customer_password_letter')) {
            // Require at least one letter...
            $passwordValidate = $passwordValidate->letters();
        }
        if (gp247_config('customer_password_mixedcase')) {
            // Require at least one uppercase and one lowercase letter...
            $passwordValidate = $passwordValidate->mixedCase();
        }
        if (gp247_config('customer_password_number')) {
            // Require at least one number...
            $passwordValidate = $passwordValidate->numbers();
        }
        if (gp247_config('customer_password_symbol')) {
            // Require at least one symbol...
            $passwordValidate = $passwordValidate->symbols();
        }
        return [
            'password'          => ['required','string', $passwordValidate, 'max: '.gp247_config('customer_password_max', null, 16)],
            'password_confirm'  => ['required','string', $passwordValidate, 'max: '.gp247_config('customer_password_max', null, 16), 'confirmed'],
            'password_nullable' => ['nullable','string', $passwordValidate, 'max: '.gp247_config('customer_password_max', null, 16)],
        ];
    }
}
