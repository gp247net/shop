<?php

namespace GP247\Shop\Api\Support\Commerce;

use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\OrderPurpose;
use GP247\Shop\Payment\PaymentCurrency;
use GP247\Shop\Payment\PaymentRequestService;
use Illuminate\Support\Facades\DB;

/**
 * Orders placed through the API that hold stock without money: the app may cancel its own
 * one, and gateway orders left unpaid past `gp247-config.payment.api_unpaid_order_minutes`
 * are cancelled lazily when the next API order is placed — no cron (NFR-AVAIL-001).
 * Cancelling goes through ShopOrder::changeStatus(), which gives the stock back, and closes
 * the order's open payment requests.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-cancel-unpaid-order
 * @aidlc-adr api-commerce_hosted-payment-via-payment-request
 */
class UnpaidApiOrders
{
    /** Most orders one lazy pass cancels: the placing request must stay quick. */
    public const BATCH = 20;

    public function __construct(private PaymentRequestService $requests)
    {
    }

    /**
     * Whether the app may cancel the order: placed through the API, still NEW, no money in.
     *
     * @param ShopOrder $order
     * @return bool
     */
    public function cancellable(ShopOrder $order): bool
    {
        if (!ShopOrder::supportsChannel() || (string) $order->channel !== 'api') {
            return false;
        }
        $received = (float) $order->received;

        return (int) $order->status === ShopOrderStatus::NEW
            && ($received <= 0 || PaymentCurrency::same($received, 0.0, (string) $order->currency));
    }

    /**
     * Cancel the order (stock back, open payment requests closed). Cancelling a cancelled
     * order changes nothing.
     *
     * @param ShopOrder $order
     * @param string    $why   History line.
     * @return bool True when the order is cancelled now, false when it may not be.
     */
    public function cancel(ShopOrder $order, string $why): bool
    {
        if ((int) $order->status === ShopOrderStatus::CANCELED) {
            return true;
        }
        if (!$this->cancellable($order)) {
            return false;
        }

        return (bool) DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($order, $why) {
            $error = $order->changeStatus(ShopOrderStatus::CANCELED, ['content' => $why, 'customer_id' => $order->customer_id ?: 0]);
            if ($error !== null) {
                gp247_report('[api-commerce] order ' . $order->id . ' not cancelled: ' . $error);

                return false;
            }
            foreach ($this->openRequests($order) as $request) {
                $this->requests->cancel($request);
            }

            return true;
        });
    }

    /**
     * Cancel up to BATCH API orders still unpaid at the gateway past the limit. A failure on
     * one order is reported and never stops the caller.
     *
     * @return int Orders cancelled.
     */
    public function expireLazily(): int
    {
        $minutes = (int) config('gp247-config.payment.api_unpaid_order_minutes', 1440);
        if ($minutes <= 0 || !ShopOrder::supportsChannel()) {
            return 0;
        }
        $done = 0;
        try {
            $stale = ShopOrder::where('channel', 'api')
                ->where('status', ShopOrderStatus::NEW)
                ->where('received', '<=', 0)
                ->where('created_at', '<', now()->subMinutes($minutes))
                ->whereIn('id', PaymentRequest::query()->select('subject_id')
                    ->where('subject_type', OrderPurpose::SUBJECT_TYPE)
                    ->where('direction', PaymentRequest::DIRECTION_IN)
                    ->where('status', PaymentRequest::STATUS_OPEN))
                ->orderBy('created_at')
                ->limit(self::BATCH)
                ->get();
            foreach ($stale as $order) {
                try {
                    $done += $this->cancel($order, 'Cancelled: unpaid at the gateway after ' . $minutes . ' minutes') ? 1 : 0;
                } catch (\Throwable $e) {
                    gp247_report('[api-commerce] expiring unpaid order ' . $order->id . ' failed: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            gp247_report('[api-commerce] expiring unpaid orders failed: ' . $e->getMessage());
        }

        return $done;
    }

    /**
     * @param ShopOrder $order
     * @return \Illuminate\Support\Collection<int, PaymentRequest> Open requests that hold no money.
     */
    private function openRequests(ShopOrder $order)
    {
        return PaymentRequest::where('subject_type', OrderPurpose::SUBJECT_TYPE)
            ->where('subject_id', (string) $order->id)
            ->where('direction', PaymentRequest::DIRECTION_IN)
            ->where('status', PaymentRequest::STATUS_OPEN)
            ->get()
            ->reject(fn (PaymentRequest $request) => $request->hasMovements());
    }
}
