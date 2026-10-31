<?php
namespace GP247\Shop\Controllers;


use GP247\Shop\Services\CustomerAccountService;
use GP247\Shop\Support\ShopLayoutPage;
use GP247\Front\Controllers\RootFrontController;
use GP247\Core\Models\AdminCountry;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopShippingStatus;
use GP247\Shop\Models\ShopCustomer;
use GP247\Core\Models\AdminCustomField;
use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Shop\Models\ShopCustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use GP247\Shop\Controllers\Auth\AuthTrait;

class ShopAccountController extends RootFrontController
{
    use AuthTrait;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Index user profile
     *
     * @return  [view]
     */
    public function index()
    {
        $customer = customer()->user();
        
        $subPath = 'account.shop_index';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view($view)
            ->with(
                [
                    'title'       => gp247_language_render('customer.my_account'),
                    'customer'    => $customer,
                    'layout_page' => ShopLayoutPage::Profile->value,
                    'breadcrumbs' => [
                        ['url'    => '', 'title' => gp247_language_render('customer.my_account')],
                    ],
                ]
            );
    }


    /**
     * Form Change password
     *
     * @return  [view]
     */
    public function changePassword()
    {
        $customer = customer()->user();

        $subPath = 'account.shop_change_password';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view($view)
        ->with(
            [
                'title'       => gp247_language_render('customer.change_password'),
                'customer'    => $customer,
                'layout_page' => ShopLayoutPage::Profile->value,
                'breadcrumbs' => [
                    ['url'    => gp247_route_front('customer.index'), 'title' => gp247_language_render('customer.my_profile')],
                    ['url'    => '', 'title' => gp247_language_render('customer.change_password')],
                ],
            ]
        );
    }

    /**
     * Post change password
     *
     * @param   Request  $request  [$request description]
     *
     * @return  [redirect]
     */
    public function postChangePassword(Request $request)
    {
        $dataUser = customer()->user();
        $password = $request->get('password');
        $password_old = $request->get('password_old');
        if (trim($password_old) == '') {
            return redirect()->back()
                ->with(
                    [
                        'password_old_error' => gp247_language_render('customer.password_old_required')
                    ]
                );
        } else {
            if (!\Hash::check($password_old, $dataUser->password)) {
                return redirect()->back()
                    ->with(
                        [
                            'password_old_error' => gp247_language_render('customer.password_old_notcorrect')
                        ]
                    );
            }
        }
        $messages = [
            'password.required' => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.password')]),
            'password.confirmed' => gp247_language_render('validation.confirmed', ['attribute'=> gp247_language_render('customer.password')]),
            'password_old.required' => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.password_old')]),
            'password.min' => gp247_language_render('validation.password.min', ['attribute'=> gp247_language_render('customer.password')]),
            'password.max' => gp247_language_render('validation.password.max', ['attribute'=> gp247_language_render('customer.password')]),
            'password.letters' => gp247_language_render('validation.password.letters', ['attribute'=> gp247_language_render('customer.password')]),
            'password.mixed' => gp247_language_render('validation.password.mixed', ['attribute'=> gp247_language_render('customer.password')]),
            'password.numbers' => gp247_language_render('validation.password.numbers', ['attribute'=> gp247_language_render('customer.password')]),
            'password.symbols' => gp247_language_render('validation.password.symbols', ['attribute'=> gp247_language_render('customer.password')]),
        ];
        $v = Validator::make(
            $request->all(),
            // Same rule as the API (US-LW-password-change-revokes-api-sessions): a new password is required.
            app(CustomerAccountService::class)->passwordRules(),
            $messages
        );
        if ($v->fails()) {
            return redirect()->back()->withErrors($v->errors());
        }
        // Also signs the customer out of every API client (US-LW-password-change-revokes-api-sessions).
        app(CustomerAccountService::class)->changePassword($dataUser, (string) $password_old, (string) $password);

        return redirect(gp247_route_front('customer.index'))
            ->with(['success' => gp247_language_render('customer.update_success')]);
    }

    /**
     * Form change info
     *
     * @return  [view]
     */
    public function changeInfomation()
    {
        $customer = customer()->user();

        $subPath = 'account.shop_change_infomation';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view($view)
        ->with(
                [
                    'title'       => gp247_language_render('customer.change_infomation'),
                    'customer'    => $customer,
                    'countries'   => AdminCountry::getCodeAll(),
                    'layout_page' => ShopLayoutPage::Profile->value,
                    'customFields'=> (new AdminCustomField)->getCustomField($type = (new ShopCustomer)->getTable()),
                    // WHY: consistency with the register form — makes the shared renderer
                    // model-independent. A bound $customer already supplies the type here,
                    // so this is inert but keeps both entry points symmetric. Modification 20260814T140030.
                    'customFieldType' => (new ShopCustomer)->getTable(),
                    'breadcrumbs' => [
                        ['url'    => gp247_route_front('customer.index'), 'title' => gp247_language_render('customer.my_profile')],
                        ['url'    => '', 'title' => gp247_language_render('customer.change_infomation')],
                    ],
                ]
            );
    }

