<?php

namespace GP247\Shop\Payment;

use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Payment\Contracts\OrderStatusAfterPayment;
use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;

/**
 * Purpose `order.checkout`: the payment of an order placed through checkout outside the
 * website (REST API). Money is recorded on the order like `order.balance`; in addition the
 * order status follows the gateway, as the gateway's own website payment step does — paid
 * in full ⇒ the gateway's "paid" status, fully refunded ⇒ its "refunded" status. A gateway
 * that does not implement OrderStatusAfterPayment leaves the status as it is.
 *
 * Only a NEW order moves on payment: money a gateway confirms for an order already
 * cancelled (or handled by the shop) is recorded without reopening it; refunding a
 * cancelled order keeps it cancelled (its stock is already back).
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-hosted-payment
 * @aidlc-adr api-commerce_hosted-payment-via-payment-request
 */
class OrderCheckoutPurpose extends OrderBalancePurpose
{
    public const KEY = 'order.checkout';

    /**
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement
     * @return void
     */
    public function onSettled(PaymentRequest $request, PaymentMovement $movement): void
    {
        parent::onSettled($request, $movement);
        $this->followGateway($request, $movement, OrderStatusAfterPayment::EVENT_PAID);
    }

    /**
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement
     * @return void
     */
    public function onReversed(PaymentRequest $request, PaymentMovement $movement): void
    {
        parent::onReversed($request, $movement);
        $this->followGateway($request, $movement, OrderStatusAfterPayment::EVENT_REFUNDED);
    }

    /**
     * Move the order to the status the gateway gives this event, when the money says so.
     *
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement
     * @param string          $event OrderStatusAfterPayment::EVENT_*.
     * @return void
     */
    private function followGateway(PaymentRequest $request, PaymentMovement $movement, string $event): void
    {
        $order = ShopOrder::find((string) $request->subject_id);
        if ($order === null) {
            return;
        }
        $currency = (string) $order->currency;
        if ($event === OrderStatusAfterPayment::EVENT_PAID) {
            $settled = (int) $order->status === ShopOrderStatus::NEW
                && ((float) $order->balance <= 0 || PaymentCurrency::same((float) $order->balance, 0.0, $currency));
        } else {
            // WHY not a cancelled order: leaving CANCELED takes the stock back (changeStatus) —
            // refunding money of a cancelled order must not take its goods out again.
            $settled = (int) $order->status !== ShopOrderStatus::CANCELED
                && ((float) $order->received <= 0 || PaymentCurrency::same((float) $order->received, 0.0, $currency));
        }
        if (!$settled) {
            return;
        }

        $driver = app(GatewayRegistry::class)->driver((string) $movement->gateway);
        // WHY also the bare method: a plugin that must still load on an older shop (no
        // interface yet) declares orderStatusAfter() without `implements`.
        if (!$driver instanceof OrderStatusAfterPayment && !($driver !== null && method_exists($driver, 'orderStatusAfter'))) {
            return;
        }
        // WHY the request's own store: the website checkout of a vendor order reads the gateway
        // settings of the vendor store too (gp247_plugin_store_id() = storeCheckout).
        $status = $driver->orderStatusAfter($event, (string) $request->store_id);
        if ($status === null || $status === (int) $order->status) {
            return;
        }
        $error = $order->changeStatus($status, [
            'content'     => ucfirst($event) . ' through ' . $movement->gateway . ' (payment request #' . $request->id . ')',
            'customer_id' => $order->customer_id ?: 0,
        ]);
        if ($error !== null) {
            gp247_report('[payment-request] order ' . $order->id . ': status after ' . $event . ' not applied: ' . $error);
        }
    }
}
