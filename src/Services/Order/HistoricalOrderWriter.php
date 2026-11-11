<?php

namespace GP247\Shop\Services\Order;

use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderDetail;
use GP247\Shop\Models\ShopOrderHistory;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopOrderTotal;
use GP247\Shop\Models\ShopOrderTransaction;
use GP247\Shop\Models\ShopShippingStatus;
use GP247\Shop\Services\Catalog\WriteContext;
use GP247\Shop\Services\ShopEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Writes an order that already happened in another system (an archive brought in by a data
 * import): order, lines, totals, status history and the money received, as the source says.
 *
 * It does NOT go through ShopOrder::createOrder(): that path takes stock, checks flash-sale
 * quotas, applies promotions and sends mail — side effects of a real sale. Here:
 * - no stock moves: `stock_returned_at` is set, so deleting the order (or undoing the import)
 *   never credits stock that was never taken;
 * - no sync event (ShopEvents::muted) and no checkout_token;
 * - the money received goes through the payment ledger (recordPayment), so `received`,
 *   `balance` and `payment_status` stay derived from it (RISK-TECH-received-derivation-drift).
 *
 * Public contract (shop minor): write(WriteContext, payload) where payload =
 *   ['order' => [...shop_order columns...], 'lines' => [[sku, name, price, qty, total_price, tax?, tax_rate?, discount?, product_id?, attribute?]],
 *    'history' => [[order_status_id, content, add_date]], 'source' => string]
 * Money is given as non-negative magnitudes (sign by ShopOrderTotal::SIGN_MAP).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-historical-order-write
 * @aidlc-adr shop-admin_catalog-write-service
 */
final class HistoricalOrderWriter
{
    /** `shop_order.channel` of an imported order (column string(10)). */
    public const CHANNEL = 'import';

    /** product_id of a line whose product is not in this shop (the line keeps name and SKU). */
    public const NO_PRODUCT = '0';

    /** Columns of shop_order the payload may set. */
    private const ORDER_COLUMNS = [
        'customer_id', 'first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'email', 'phone', 'company',
        'address1', 'address2', 'address3', 'city', 'district', 'ward', 'country', 'postcode',
        'currency', 'exchange_rate', 'subtotal', 'tax', 'shipping', 'discount', 'other_fee', 'total',
        'status', 'shipping_status', 'payment_method', 'shipping_method', 'comment', 'created_at',
    ];

    /** Text columns of shop_order without a rule of their own, with their column length (see validate()). */
    private const TEXT_COLUMNS = [
        'last_name' => 100, 'first_name_kana' => 100, 'last_name_kana' => 100, 'phone' => 20, 'company' => 100,
        'address1' => 100, 'address2' => 100, 'address3' => 100, 'city' => 100, 'district' => 100, 'ward' => 100,
        'country' => 10, 'postcode' => 10, 'payment_method' => 100, 'shipping_method' => 100, 'comment' => 300,
    ];