    /**
     * Process update info
     *
     * @param   Request  $request  [$request description]
     *
     * @return  [redirect]
     */
    public function postChangeInfomation(Request $request)
    {
        $user = customer()->user();
        $cId = $user->id;
        $data = request()->all();
        // WHY: the change-infomation form posts no `id`, but the shared edit mapping
        // (gp247_customer_data_edit_mapping) needs it — it builds the email
        // unique-ignore-self rule as `unique:...,email,<id>,id` and unsets `id`
        // before update. Without it, an enabled `customer_email` config dereferences
        // an undefined `id` and the whole save 500s (never reaching the custom-field
        // write). Inject the authenticated customer id, matching the admin edit path.
        $data['id'] = $cId;

        $v =  $this->validator($data);
        if ($v->fails()) {
            return redirect()->back()
                ->withErrors($v)
                ->withInput();
        }
        $user = $this->updateCustomer($data, $cId);

        return redirect(gp247_route_front('customer.index'))
            ->with(['success' => gp247_language_render('customer.update_success')]);
    }

    /**
     * Validate data input
     */
    protected function validator(array $data)
    {
        // WHY: one rule set for the form and API v2 member/profile (US-CMP-api-customer-profile).
        return app(CustomerAccountService::class)->profileValidator(customer()->user(), $data);
    }

    /**
     * Update data customer
     */
    protected function updateCustomer(array $data, string $cId)
    {
        return app(CustomerAccountService::class)->updateProfile(ShopCustomer::find($cId), $data);
    }

    /**
     * Render order list
     * @return [view]
     */
    public function orderList()
    {
        $customer = customer()->user();
        $statusOrder = ShopOrderStatus::getIdAll();
        $subPath = 'account.shop_order_list';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view($view)
            ->with(
                [
                'title'       => gp247_language_render('customer.order_history'),
                'statusOrder' => $statusOrder,
                'orders'      => (new ShopOrder)->setCustomerId($customer->id)->getData(),
                'customer'    => $customer,
                'layout_page' => ShopLayoutPage::Profile->value,
                'breadcrumbs' => [
                    ['url'    => gp247_route_front('customer.index'), 'title' => gp247_language_render('customer.my_profile')],
                    ['url'    => '', 'title' => gp247_language_render('customer.order_history')],
                ],
                ]
            );
    }


    /**
     * Render order detail
     * @return [view]
     */
    public function orderDetail($id)
    {
        $customer = customer()->user();
        $statusOrder = ShopOrderStatus::getIdAll();
        $statusShipping = ShopShippingStatus::getIdAll();
        $attributesGroup = ShopAttributeGroup::pluck('name', 'id')->all();
        $order = ShopOrder::where('id', $id) ->where('customer_id', $customer->id)->first();
        if ($order) {
            $title = gp247_language_render('customer.order_detail').' #'.$order->id;
        } else {
            return $this->pageNotFound();
        }
        $subPath = 'account.shop_order_detail';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view($view)
        ->with(
            [
            'title'           => $title,
            'statusOrder'     => $statusOrder,
            'statusShipping'  => $statusShipping,
            'countries'       => AdminCountry::getCodeAll(),
            'attributesGroup' => $attributesGroup,
            'order'           => $order,
            'customer'        => $customer,
            'layout_page'     => ShopLayoutPage::Profile->value,
            'breadcrumbs'     => [
                ['url'        => gp247_route_front('customer.index'), 'title' => gp247_language_render('customer.my_profile')],
                ['url'        => '', 'title' => $title],
            ],
            ]
        );
    }


    /**
     * Render address list
     * @return [view]
     */
    public function addressList()
    {
        $customer = customer()->user();
        $subPath = 'account.shop_address_list';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view($view)
            ->with(
                [
                'title'       => gp247_language_render('customer.address_list'),
                'addresses'   => $customer->addresses,
                'countries'   => AdminCountry::getCodeAll(),
                'customer'    => $customer,
                'layout_page' => ShopLayoutPage::Profile->value,
                'breadcrumbs' => [
                    ['url'    => gp247_route_front('customer.index'), 'title' => gp247_language_render('customer.my_profile')],
                    ['url'    => '', 'title' => gp247_language_render('customer.address_list')],
                ],
                ]
            );
    }

