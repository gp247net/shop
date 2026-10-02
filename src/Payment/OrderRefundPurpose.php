<?php

namespace GP247\Shop\Payment;

use GP247\Shop\Payment\Contracts\ProvidesRefundSources;
use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\Support\RefundSource;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopOrderTransaction;

/**
 * Purpose `order.refund`: give an order's money back to the customer — through the
 * gateway that took it at checkout (refund sources) or by hand. Each payout is a refund
 * on the order, keyed by the gateway's refund id so the gateway's own notification of
 * the same refund is a replay. Emptying the order moves it to "refunded" (a cancelled
 * order keeps its status), as the gateway plugins do.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-order-payment-request-refund
 * @aidlc-adr payment-request_generic-money-request
 */
class OrderRefundPurpose extends OrderPurpose implements ProvidesRefundSources
{
    public const KEY = 'order.refund';

    protected function ceiling(ShopOrder $order): float
    {
        return max(0.0, (float) $order->received);
    }

    /**
     * The order's payments taken by a gateway (method + gateway reference).
     *
     * WHY capped at what the order still holds: the order ledger does not tie a refund to
     * the payment it gives back; the gateway refuses anything beyond the real charge.
     *
     * @param PaymentRequest $request
     * @return array<int, RefundSource>
     */
    public function refundSources(PaymentRequest $request): array
    {
        $order = $this->orderOf($request);
        if ($order === null) {
            return [];
        }
        $held = (float) $order->received;

        return ShopOrderTransaction::where('order_id', $order->id)
            ->where('type', ShopOrderTransaction::TYPE_PAYMENT)
            ->whereNotNull('method')
            ->whereNotNull('gateway_transaction_id')
            ->orderBy('id')
            ->get()
            ->map(fn (ShopOrderTransaction $row) => new RefundSource(
                (string) $row->method,
                (string) $row->gateway_transaction_id,
                min((float) $row->amount, $held),
                trim(($row->paid_at ? (string) $row->paid_at : '') . ' · ' . $row->amount . ' ' . $order->currency, ' ·')
            ))
            ->all();
    }

    /**
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement Payout (by hand or a gateway refund).
     * @return void
     */
    public function onSettled(PaymentRequest $request, PaymentMovement $movement): void
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
        if ($row === null || !$row->wasRecentlyCreated) {
            return; // the gateway's notification got there first
        }

        $order = ShopOrder::find($order->id);
        $content = $this->historyLine($order, $request, 'refunded', $movement);
        if ((float) $order->received <= 0 && !in_array((int) $order->status, [ShopOrderStatus::CANCELED, ShopOrderStatus::REFUNDED], true)) {
            $order->changeStatus(ShopOrderStatus::REFUNDED, [
                'content' => $content,
                'admin_id' => $movement->admin_id ?: 0,
                'customer_id' => $order->customer_id ?: 0,
            ]);

            return;
        }
        $this->history($order, $content, $movement);
    }

    /**
     * A payout is never "reversed" through a request; nothing to mirror.
     *
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement
     * @return void
     */
    public function onReversed(PaymentRequest $request, PaymentMovement $movement): void
    {
    }
}
