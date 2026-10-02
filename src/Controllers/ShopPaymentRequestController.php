<?php

namespace GP247\Shop\Controllers;

use GP247\Front\Controllers\RootFrontController;

use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\PaymentCurrency;
use GP247\Shop\Payment\PaymentRequestService;
use Illuminate\Http\Request;

/**
 * Public pay page of a payment request: the payer opens the link the admin sent,
 * sees what they pay, to whom and for what, and picks an online gateway that collects.
 *
 * Every link that does not lead to a payable-or-informative request of THIS storefront
 * — unknown token, money going out, another store's request — answers the same 404, so
 * the page gives nothing away to someone guessing links
 * (NFR-SEC-payment-request-public-link).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-public-pay-page
 * @aidlc-adr payment-request_generic-money-request
 */
class ShopPaymentRequestController extends RootFrontController
{
    /** View key under the active template; falls back to the shop's standalone default. */
    private const VIEW = 'screen.shop_payment_request';

    public const STATE_PAYABLE = 'payable';
    public const STATE_SETTLED = 'settled';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_EXPIRED = 'expired';
    public const STATE_NO_GATEWAY = 'no_gateway';
    /** Nothing left to pay, or the purpose's plugin is gone — the payer contacts the store. */
    public const STATE_UNAVAILABLE = 'unavailable';

    /**
     * Show the request behind a payment link.
     *
     * @param string $token Raw token from the link.
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\Response
     */
    public function show(string $token)
    {
        $paymentRequest = $this->resolve($token);
        if ($paymentRequest === null) {
            return $this->pageNotFound();
        }

        $service = app(PaymentRequestService::class);
        $gateways = $service->collectGateways($paymentRequest);
        $state = $this->stateOf($paymentRequest, $gateways);

        return view($this->viewName(), [
            'title' => gp247_language_render('front.payment_request.title'),
            'paymentRequest' => $paymentRequest,
            'token' => $token,
            'state' => $state,
            'gateways' => $state === self::STATE_PAYABLE ? $gateways : [],
            'outstanding' => $this->money($paymentRequest->outstanding(), $paymentRequest->currency),
            'total' => $this->money((float) $paymentRequest->amount, $paymentRequest->currency),
        ]);
    }

    /**
     * Send the payer to the chosen gateway's hosted page.
     *
     * @param Request $request
     * @param string  $token
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    public function collect(Request $request, string $token)
    {
        $paymentRequest = $this->resolve($token);
        if ($paymentRequest === null) {
            return $this->pageNotFound();
        }

        $service = app(PaymentRequestService::class);
        $gateway = (string) $request->input('gateway', '');
        if (!array_key_exists($gateway, $service->collectGateways($paymentRequest))) {
            return back()->with('error', gp247_language_render('front.payment_request.choose_gateway'));
        }

        try {
            $result = $service->startCollect($paymentRequest, $gateway);
        } catch (\DomainException $e) {
            return back()->with('error', gp247_language_render('front.payment_request.not_payable'));
        } catch (\Throwable $e) {
            gp247_report('[payment-request] collect through "' . $gateway . '" failed for request #' . $paymentRequest->id . ': ' . $e->getMessage());

            return back()->with('error', gp247_language_render('front.payment_request.gateway_error'));
        }

        if (empty($result->redirectUrl)) {
            return back()->with('error', gp247_language_render('front.payment_request.gateway_error'));
        }

        return redirect()->away($result->redirectUrl);
    }

    /**
     * The request behind a token, when this storefront may show it.
     *
     * @param string $token
     * @return PaymentRequest|null
     */
    private function resolve(string $token): ?PaymentRequest
    {
        try {
            $paymentRequest = app(PaymentRequestService::class)->findByPublicToken($token);
        } catch (\Throwable $e) {
            // Core not upgraded yet (no payment_request table): no link can exist.
            gp247_report('[payment-request] pay page lookup failed: ' . $e->getMessage());

            return null;
        }
        if ($paymentRequest === null || $paymentRequest->direction !== PaymentRequest::DIRECTION_IN) {
            return null;
        }
        if ((string) $paymentRequest->store_id !== (string) $this->storefrontStoreId()) {
            return null;
        }

        return $paymentRequest;
    }

    /**
     * @param PaymentRequest        $paymentRequest
     * @param array<string, string> $gateways Collecting gateways.
     * @return string One of STATE_*.
     */
    private function stateOf(PaymentRequest $paymentRequest, array $gateways): string
    {
        if ($paymentRequest->isSettled()) {
            return self::STATE_SETTLED;
        }
        if ($paymentRequest->isCancelled()) {
            return self::STATE_CANCELLED;
        }
        if ($paymentRequest->isExpired()) {
            return self::STATE_EXPIRED;
        }
        if (!app(PaymentRequestService::class)->isPayable($paymentRequest)) {
            return self::STATE_UNAVAILABLE;
        }

        return $gateways === [] ? self::STATE_NO_GATEWAY : self::STATE_PAYABLE;
    }

    /**
     * @return string Store the current domain resolved to at boot.
     */
    private function storefrontStoreId(): string
    {
        return (string) (config('app.storeId') ?? (defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : '1'));
    }

    /**
     * @param float  $amount
     * @param string $currency
     * @return string e.g. "1,250.50 USD"
     */
    private function money(float $amount, string $currency): string
    {
        return number_format($amount, PaymentCurrency::precision($currency)) . ' ' . $currency;
    }

    /**
     * WHY the default is standalone (a whole page, inline CSS): it must render on any
     * template, including one whose site never published the default template's assets.
     *
     * @return string The active template's view (its own customisation), or the shop's
     *                standalone default when the template does not ship one.
     */
    private function viewName(): string
    {
        $templateView = $this->GP247TemplatePath . '.' . self::VIEW;

        return view()->exists($templateView) ? $templateView : 'gp247-shop-front::' . self::VIEW;
    }
}