    /**
     * Write one archived order in one transaction (nested in the caller's, if any).
     *
     * @param WriteContext         $context Store the order belongs to.
     * @param array<string, mixed> $payload See the class comment.
     * @return ShopOrder
     * @throws \Illuminate\Validation\ValidationException Field keys: order.*, lines, lines.N.*, history.N.*.
     */
    public function write(WriteContext $context, array $payload): ShopOrder
    {
        $data = $this->validate($payload);

        return ShopEvents::muted(fn () => DB::connection(GP247_DB_CONNECTION)->transaction(
            fn () => $this->persist($context, $data, (string) ($payload['source'] ?? ''))
        ));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Validated payload.
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $payload): array
    {
        $money = ['required', 'numeric', 'min:0'];
        // WHY every column has a rule: Laravel keeps only validated keys of a nested array, so a
        // column without one would be dropped silently (address, phone, line product id…).
        $textRules = [];
        foreach (self::TEXT_COLUMNS as $column => $length) {
            $textRules['order.' . $column] = ['nullable', 'string', 'max:' . $length];
        }

        return Validator::make($payload, $textRules + [
            'order' => ['required', 'array'],
            // WHY string: customer ids are UUIDs; '0' (or empty) means a guest order.
            'order.customer_id' => ['nullable', 'string', 'max:36'],
            'order.email' => ['nullable', 'string', 'max:150'],
            'order.first_name' => ['required', 'string', 'max:100'],
            'order.currency' => ['required', 'string', 'max:10'],
            'order.exchange_rate' => ['required', 'numeric', 'gt:0'],
            'order.subtotal' => $money,
            'order.tax' => ['nullable', 'numeric', 'min:0'],
            'order.shipping' => ['nullable', 'numeric', 'min:0'],
            'order.discount' => ['nullable', 'numeric', 'min:0'],
            'order.other_fee' => ['nullable', 'numeric', 'min:0'],
            'order.total' => $money,
            'order.received' => ['nullable', 'numeric', 'min:0'],
            'order.status' => ['required', 'integer', Rule::in(array_map('intval', array_keys(ShopOrderStatus::getIdAll())))],
            'order.shipping_status' => ['nullable', 'integer', Rule::in(array_map('intval', array_keys(ShopShippingStatus::getIdAll())))],
            'order.created_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.sku' => ['nullable', 'string', 'max:100'],
            'lines.*.price' => $money,
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.total_price' => $money,
            'lines.*.tax' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.product_id' => ['nullable', 'string', 'max:64'],
            'lines.*.attribute' => ['nullable', 'array'],
            'history' => ['nullable', 'array'],
            'history.*.order_status_id' => ['required', 'integer', Rule::in(array_map('intval', array_keys(ShopOrderStatus::getIdAll())))],
            'history.*.content' => ['nullable', 'string', 'max:500'],
            'history.*.add_date' => ['nullable', 'date'],
        ])->validate();
    }

    /**
     * @param WriteContext         $context
     * @param array<string, mixed> $data   Validated payload.
     * @param string               $source Name of the system the order comes from (for the ledger note).
     * @return ShopOrder
     */
    private function persist(WriteContext $context, array $data, string $source): ShopOrder
    {
        $input = $data['order'];
        $row = array_intersect_key($input, array_flip(self::ORDER_COLUMNS));
        $row = array_merge(['tax' => 0, 'shipping' => 0, 'discount' => 0, 'other_fee' => 0], $row);
        $row['customer_id'] = trim((string) ($row['customer_id'] ?? '')) !== '' ? (string) $row['customer_id'] : '0';
        $row['store_id'] = $context->storeId;
        $row['payment_status'] = \GP247\Shop\Models\ShopPaymentStatus::UNPAID;
        $row['received'] = 0;
        $row['balance'] = (float) $row['total'];
        // WHY: the goods of an archived order were never taken from this shop's stock.
        $row['stock_returned_at'] = gp247_time_now();
        if (ShopOrder::supportsChannel()) {
            $row['channel'] = self::CHANNEL;
        }

        $order = new ShopOrder($row);
        // WHY: created_at must be the date of the original sale, not the import day.
        $order->timestamps = isset($row['created_at']) ? false : true;
        if (isset($row['created_at'])) {
            $order->updated_at = $row['created_at'];
        }
        $order->save();
        $order->timestamps = true;

        $this->writeLines($order, $data['lines'], $context);
        $this->writeTotals($order);
        $this->writeHistory($order, (array) ($data['history'] ?? []), $context);

        $received = (float) ($input['received'] ?? 0);
        if ($received > 0) {
            $order->recordPayment($received, 'import', null, $row['created_at'] ?? null, $source !== '' ? 'Imported from ' . mb_substr($source, 0, 100) : 'Imported', $context->adminUserId ?? 0);
        }

        return $order->refresh();
    }

