<?php

namespace GP247\Shop\Controllers\Auth;

use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopCustomer;
use GP247\Core\Models\AdminCustomField;
use GP247\Core\Models\AdminCountry;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use GP247\Shop\Controllers\Auth\AuthTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

class RegisterController extends RootFrontController
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
     */

    use RegistersUsers;
    use AuthTrait;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    // protected $redirectTo = '/home';
    protected function redirectTo()
    {
        return gp247_route_front('customer.index');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        $dataMapping = $this->mappingValidator($data);

        //Process captcha
        if (gp247_captcha_method() && in_array('register', gp247_captcha_page())) {
            $data['captcha_field'] = $data[gp247_captcha_method()->getField()] ?? '';
            $dataMapping['validate']['captcha_field'] = ['required', 'string', new \GP247\Core\Rules\CaptchaRule];
        }
        return Validator::make($data, $dataMapping['validate'], $dataMapping['messages']);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \GP247\Shop\Models\ShopCustomer
     */
    protected function create(array $data)
    {
        $data['country'] = strtoupper($data['country'] ?? '');
        $dataMap = $this->mappingValidator($data)['dataInsert'];

        $user = ShopCustomer::createCustomer($dataMap);

        return $user;
    }
    
    public function showRegistrationForm()
    {
        return redirect(gp247_route_front('customer.register'));
        // return view('auth.register');
    }

    protected function registered(Request $request, $user)
    {
        redirect()->route('front.home')->with(['message' => gp247_language_render('customer.register_success')]);
    }


    /**
     * Process front form register
     *
     * @param [type] ...$params
     * @return void
     */
    public function showRegisterFormProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_showRegisterForm();
    }


    /**
     * Form register
     *
     * @return  [type]  [return description]
     */
    private function _showRegisterForm()
    {
        if (session('customer')) {
            return redirect()->route('front.home');
        }
        $viewCaptcha = gp247_captcha_processview('register', gp247_language_render('customer.signup'));
        $subPath = 'auth.register';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            array(
                'title'       => gp247_language_render('customer.title_register'),
                'customer'    => [],
                'countries'   => AdminCountry::getCodeAll(),
                'layout_page' => 'shop_auth',
                'viewCaptcha' => $viewCaptcha,
                'customFields'=> (new AdminCustomField)->getCustomField($type = 'shop_customer'),
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('customer.title_register')],
                ],
            )
        );
    }


    /**
     * Handle a registration request for the application.
     * User for Front
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $data = $request->all();
        $this->validator($data)->validate();
        $user = $this->create($data);

        if ($user) {
            
            gp247_customer_created_by_client($user);

            //Login
            $this->guard()->login($user);
            
            if ($response = $this->registered($request, $user)) {
                return $response;
            }
        } else {
            return back()->withInput();
        }

        return $request->wantsJson()
                    ? new JsonResponse([], 201)
                    : redirect($this->redirectPath());
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('customer');
    }
}
