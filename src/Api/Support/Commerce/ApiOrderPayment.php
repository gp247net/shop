<?php

namespace GP247\Shop\Api\Support\Commerce;

use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopPaymentStatus;
use GP247\Shop\Payment\Contracts\PaymentGateway;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\OrderBalancePurpose;
use GP247\Shop\Payment\OrderPurpose;
use GP247\Shop\Payment\PaymentRequestService;
use GP247\Shop\Payment\Support\ReturnUrlPolicy;

/**
 * How an order placed through the API is paid. A method without a gateway is `none`
 * (cash on delivery, bank transfer…). A method whose gateway can collect is `hosted`: the
 * order's balance becomes a payment request (purpose `order.balance`) and the gateway hands
 * out its hosted page. The money is recorded later by the gateway's own signed webhook /
 * return page — never by anything the app sends.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-hosted-payment
 * @aidlc-story US-api-commerce-payment-status
 * @aidlc-adr api-commerce_hosted-payment-via-payment-request
 */
class ApiOrderPayment
{
    public const TYPE_NONE = 'none';
    public const TYPE_HOSTED = 'hosted';

    public function __construct(private PaymentRequestService $requests)
    {
    }

    /**
     * @param ShopOrder $order
     * @return string TYPE_HOSTED when the order's payment method has a collecting gateway, else TYPE_NONE.
     */
    public static function typeOf(ShopOrder $order): string
    {
        $gateways = (array) config('gp247-config.payment.gateways', []);
        $method = (string) $order->payment_method;

        return $method !== '' && in_array(PaymentGateway::CAP_COLLECT, (array) ($gateways[$method]['capabilities'] ?? []), true)
            ? self::TYPE_HOSTED
            : self::TYPE_NONE;
    }

    /**
     * @param ShopOrder $order
     * @return string paid | partially_paid | refunded | unpaid — as recorded in the order's money ledger.
     */
    public static function status(ShopOrder $order): string
    {
        return match ((int) $order->payment_status) {
            ShopPaymentStatus::PAID => 'paid',
            ShopPaymentStatus::PARTIAL => 'partially_paid',
            ShopPaymentStatus::REFUND => 'refunded',
            default => 'unpaid',
        };
    }

    /**
     * How the app takes the payer through payment. For a hosted method with a balance left,
     * a fresh hosted page is asked for each time (a resend of the same order gets a new one).
     * A gateway that fails leaves the order placed and answers `url: null` — the app resends.
     *
     * @param ShopOrder   $order
     * @param string|null $returnUrl Where the payer goes after the gateway; kept only when allowed.
     * @return array{type: string, url?: string|null}
     */
    public function forOrder(ShopOrder $order, ?string $returnUrl = null): array
    {
        if (self::typeOf($order) !== self::TYPE_HOSTED) {
            return ['type' => self::TYPE_NONE];
        }
        if ((float) $order->balance <= 0) {
            return ['type' => self::TYPE_HOSTED, 'url' => null];
        }

        try {
            if (!PaymentRequestService::ready()) {
                throw new \DomainException('payment requests are not installed on this site (run php artisan gp247:update)');
            }
            $request = $this->openRequest($order, ReturnUrlPolicy::allowed($returnUrl));
            if ($this->requests->publicToken($request) === null) {
                // WHY: the gateways send the payer back to the request's pay page; without a
                // link token that page does not exist and the payer would land on the home page.
                $this->requests->issuePublicLink($request);
            }
            $url = $this->requests->startCollect($request, (string) $order->payment_method)->redirectUrl;
        } catch (\Throwable $e) {
            gp247_report('[api-commerce] hosted payment of order #' . $order->id . ' could not start: ' . $e->getMessage());
            $url = null;
        }

        return ['type' => self::TYPE_HOSTED, 'url' => $url !== '' ? $url : null];
    }

    /**
     * The open balance request of the order, created on first use.
     *
     * @param ShopOrder   $order
     * @param string|null $returnUrl Allowed return address, or null.
     * @return PaymentRequest
     */
    private function openRequest(ShopOrder $order, ?string $returnUrl): PaymentRequest
    {
        $request = PaymentRequest::where('subject_type', OrderPurpose::SUBJECT_TYPE)
            ->where('subject_id', (string) $order->id)
            ->where('purpose', OrderBalancePurpose::KEY)
            ->where('direction', PaymentRequest::DIRECTION_IN)
            ->orderByDesc('id')
            ->first();

        if ($request !== null && $request->acceptsMoney() && !$request->isSettled()) {
            $metadata = (array) ($request->metadata ?? []);
            if ($returnUrl !== null && ($metadata['return_url'] ?? null) !== $returnUrl) {
                $request->forceFill(['metadata' => ['return_url' => $returnUrl] + $metadata])->save();
            }

            return $request;
        }

        return $this->requests->create([
            'direction'    => PaymentRequest::DIRECTION_IN,
            'purpose'      => OrderBalancePurpose::KEY,
            'amount'       => (float) $order->balance,
            'currency'     => (string) $order->currency,
            'store_id'     => (string) $order->store_id,
            'subject_type' => OrderPurpose::SUBJECT_TYPE,
            'subject_id'   => (string) $order->id,
            'party_name'   => trim((string) $order->first_name . ' ' . (string) $order->last_name),
            'party_email'  => (string) $order->email,
            'party_phone'  => (string) $order->phone,
            'description'  => 'Order #' . $order->id,
            'metadata'     => ['source' => 'api', 'return_url' => $returnUrl],
        ]);
    }
}
