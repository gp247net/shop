<?php

namespace GP247\Shop\Payment;

use GP247\Shop\Payment\Contracts\PurposeResolver;
use GP247\Shop\Payment\Contracts\ValidatesRequest;
use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\PaymentCurrency;
use GP247\Shop\Models\ShopOrder;

/**
 * Shared ground of the two order purposes of the core's payment requests: the request
 * must point at one order of the same store and currency, and every movement is written
 * to that order's own payment ledger (the one place `received` moves).
 *
 * Ledger reference of a movement: the gateway's reference (idempotent with the gateway
 * plugin's own notifications), or `payreq-mv-<movement id>` when recorded by hand.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-order-payment-request-balance
 * @aidlc-story US-SADM-order-payment-request-refund
 * @aidlc-adr payment-request_generic-money-request
 */
abstract class OrderPurpose implements PurposeResolver, ValidatesRequest
{
    public const SUBJECT_TYPE = 'shop_order';

    /** @var bool|null Memoised: the owner's table exists. */
    private static ?bool $tableReady = null;

    /**
     * Whether the tables this purpose writes to exist (package/plugin install really ran).
     *
     * @return bool
     */
    public static function available(): bool
    {
        if (self::$tableReady === null) {
            try {
                $model = new \GP247\Shop\Models\ShopOrder();
                self::$tableReady = \Illuminate\Support\Facades\Schema::connection($model->getConnectionName())->hasTable($model->getTable());
            } catch (\Throwable $e) {
                self::$tableReady = false;
            }
        }

        return self::$tableReady;
    }

