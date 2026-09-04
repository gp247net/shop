<?php
use GP247\Core\Models\AdminCountry;

/**
 * Function process after order success
 */
if (!function_exists('gp247_order_process_after_success') && !in_array('gp247_order_process_after_success', config('gp247_functions_except', []))) {
    function gp247_order_process_after_success(string $orderID = "")
    {
        if ((gp247_config('order_success_to_admin') || gp247_config('order_success_to_customer')) && gp247_config('email_action_mode')) {
            $data = \GP247\Shop\Models\ShopOrder::with('details')->find($orderID)->toArray();
            $orderDetail = [];
                foreach ($data['details'] as $key => $detail) {
                    $product = (new \GP247\Shop\Models\ShopProduct)->getDetail($detail['product_id']);
                    $pathDownload = $product->downloadPath->path ?? '';
                    $linkDownload = '';
                    $nameProduct = $detail['name'];
                    if ($product && $pathDownload && $product->product_type == GP247_PRODUCT_TYPE_DOWNLOAD) {
                        $linkDownload = $pathDownload;
                    }
                    $orderDetail[] = [
                        'sku' => $detail['sku'],
                        'name' => $nameProduct,
                        'linkDownload' => $linkDownload,
                        'price' => gp247_currency_render($detail['price'], '', '', '', false),
                        'qty' => number_format($detail['qty']),
                        'total' => gp247_currency_render($detail['total_price'], '', '', '', false),
                    ];
                }
              

                // Shared mail body — admin and customer receive the same order summary,
                // so build it once instead of duplicating the 12 fields per recipient.
                $dataView = [
                    'orderID' => $orderID,
                    'toname' => $data['first_name'].' '.$data['last_name'],
                    // Canonical address order: city -> district -> address1/2/3.
                    // array_filter drops empty parts so an install with city/district
                    // OFF renders the same "address1 address2 address3" string as before.
                    'address' => implode(' ', array_filter([$data['city'] ?? '', $data['district'] ?? '', $data['address1'], $data['address2'], $data['address3']])),
                    'phone' => $data['phone'],
                    'comment' => $data['comment'],
                    'currency' => $data['currency'],
                    'orderDetail' => $orderDetail,
                    'subtotal' => gp247_currency_render($data['subtotal'], '', '', '', false),
                    'shipping' => gp247_currency_render($data['shipping'], '', '', '', false),
                    'discount' => gp247_currency_render($data['discount'], '', '', '', false),
                    'otherFee' => gp247_currency_render($data['other_fee'], '', '', '', false),
                    'total' => gp247_currency_render($data['total'], '', '', '', false),
                ];

                // Send mail order success to admin
                if (gp247_config('order_success_to_admin')) {
                    $config = [
                        'to' => gp247_store_info('email'),
                        'subject' => gp247_language_render('email.order.email_subject_to_admin', ['order_id' => $orderID]),
                    ];
                    gp247_mail_send(gp247_shop_mail_view('email.shop_order_success_to_admin'), $dataView, $config, []);
                }

                // Send mail order success to customer
                if (gp247_config('order_success_to_customer') && $data['email']) {
                    $config = [
                        'to' => $data['email'],
                        'replyTo' => gp247_store_info('email'),
                        'subject' => gp247_language_render('email.order.email_subject_customer', ['order_id' => $orderID]),
                    ];
                    gp247_mail_send(gp247_shop_mail_view('email.shop_order_success_to_customer'), $dataView, $config, []);
                }
        }
    }
}

/**
 * Function process mapping validate order
 */
