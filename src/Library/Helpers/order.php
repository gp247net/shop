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
                    $nameProduct = $detail['name'];
                    if ($product && $pathDownload && $product->tag == GP247_TAG_DOWNLOAD) {
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
              

                // Send mail order success to admin
                if (gp247_config('order_success_to_admin')) {
                    $dataView = [
                        'orderID' => $orderID,
                        'toname' => $data['first_name'].' '.$data['last_name'],
                        'address' => $data['address1'] . ' ' . $data['address2'].' '.$data['address3'],
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
                    $config = [
                        'to' => gp247_store_info('email'),
                        'subject' => gp247_language_render('email.order.email_subject_to_admin', ['order_id' => $orderID]),
                    ];
                    $subPath = 'email.order_success_to_admin';
                    $view = gp247_shop_process_view('GP247TemplatePath::'.gp247_store_info('template'),$subPath);
                    gp247_mail_send($view, $dataView, $config, []);
                }

                // Send mail order success to customer
                if (gp247_config('order_success_to_customer') && $data['email']) {
                    $dataView = [
                        'orderID' => $orderID,
                        'toname' => $data['first_name'].' '.$data['last_name'],
                        'address' => $data['address1'] . ' ' . $data['address2'].' '.$data['address3'],
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
                    $config = [
                        'to' => $data['email'],
                        'replyTo' => gp247_store_info('email'),
                        'subject' => gp247_language_render('email.order.email_subject_customer', ['order_id' => $orderID]),
                    ];
                    $subPath = 'email.order_success_to_customer';
                    $view = gp247_shop_process_view('GP247TemplatePath::'.gp247_store_info('template'),$subPath);
                    gp247_mail_send($view, $dataView, $config, []);
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