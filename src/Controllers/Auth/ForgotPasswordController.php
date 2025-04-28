<?php

namespace GP247\Shop\Controllers\Auth;

use GP247\Front\Controllers\RootFrontController;
use Auth;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends RootFrontController
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
     */

    use SendsPasswordResetEmails;

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
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(\Illuminate\Http\Request $request)
    {
        $data = $request->all();
        $dataMapping['email'] = 'required|string|email';
        
        //Process captcha
        if (gp247_captcha_method() && in_array('forgot', gp247_captcha_page())) {
            $data['captcha_field'] = $data[gp247_captcha_method()->getField()] ?? '';
            $dataMapping['captcha_field'] = ['required', 'string', new \GP247\Core\Rules\CaptchaRule];
        }
        $validator = \Illuminate\Support\Facades\Validator::make($data, $dataMapping);
        if ($validator->fails()) {
            return redirect()->back()
                        ->withErrors($validator)
                        ->withInput();
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $response = $this->broker()->sendResetLink(
            $this->credentials($request)
        );

        return $response == \Illuminate\Support\Facades\Password::RESET_LINK_SENT
                    ? $this->sendResetLinkResponse($request, $response)
                    : $this->sendResetLinkFailedResponse($request, $response);
    }


    /**
     * Process front Form forgot password
     *
     * @param [type] ...$params
     * @return void
     */
    public function showLinkRequestFormProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_showLinkRequestForm();
    }

    /**
     * Form forgot password
     * @return [view]
     */
    private function _showLinkRequestForm()
    {
        if (customer()->user()) {
            return redirect()->route('front.home');
        }
        $viewCaptcha = gp247_captcha_processview('forgot', gp247_language_render('action.submit'));
        $subPath = 'auth.forgot';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            array(
                'title'       => gp247_language_render('customer.password_forgot'),
                'layout_page' => 'shop_auth',
                'viewCaptcha' => $viewCaptcha,
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('customer.password_forgot')],
                ],
            )
        );
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

    protected function broker()
    {
        return Password::broker('customer_password');
    }
}
