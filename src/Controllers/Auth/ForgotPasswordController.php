<?php

namespace GP247\Shop\Controllers\Auth;

use GP247\Front\Controllers\RootFrontController;
use Auth;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

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
        if (gp247_captcha_method() && in_array('forgot', gp247_captcha_page())) {
            $data['captcha_field'] = $data[gp247_captcha_method()->getField()] ?? '';
            $dataMapping['captcha_field'] = ['required', 'string', new \GP247\Shop\Rules\CaptchaRule];
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
        $viewCaptcha = '';
        if (gp247_captcha_method() && in_array('forgot', gp247_captcha_page())) {
            if (view()->exists(gp247_captcha_method()->pathPlugin.'::render')) {
                $dataView = [
                    'titleButton' => gp247_language_render('action.submit'),
                    'idForm' => 'gp247_form-process',
                    'idButtonForm' => 'gp247_button-form-process',
                ];
                $viewCaptcha = view(gp247_captcha_method()->pathPlugin.'::render', $dataView)->render();
            }
        }
        $subPath = 'auth.forgot';
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
}