    /**
     * @param ShopOrder                  $order
     * @param list<array<string, mixed>> $lines
     * @param WriteContext               $context
     * @return void
     */
    private function writeLines(ShopOrder $order, array $lines, WriteContext $context): void
    {
        foreach ($lines as $line) {
            $productId = trim((string) ($line['product_id'] ?? ''));
            ShopOrderDetail::create([
                'order_id' => $order->id,
                'product_id' => $productId !== '' ? $productId : self::NO_PRODUCT,
                'name' => $line['name'],
                'sku' => (string) ($line['sku'] ?? ''),
                'price' => $line['price'],
                'qty' => $line['qty'],
                'total_price' => $line['total_price'],
                'tax' => $line['tax'] ?? 0,
                'tax_rate' => $line['tax_rate'] ?? 0,
                'discount' => $line['discount'] ?? 0,
                'attribute' => json_encode((array) ($line['attribute'] ?? [])),
                'currency' => $order->currency,
                'exchange_rate' => $order->exchange_rate,
                'store_id' => $context->storeId,
            ]);
        }
    }

    /**
     * Total rows from the order's own figures, as the admin order screen writes them.
     *
     * @param ShopOrder $order
     * @return void
     */
    private function writeTotals(ShopOrder $order): void
    {
        $rows = [
            ['subtotal', 'order.totals.sub_total', ShopOrderTotal::POSITION_SUBTOTAL],
            ['tax', 'order.totals.tax', ShopOrderTotal::POSITION_TAX],
            ['shipping', 'order.totals.shipping', ShopOrderTotal::POSITION_SHIPPING_METHOD],
            ['discount', 'order.totals.discount', ShopOrderTotal::POSITION_TOTAL_METHOD],
            ['other_fee', 'order.totals.other_fee', ShopOrderTotal::POSITION_OTHER_FEE],
            ['total', 'order.totals.total', ShopOrderTotal::POSITION_TOTAL],
        ];
        foreach ($rows as [$code, $title, $sort]) {
            ShopOrderTotal::create([
                'order_id' => $order->id,
                'code' => $code,
                'value' => (float) $order->{$code},
                'title' => gp247_language_render($title),
                'sort' => $sort,
            ]);
        }
    }

    /**
     * @param ShopOrder                  $order
     * @param list<array<string, mixed>> $history
     * @param WriteContext               $context
     * @return void
     */
    private function writeHistory(ShopOrder $order, array $history, WriteContext $context): void
    {
        foreach ($history as $entry) {
            ShopOrderHistory::create([
                'order_id' => $order->id,
                'order_status_id' => (int) $entry['order_status_id'],
                'content' => (string) ($entry['content'] ?? ''),
                'add_date' => $entry['add_date'] ?? $order->created_at,
                'admin_id' => $context->adminUserId ?? 0,
                'customer_id' => (string) $order->customer_id,
            ]);
        }
    }

    /**
     * Remove an archived order (undo of a data import): the order, its lines, totals, status
     * history and payment ledger rows. Only an order this writer created (channel `import`) of
     * the context's store qualifies; a real order keeps the delete guard of ShopOrder (money /
     * shipped), because this path never reaches ShopOrder::deleting. No stock moves — the
     * archived order never took any.
     *
     * @param WriteContext $context Store the order must belong to.
     * @param string       $orderId
     * @return string|null Null when removed; otherwise why it was kept (`not_found` | `not_archived`).
     */
    public function remove(WriteContext $context, string $orderId): ?string
    {
        $order = ShopOrder::where('store_id', $context->storeId)->find($orderId);
        if ($order === null) {
            return 'not_found';
        }
        if (!ShopOrder::supportsChannel() || (string) $order->channel !== self::CHANNEL) {
            return 'not_archived';
        }

        ShopEvents::muted(fn () => DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($order): void {
            // WHY query builder: Eloquent deletes would run the stock and delete-guard hooks of a real order.
            ShopOrderTransaction::withTrashed()->where('order_id', $order->id)->forceDelete();
            ShopOrderDetail::where('order_id', $order->id)->delete();
            ShopOrderTotal::where('order_id', $order->id)->delete();
            ShopOrderHistory::where('order_id', $order->id)->delete();
            ShopOrder::whereKey($order->id)->delete();
        }));

        return null;
    }
}
