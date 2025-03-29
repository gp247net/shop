<?php

namespace GP247\Shop\Controllers\Auth;

use GP247\Front\Controllers\RootFrontController;
use Auth;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;

class ResetPasswordController extends RootFrontController
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
     */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    // protected $redirectTo = '/home';
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(); //
    }

    /**
     * Process front Form forgot password
     *
     * @param [type] ...$params
     * @return void
     */
    public function showResetFormProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            $token = $params[1] ?? '';
            gp247_lang_switch($lang);
        } else {
            $token = $params[0] ?? '';
        }
        return $this->_showResetForm($token);
    }

    /**
     * Form reset password
     *
     * @param   Request  $request
     * @param   [string]   $token
     *
     * @return  [view]
     */
    private function _showResetForm($token = null)
    {
        if (customer()->user()) {
            return redirect()->route('front.home');
        }
        $subPath = 'auth.reset';
        $view = $this->GP247TemplatePath .'.'. $subPath;
        if (!view()->exists($view)) {
            if (!view()->exists('gp247-shop-front::'.$subPath)) {
                gp247_report('View not found '.$view);
                echo  gp247_language_render('front.view_not_exist', ['view' => $view]);
                exit();
            }   
            $view = 'gp247-shop-front::'.$subPath;
        }
        return view(
            $view,
            [
                'title'       => gp247_language_render('customer.password_reset'),
                'token'       => $token,
                'layout_page' => 'shop_auth',
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('customer.password_reset')],
                ],
            ]
        );
    }
}
