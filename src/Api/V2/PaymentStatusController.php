<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Support\Commerce\ApiOrderPayment;
use GP247\Shop\Api\Support\Commerce\OrderAccessToken;
use GP247\Shop\Api\Support\Commerce\UnpaidApiOrders;
use GP247\Shop\Models\ShopOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payment status of an order placed through the API, read after the payer comes back
 * from the gateway: a signed-in customer reads their own orders, a guest the one order
 * their order access token opens. What is shown is what the gateway confirmed into the
 * order's money ledger; any other order — or a wrong token — answers the same 404. The
 * same owner may cancel an unpaid order placed through the API (stock back).
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-payment-status
 * @aidlc-story US-api-commerce-cancel-unpaid-order
 * @aidlc-adr api-commerce_guest-access-tokens
 */
class PaymentStatusController extends RootFrontController
{
    /**
     * GET api/v2/front/member/orders/{id}/payment (customer token).
     *
     * @param Request $request
     * @param string  $id
     * @return JsonResponse
     */
    public function member(Request $request, $id)
    {
        $order = ShopOrder::where('customer_id', $request->user()->id)->where('id', (string) $id)->first();

        return $this->answer($order);
    }

    /**
     * GET api/v2/front/orders/{id}/payment with header X-Order-Access (guest).
     *
     * @param Request $request
     * @param string  $id
     * @return JsonResponse
     */
    public function guest(Request $request, $id)
    {
        $token = $request->header(OrderAccessToken::HEADER);
        $order = OrderAccessToken::verify((string) $id, is_string($token) ? $token : null)
            ? ShopOrder::where('id', (string) $id)->first()
            : null;

        return $this->answer($order);
    }

    /**
     * POST api/v2/front/member/orders/{id}/cancel (customer token).
     *
     * @param Request $request
     * @param string  $id
     * @return JsonResponse
     */
    public function memberCancel(Request $request, $id)
    {
        return $this->cancel(ShopOrder::where('customer_id', $request->user()->id)->where('id', (string) $id)->first());
    }

    /**
     * POST api/v2/front/orders/{id}/cancel with header X-Order-Access (guest).
     *
     * @param Request $request
     * @param string  $id
     * @return JsonResponse
     */
    public function guestCancel(Request $request, $id)
    {
        $token = $request->header(OrderAccessToken::HEADER);
        $order = OrderAccessToken::verify((string) $id, is_string($token) ? $token : null)
            ? ShopOrder::where('id', (string) $id)->first()
            : null;

        return $this->cancel($order);
    }

    /**
     * Cancel the caller's unpaid API order and answer with its new status.
     *
     * @param ShopOrder|null $order
     * @return JsonResponse
     */
    private function cancel(?ShopOrder $order): JsonResponse
    {
        if ($order === null) {
            return ApiErrorResponder::notFound('Order not found');
        }
        if (!app(UnpaidApiOrders::class)->cancel($order, 'Cancelled by the customer in the app')) {
            return ApiErrorResponder::respond(409, 'order_not_cancellable',
                'Only an unpaid order placed through the app and not yet processed can be cancelled here');
        }

        return $this->answer($order->fresh());
    }

    /**
     * Status and amounts only — no address, no lines.
     *
     * @param ShopOrder|null $order
     * @return JsonResponse
     */
    private function answer(?ShopOrder $order): JsonResponse
    {
        if ($order === null) {
            return ApiErrorResponder::notFound('Order not found');
        }

        return response()->json(['data' => [
            'order' => [
                'id'             => (string) $order->id,
                'store_id'       => (string) $order->store_id,
                'status'         => (int) $order->status,
                'payment_status' => (int) $order->payment_status,
                'total'          => (float) $order->total,
                'received'       => (float) $order->received,
                'balance'        => (float) $order->balance,
                'currency'       => (string) $order->currency,
            ],
            'payment' => [
                'type'   => ApiOrderPayment::typeOf($order),
                'status' => ApiOrderPayment::status($order),
            ],
        ]]);
    }
}