    /**
     * Render address detail
     * @return [view]
     */
    public function updateAddress($id)
    {
        $customer = customer()->user();
        $address =  (new ShopCustomerAddress)->where('customer_id', $customer->id)
            ->where('id', $id)
            ->first();
        if ($address) {
            $title = gp247_language_render('customer.address_detail');
        } else {
            return $this->pageNotFound();
        }
        $subPath = 'account.shop_update_address';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view($view)
        ->with(
            [
            'title'       => $title,
            'address'     => $address,
            'customer'    => $customer,
            'countries'   => AdminCountry::getCodeAll(),
            'layout_page' => ShopLayoutPage::Profile->value,
            'breadcrumbs' => [
                ['url'    => gp247_route_front('customer.index'), 'title' => gp247_language_render('customer.my_profile')],
                ['url'    => '', 'title' => $title],
            ],
            ]
        );
    }

    /**
     * Process update address
     *
     * @param   Request  $request  [$request description]
     *
     * @return  [redirect]
     */
    public function postUpdateAddress($id)
    {
        $customer = customer()->user();
        $data = request()->all();
        $address =  (new ShopCustomerAddress)->where('customer_id', $customer->id)
            ->where('id', $id)
            ->first();
        
        $service = app(CustomerAccountService::class);
        $v = $service->addressValidator($data);
        if ($v->fails()) {
            return redirect()->back()->withErrors($v->errors());
        }
        if (!$address) {
            return $this->pageNotFound();
        }
        $service->updateAddress($customer, $address, $data);

        return redirect(gp247_route_front('customer.address_list'))
            ->with(['success' => gp247_language_render('customer.update_success')]);
    }

    /**
     * Get address detail
     *
     * @return  [json]
     */
    public function getAddress()
    {
        $customer = customer()->user();
        $id = request('id');
        $address =  (new ShopCustomerAddress)->where('customer_id', $customer->id)
            ->where('id', $id)
            ->first();
        if ($address) {
            return $address->toJson();
        } else {
            return $this->pageNotFound();
        }
    }

    /**
     * Get address detail
     *
     * @return  [json]
     */
    public function deleteAddress()
    {
        $customer = customer()->user();
        $service = app(CustomerAccountService::class);
        $address = $service->findAddress($customer, (string) request('id'));
        if ($address) {
            $service->deleteAddress($customer, $address);
        }
        return json_encode(['error' => 0, 'msg' => gp247_language_render('customer.delete_address_success')]);
    }

    /**
     * _verification function
     *
     * @return void
     */
    public function verification()
    {
        $customer = customer()->user();
        if (!$customer->hasVerifiedEmail()) {
            return redirect(gp247_route_front('customer.index'));
        }
        $subPath = 'account.shop_verify';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view($view)
            ->with(
                [
                    'title' => gp247_language_render('customer.verify_email.title_page'),
                    'customer' => $customer,
                ]
            );
    }

    /**
     * Resend email verification
     *
     * @return void
     */
    public function resendVerification()
    {
        $customer = customer()->user();
        if (!$customer->hasVerifiedEmail()) {
            return redirect(gp247_route_front('customer.index'));
        }
        $resend = $customer->sendEmailVerify();

        if ($resend) {
            return redirect()->back()->with('resent', true);
        }
    }

    /**
     * Verify the e-mail of customer {id} from the signed link sent by mail. Works with or
     * without a web session: the URL signature, the id and the hash of the current
     * e-mail prove the link; a session of another customer is refused. Never signs in.
     *
     * @param Request     $request
     * @param string|null $id
     * @param string|null $token sha1 of the e-mail the link was sent to.
     * @return \Illuminate\Http\RedirectResponse
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-email-verify-signed-link
     */
    public function verificationProcessData(Request $request, $id = null, $token = null)
    {
        if (! $request->hasValidSignature()) {
            abort(401);
        }
        $current = customer()->user();
        $customer = $id !== null ? ShopCustomer::find($id) : null;
        $valid = $customer
            && !empty($customer->email)
            && hash_equals(sha1($customer->email), (string) $token)
            && (!$current || (string) $current->id === (string) $customer->id);
        if (!$valid) {
            return redirect(gp247_route_front('front.home'))
                ->with(['error' => gp247_language_render('customer.verify_email.link_invalid')]);
        }

        if (!$customer->isVerified()) {
            $customer->forceFill(['email_verified_at' => \Carbon\Carbon::now()])->save();
        }
        $target = $current ? gp247_route_front('customer.index') : gp247_route_front('customer.login');
        return redirect($target)->with(['message' => gp247_language_render('customer.verify_email.verify_success')]);
    }
}
