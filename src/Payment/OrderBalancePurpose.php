<?php

namespace GP247\Shop\Payment;

use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Models\ShopOrder;

/**
 * Purpose `order.balance`: collect what is still due on an order (deposit balance, an
 * order created in the admin, a partial payment). Money in goes to the order as a
 * payment; a refund of it goes back as a refund. The order status is left to the admin.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-order-payment-request-balance
 * @aidlc-adr payment-request_generic-money-request
 */
class OrderBalancePurpose extends OrderPurpose
{
    public const KEY = 'order.balance';

    protected function ceiling(ShopOrder $order): float
    {
        return max(0.0, round((float) $order->total - (float) $order->received, 3));
    }

    /**
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement
     * @return void
     */
    public function onSettled(PaymentRequest $request, PaymentMovement $movement): void
    {
        $order = $this->orderFor($request, $movement);
        if ($order === null) {
            return;
        }
        $row = $order->recordPayment(
            (float) $movement->amount,
            $this->ledgerMethod($movement),
            $this->ledgerRef($movement),
            $movement->paid_at,
            'Payment request #' . $request->id,
            $movement->admin_id
        );
        if ($row !== null && $row->wasRecentlyCreated) {
            $this->history(ShopOrder::find($order->id), $this->historyLine($order, $request, 'received', $movement), $movement);
        }
    }

    /**
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement
     * @return void
     */
    public function onReversed(PaymentRequest $request, PaymentMovement $movement): void
    {
        $order = $this->orderFor($request, $movement);
        if ($order === null) {
            return;
        }
        $row = $order->recordRefund(
            (float) $movement->amount,
            $this->ledgerMethod($movement),
            $this->ledgerRef($movement),
            $movement->paid_at,
            'Payment request #' . $request->id,
            $movement->admin_id
        );
        if ($row !== null && $row->wasRecentlyCreated) {
            $this->history(ShopOrder::find($order->id), $this->historyLine($order, $request, 'refunded', $movement), $movement);
        }
    }
}