    /**
     * The shop's currencies for the core's payment requests (seam
     * `gp247-config.payment.currencies`).
     *
     * @return array<string, int> Code => decimals. Empty when the shop tables are not
     *                            installed yet — the core then reads it as "no catalogue".
     */
    public static function currencyCatalog(): array
    {
        try {
            return \GP247\Shop\Models\ShopCurrency::getListAll()
                ->mapWithKeys(fn ($row) => [(string) $row->code => (int) ($row->precision ?? 2)])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Labels of the shop's currencies for the core's currency picker (seam
     * `gp247-config.payment.currency_labels`).
     *
     * @return array<string, string> Code => "CODE — Name".
     */
    public static function currencyLabels(): array
    {
        try {
            return \GP247\Shop\Models\ShopCurrency::getListAll()
                ->mapWithKeys(fn ($row) => [(string) $row->code => trim((string) $row->code . ' — ' . (string) $row->name, ' —')])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The most the request may be for, given the order as it is now.
     *
     * @param ShopOrder $order
     * @return float
     */
    abstract protected function ceiling(ShopOrder $order): float;

    /**
     * @param array<string, mixed> $data
     * @return void
     * @throws \GP247\Shop\Payment\Support\RequestRejected Naming the field the refusal is about.
     */
    public function validateRequest(array $data): void
    {
        if (($data['subject_type'] ?? null) !== self::SUBJECT_TYPE || empty($data['subject_id'])) {
            throw new \GP247\Shop\Payment\Support\RequestRejected(gp247_language_render('admin.order.payment_request_needs_order'), 'subject_id');
        }
        $order = ShopOrder::find($data['subject_id']);
        if ($order === null) {
            throw new \GP247\Shop\Payment\Support\RequestRejected(gp247_language_render('admin.order.payment_request_order_missing'), 'subject_id');
        }
        if ((string) $order->store_id !== (string) ($data['store_id'] ?? '')) {
            throw new \GP247\Shop\Payment\Support\RequestRejected(gp247_language_render('admin.order.payment_request_other_store'), 'subject_id');
        }
        $currency = (string) ($data['currency'] ?? '');
        if (strcasecmp($currency, (string) $order->currency) !== 0) {
            throw new \GP247\Shop\Payment\Support\RequestRejected(gp247_language_render('admin.order.payment_request_other_currency', ['currency' => $order->currency]), 'currency');
        }
        $amount = (float) ($data['amount'] ?? 0);
        $ceiling = $this->ceiling($order);
        if ($amount > $ceiling && !PaymentCurrency::same($amount, $ceiling, $currency)) {
            throw new \GP247\Shop\Payment\Support\RequestRejected(gp247_language_render('admin.order.payment_request_over', ['max' => $ceiling]), 'amount');
        }
    }

    /**
     * @param PaymentRequest $request
     * @return array{label: string, url?: string, suggested_amount: float}|null
     */
    public function describeSubject(PaymentRequest $request): ?array
    {
        $order = $this->orderOf($request);
        if ($order === null) {
            return null;
        }
        $out = [
            'label' => '#' . $order->id . ' — ' . trim((string) $order->first_name . ' ' . (string) $order->last_name),
            'suggested_amount' => $this->ceiling($order),
        ];
        if (\Illuminate\Support\Facades\Route::has('admin_order.detail')) {
            $out['url'] = gp247_route_admin('admin_order.detail', ['id' => $order->id]);
        }

        return $out;
    }

    /**
     * @param PaymentRequest $request
     * @return ShopOrder|null
     */
    protected function orderOf(PaymentRequest $request): ?ShopOrder
    {
        if ($request->subject_type !== self::SUBJECT_TYPE || empty($request->subject_id)) {
            return null;
        }

        return ShopOrder::find($request->subject_id);
    }

    /**
     * The order a movement belongs to, or null (reported) when it is gone — the money
     * stays on the request; refusing it would not un-move it.
     *
     * @param PaymentRequest  $request
     * @param PaymentMovement $movement
     * @return ShopOrder|null
     * @throws \DomainException When a hand entry falls in a closed accounting period.
     */
    protected function orderFor(PaymentRequest $request, PaymentMovement $movement): ?ShopOrder
    {
        $order = $this->orderOf($request);
        if ($order === null) {
            gp247_report('[payment-request] request #' . $request->id . ': order ' . $request->subject_id . ' not found — movement #' . $movement->id . ' kept on the request only');

            return null;
        }
        // Same guard as the order screen: it protects a person typing, not money a
        // gateway already moved (OrderManager::periodIsClosed()).
        if ($movement->method === PaymentMovement::METHOD_MANUAL && function_exists('inout_check_period_closed')) {
            $date = $movement->paid_at ? $movement->paid_at->format('Y-m-d') : date('Y-m-d');
            if (inout_check_period_closed((string) $order->store_id, $date)) {
                throw new \DomainException(gp247_language_render('Plugins/InOut::lang.period_closed_error'));
            }
        }

        return $order;
    }

    /**
     * @param PaymentMovement $movement
     * @return string Ledger reference of the movement on the order.
     */
    protected function ledgerRef(PaymentMovement $movement): string
    {
        return (string) ($movement->gateway_ref ?: 'payreq-mv-' . $movement->id);
    }

    /**
     * @param PaymentMovement $movement
     * @return string|null Payment method recorded on the order (null when by hand).
     */
    protected function ledgerMethod(PaymentMovement $movement): ?string
    {
        return $movement->method === PaymentMovement::METHOD_MANUAL ? null : (string) $movement->gateway;
    }

    /**
     * @param ShopOrder      $order
     * @param PaymentRequest $request
     * @param string         $what
     * @param PaymentMovement $movement
     * @return string History line.
     */
    protected function historyLine(ShopOrder $order, PaymentRequest $request, string $what, PaymentMovement $movement): string
    {
        return 'Payment request #' . $request->id . ': ' . $what . ' ' . $movement->amount . ' ' . $order->currency
            . ($this->ledgerMethod($movement) ? ' via ' . $this->ledgerMethod($movement) : ' (by hand)');
    }

    protected function history(ShopOrder $order, string $content, PaymentMovement $movement): void
    {
        $order->addOrderHistory([
            'order_id' => $order->id,
            'content' => $content,
            'admin_id' => $movement->admin_id ?: 0,
            'customer_id' => $order->customer_id ?: 0,
            'order_status_id' => $order->status,
        ]);
    }
}
