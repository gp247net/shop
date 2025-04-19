<?php

namespace GP247\Shop\Controllers\Auth;

use GP247\Front\Controllers\RootFrontController;
use GP247\Core\Models\AdminCountry;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class LoginController extends RootFrontController
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
     */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    // protected $redirectTo = '/';
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

    protected function validateLogin(Request $request)
    {
        $messages = [
            'email.email'       => gp247_language_render('validation.email', ['attribute'=> gp247_language_render('customer.email')]),
            'email.required'    => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.email')]),
            'password.required' => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('customer.password')]),
            ];
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ], $messages);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        return $validator;
    }

    /**
     * Process front form login
     *
     * @param [type] ...$params
     * @return void
     */
    public function showLoginFormProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_showLoginForm();
    }


    /**
     * Form login
     *
     * @return  [type]  [return description]
     */
    private function _showLoginForm()
    {
        if (customer()->user()) {
            return redirect()->route('front.home');
        }
        $subPath = 'auth.login';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            array(
                'title'       => gp247_language_render('customer.login_title'),
                'countries'   => AdminCountry::getCodeAll(),
                'layout_page' => 'shop_auth',
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('customer.login_title')],
                ],
            )
        );
    }


    public function logout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();

        return $this->loggedOut($request) ?: redirect(gp247_route_front('customer.login'));
    }

    protected function authenticated(Request $request, $user)
    {
        if (customer()->user()) {
            session(['customer' => customer()->user()->toJson()]);
            // Merge cart after login
            gp247_cart()->syncCartAfterLogin(customer()->user()->id);
            gp247_cart('compare')->syncCartAfterLogin(customer()->user()->id);
            gp247_cart('wishlist')->syncCartAfterLogin(customer()->user()->id);
        } else {
            session(['customer' => []]);
        }
    }

    
    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    protected function username()
    {
        return 'email';
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