if (!function_exists('gp247_order_mapping_validate') && !in_array('gp247_order_mapping_validate', config('gp247_functions_except', []))) {
    function gp247_order_mapping_validate():array
    {
        $validate = [
            'first_name'     => config('validation.customer.first_name', 'required|string|max:100'),
            'email'          => config('validation.customer.email', 'required|string|email|max:255'),
        ];
        //check shipping
        if (gp247_config('use_shipping')) {
            $validate['shippingMethod'] = 'required';
        }
        //check payment
        if (gp247_config('use_payment')) {
            $validate['paymentMethod'] = 'required';
        }

        if (gp247_config('customer_lastname')) {
            if (gp247_config('customer_lastname_required')) {
                $validate['last_name'] = config('validation.customer.last_name_required', 'required|string|max:100');
            } else {
                $validate['last_name'] = config('validation.customer.last_name_null', 'nullable|string|max:100');
            }
        }
        // City / district precede address1 to match the canonical address order
        // (city -> district -> address1/2/3). Both default OFF, so an install that
        // never enables them validates exactly as before.
        if (gp247_config('customer_city')) {
            if (gp247_config('customer_city_required')) {
                $validate['city'] = config('validation.customer.city_required', 'required|string|max:100');
            } else {
                $validate['city'] = config('validation.customer.city_null', 'nullable|string|max:100');
            }
        }

        if (gp247_config('customer_district')) {
            if (gp247_config('customer_district_required')) {
                $validate['district'] = config('validation.customer.district_required', 'required|string|max:100');
            } else {
                $validate['district'] = config('validation.customer.district_null', 'nullable|string|max:100');
            }
        }

        if (gp247_config('customer_address1')) {
            if (gp247_config('customer_address1_required')) {
                $validate['address1'] = config('validation.customer.address1_required', 'required|string|max:100');
            } else {
                $validate['address1'] = config('validation.customer.address1_null', 'nullable|string|max:100');
            }
        }

        if (gp247_config('customer_address2')) {
            if (gp247_config('customer_address2_required')) {
                $validate['address2'] = config('validation.customer.address2_required', 'required|string|max:100');
            } else {
                $validate['address2'] = config('validation.customer.address2_null', 'nullable|string|max:100');
            }
        }

        if (gp247_config('customer_address3')) {
            if (gp247_config('customer_address3_required')) {
                $validate['address3'] = config('validation.customer.address3_required', 'required|string|max:100');
            } else {
                $validate['address3'] = config('validation.customer.address3_null', 'nullable|string|max:100');
            }
        }

        if (gp247_config('customer_phone')) {
            if (gp247_config('customer_phone_required')) {
                $validate['phone'] = config('validation.customer.phone_required', 'required|regex:/^0[^0][0-9\-]{6,12}$/');
            } else {
                $validate['phone'] = config('validation.customer.phone_null', 'nullable|regex:/^0[^0][0-9\-]{6,12}$/');
            }
        }
        if (gp247_config('customer_country')) {
            $arrayCountry = (new AdminCountry)->pluck('code')->toArray();
            if (gp247_config('customer_country_required')) {
                $validate['country'] = config('validation.customer.country_required', 'required|string|min:2').'|in:'. implode(',', $arrayCountry);
            } else {
                $validate['country'] = config('validation.customer.country_null', 'nullable|string|min:2').'|in:'. implode(',', $arrayCountry);
            }
        }

        if (gp247_config('customer_postcode')) {
            if (gp247_config('customer_postcode_required')) {
                $validate['postcode'] = config('validation.customer.postcode_required', 'required|min:5');
            } else {
                $validate['postcode'] = config('validation.customer.postcode_null', 'nullable|min:5');
            }
        }
        if (gp247_config('customer_company')) {
            if (gp247_config('customer_company_required')) {
                $validate['company'] = config('validation.customer.company_required', 'required|string|max:100');
            } else {
                $validate['company'] = config('validation.customer.company_null', 'nullable|string|max:100');
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
        }

        $messages = [
            'last_name.required'      => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.last_name')]),
            'first_name.required'     => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.first_name')]),
            'email.required'          => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.email')]),
            'city.required'           => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.city')]),
            'district.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.district')]),
            'address1.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.address1')]),
            'address2.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.address2')]),
            'address3.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.address3')]),
            'phone.required'          => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.phone')]),
            'country.required'        => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.country')]),
            'postcode.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.postcode')]),
            'company.required'        => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.company')]),
            'sex.required'            => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.sex')]),
            'birthday.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.birthday')]),
            'email.email'             => gp247_language_render('validation.email', ['attribute'=> gp247_language_render('cart.email')]),
            'phone.regex'             => gp247_language_render('customer.phone_regex'),
            'postcode.min'            => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('cart.postcode')]),
            'country.min'             => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('cart.country')]),
            'first_name.max'          => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.first_name')]),
            'email.max'               => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.email')]),
            'city.max'                => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.city')]),
            'district.max'            => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.district')]),
            'address1.max'            => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.address1')]),
            'address2.max'            => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.address2')]),
            'address3.max'            => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.address3')]),
            'last_name.max'           => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.last_name')]),
            'birthday.date'           => gp247_language_render('validation.date', ['attribute'=> gp247_language_render('cart.birthday')]),
            'birthday.date_format'    => gp247_language_render('validation.date_format', ['attribute'=> gp247_language_render('cart.birthday')]),
            'shippingMethod.required' => gp247_language_render('cart.validation.shippingMethod_required'),
            'paymentMethod.required'  => gp247_language_render('cart.validation.paymentMethod_required'),
        ];

        $dataMap['validate'] = $validate;
        $dataMap['messages'] = $messages;

        return $dataMap;
    }
}
if (!function_exists('gp247_shop_total_method_modules') && !in_array('gp247_shop_total_method_modules', config('gp247_functions_except', []))) {
    /**
     * Active total-method plugins (coupon/discount/point) at checkout, keyed by plugin key.
     *
     * WHY dual-read: the total-method group's configCode was renamed "Total" -> "Promotion"
     * (modification 20260904T225634). Read the new code first and union the legacy "Total"
     * so third-party plugins still declaring "Total" (and installs not yet migrated via
     * gp247:update) keep working; a deprecation warning nudges the migration.
     *
     * Single source for every checkout total-method discovery (CheckoutWizard Livewire +
     * ShopCartController data-prep). NOTE: this is the plugin configCode — unrelated to the
     * order-total LINE code 'total' in shop_order_total.
     *
     * @return array<string, array<string,mixed>> Merged plugin rows (Promotion wins on collision).
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-006
     * @aidlc-adr storefront_checkout-total-method-contract
     */
    function gp247_shop_total_method_modules(): array
    {
        $promotion = gp247_extension_get_via_code(code: 'Promotion');
        $legacy    = gp247_extension_get_via_code(code: 'Total');

        if (!empty($legacy)) {
            logger()->warning('[GP247 checkout] total-method plugin(s) still declare the legacy configCode '
                . '"Total" (' . implode(', ', array_keys($legacy)) . '). It is deprecated since the '
                . 'Total->Promotion rename; run gp247:update or set configCode "Promotion".');
        }

        // Promotion wins on key collision; legacy fills in the rest.
        return $promotion + $legacy;
    }
}
