<?php

namespace GP247\Shop\Admin\Models;

use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopOrderTotal;
use GP247\Shop\Models\ShopOrderTransaction;
use GP247\Shop\Models\ShopPaymentStatus;
use Cache;

class AdminOrder extends ShopOrder
{
    public static $mapStyleStatus = [
        '1' => 'info', //new
        '2' => 'primary', //processing
        '3' => 'warning', //Hold
        '4' => 'danger', //Cancel
        '5' => 'success', //Success
        '6' => 'default', //Failed
    ];

    /**
     * Get order detail in admin
     *
     * @param   [type]  $id  [$id description]
     *
     * @return  [type]       [return description]
     */
    public static function getOrderAdmin($id, $storeId = null)
    {
        $data  = self::with(['details', 'orderTotal'])
        ->where('id', $id);
        if ($storeId) {
            $data = $data->where('store_id', $storeId);
        }
        return $data->first();
    }

    /**
     * Get list order in admin
     *
     * @param   [array]  $dataSearch  [$dataSearch description]
     *
     * @return  [type]               [return description]
     */
    public static function getOrderListAdmin(array $dataSearch)
    {
        $keyword      = $dataSearch['keyword'] ?? '';
        $email        = $dataSearch['email'] ?? '';
        $from_to      = $dataSearch['from_to'] ?? '';
        $end_to       = $dataSearch['end_to'] ?? '';
        $sort_order   = $dataSearch['sort_order'] ?? '';
        $arrSort      = $dataSearch['arrSort'] ?? '';
        $order_status = $dataSearch['order_status'] ?? '';
        $storeId      = $dataSearch['storeId'] ?? '';

        $orderList = (new ShopOrder);
        
        if ($storeId) {
            $orderList = $orderList->where('store_id', $storeId);
        }

        if ($order_status) {
            $orderList = $orderList->where('status', $order_status);
        }
        if ($keyword) {
            $orderList = $orderList->where(function ($sql) use ($keyword) {
                $sql->Where('id', 'like', '%'.$keyword.'%')
                    ->orWhere('email', 'like', '%'.$keyword.'%')
                    ->orWhere('first_name', 'like', '%'.$keyword.'%')
                    ->orWhere('last_name', 'like', '%'.$keyword.'%');
            });
        }

        if ($email) {
            $orderList = $orderList->where(function ($sql) use ($email) {
                $sql->Where('email', 'like', '%'.$email.'%');
            });
        }

        if ($from_to) {
            $orderList = $orderList->where(function ($sql) use ($from_to) {
                $sql->Where('created_at', '>=', $from_to);
            });
        }

        if ($end_to) {
            $orderList = $orderList->where(function ($sql) use ($end_to) {
                $sql->Where('created_at', '<=', $end_to);
            });
        }

        if ($sort_order && array_key_exists($sort_order, $arrSort)) {
            $field = explode('__', $sort_order)[0];
            $sort_field = explode('__', $sort_order)[1];
            $orderList = $orderList->sort($field, $sort_field);
        } else {
            $orderList = $orderList->sort('created_at', 'desc');
        }
        $orderList = $orderList->paginate(20);

        return $orderList;
    }

    /**
     * Insert order total
     *
     * @param   [type]  $dataInsert  [$dataInsert description]
     *
     * @return  [type]               [return description]
     */
    public static function insertOrderTotal($dataInsert)
    {
        $dataInsert = gp247_clean($dataInsert);
        return ShopOrderTotal::insert($dataInsert);
    }

    /**
     * Get item order total, then re-sort
     * @param  [int] $order_id [description]
     * @return [array]           [description]
     */
    public static function getOrderTotal($orderId)
    {
        $objects = ShopOrderTotal::where('order_id', $orderId)->get()->toArray();
        usort($objects, function ($a, $b) {
            if ($a['sort'] > $b['sort']) {
                return 1;
            } else {
                return -1;
            }
        });
        return $objects;
    }

    /**
     * Get row order total
     *
     * @param   [type]  $rowId  [$rowId description]
     *
     * @return  [type]          [return description]
     */
    public static function getRowOrderTotal($rowId)
    {
        return ShopOrderTotal::find($rowId);
    }

    /**
     * Update data when row of total change
     * @param  [array] $row [description]
     * @return [void]
     */
    public static function updateRowOrderTotal($dataRowTotal)
    {
        //Udate dataRowTotal
        $upField = ShopOrderTotal::find($dataRowTotal['id']);
        $upField->value = $dataRowTotal['value'];
        $upField->text = $dataRowTotal['text'];
        $upField->updated_at = gp247_time_now();
        $upField->save();
        $order_id = $upField->order_id;
        $order = ShopOrder::find($order_id);

        //Sum value item order total
        // WHY ShopOrderTotal::signOf(): the sign of every component is declared once,
        // in SIGN_MAP, and the stored values are non-negative magnitudes
        // (ADR shop-admin_money-sign-convention D1/D2). Before this contract the sum
        // added `discount` raw — correct only while discounts happened to be stored
        // negative, which admin-created orders never did, silently turning a discount
        // into a surcharge on the first edit (RISK-BIZ-order-sign-split).
        $totalData = ShopOrderTotal::where('order_id', $order_id)->get();
        $discount = $shipping = $other_fee = 0;
        foreach ($totalData as $value) {
            $code = $value['code'];
            if ($code === 'discount') {
                $discount += $value['value'];
            } elseif ($code === 'other_fee') {
                $other_fee += $value['value'];
            } elseif ($code === 'shipping') {
                $shipping += $value['value'];
            }
            // subtotal/tax/total are derived from the lines, and any legacy `received`
            // row is a payment, not a component of the document (D3).
        }

        //Update Order — only the components an admin edits directly
        $order->discount = $discount;
        $order->shipping = $shipping;
        $order->other_fee = $other_fee;
        $order->save();

        // Everything downstream of those components is recomputed by the one path that
        // owns it. Editing the discount now has to reach the LINES — it changes the
        // taxable base of every one of them — and this method never touched
        // shop_order_detail before. Delegating rather than re-deriving the total here is
        // also what keeps the two recalculation paths from drifting apart
        // (ADR shop-admin_order-discount-pre-tax D7).
        self::updateSubTotal($order_id);
    }


    /**
     * Update new sub total
     * @param  [int] $orderId [description]
     * @return [type]           [description]
     */
    public static function updateSubTotal($orderId)
    {
        try {
            $order = self::getOrderAdmin($orderId);
            // Discount comes off the lines BEFORE tax is charged on what remains, and the
            // per-line shares are rewritten here rather than read: this method runs on
            // every line edit, so an allocation that accumulated would drift silently
            // (ADR shop-admin_order-discount-pre-tax D7/D8).
            $sums = ShopOrder::find($orderId)->reallocateDiscountAndTax();
            $subTotal = $sums['subtotal'];
            $tax = $sums['tax'];
            $order->subtotal = $subTotal;
            $order->tax = $tax;
            // The cap can lower the stored discount (a discount typed larger than the
            // cart), so write back what was actually applied — otherwise the order would
            // keep rendering a figure the totals no longer use (F17).
            $order->discount = $sums['discount'];
            // WHY this shape: the one formula of the sign contract — every column is a
            // non-negative magnitude and the sign is applied here, once
            // (ADR shop-admin_money-sign-convention D2). `other_fee` joins the sum: it
            // was silently dropped before, so editing a line erased any surcharge.
            // `received` stays OUT of the total and only drives the balance (D3).
            // Read from the LEDGER, not the column: the column is a cache of
            // Σ payment − Σ refund, and re-deriving here means a cache that drifted for
            // any reason is corrected on the next recalculation rather than propagated
            // (ADR shop_order-payment-ledger D2, RISK-TECH-received-derivation-drift).
            $received = ShopOrderTransaction::netReceived($orderId);
            $order->received = $received;
            $total = $subTotal + $tax + $order->shipping + $order->other_fee - $order->discount;
            $balance = $total - $received;
            // The branch itself lives in ShopPaymentStatus::deriveFrom() — one copy for
            // every writer (updateSubTotal, postCreate, and the payment ledger), so a
            // rule change lands everywhere at once (NFR-MAINT-status-enum-single-source).
            $order->payment_status = ShopPaymentStatus::deriveFrom($received, $balance);
            $order->total = $total;
            $order->balance = $balance;
            $order->save();

            //Update total
            $updateTotal = ShopOrderTotal::where('order_id', $orderId)
                ->where('code', 'total')
                ->first();
            $updateTotal->value = $total;
            $updateTotal->save();
            
            //Update Subtotal
            $updateSubTotal = ShopOrderTotal::where('order_id', $orderId)
                ->where('code', 'subtotal')
                ->first();
            $updateSubTotal->value = $subTotal;
            $updateSubTotal->save();

            //Update tax
            $updateSubTotal = ShopOrderTotal::where('order_id', $orderId)
            ->where('code', 'tax')
            ->first();
            $updateSubTotal->value = $tax;
            $updateSubTotal->save();

            // Keep the discount ROW in step when the cap lowered it: the row is what the
            // detail screen and the invoice print, so leaving it showing a figure the
            // totals no longer use would make the document contradict itself.
            $discountRow = ShopOrderTotal::where('order_id', $orderId)
                ->where('code', 'discount')
                ->first();
            if ($discountRow !== null && (float) $discountRow->value != $sums['discount']) {
                $discountRow->value = $sums['discount'];
                $discountRow->save();
            }

            return 1;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }


    /**
     * Get country order in year
     *
     * @return  [type]  [return description]
    */
    public static function getCountryInYear()
    {
        return self::selectRaw('country, count(id) as count')
        ->whereRaw('DATE(created_at) >=  DATE_SUB(DATE(NOW()), INTERVAL 12 MONTH)')
        ->groupBy('country')
        ->orderBy('count', 'desc')
        ->get();
    }

    /**
     * Get device order in year
     *
     * @return  [type]  [return description]
    */
    public static function getDeviceInYear()
    {
        return self::selectRaw('device_type, count(id) as count')
        ->whereRaw('DATE(created_at) >=  DATE_SUB(DATE(NOW()), INTERVAL 12 MONTH)')
        ->groupBy('device_type')
        ->orderBy('count', 'desc')
        ->get();
    }
    
    /**
     * Dashboard revenue by month over the last 12 months — "order value placed".
     *
     * Semantics (ADR shop-admin_revenue-semantics): the VALUE OF ORDERS PLACED, by
     * placed date (created_at), converted to base. It deliberately differs from the
     * per-currency completed-order report and the InOut cash book (money collected by
     * paid_at) — each screen declares what it measures.
     *
     * Fixes three real bugs (modification 20260830T120405):
     *  - excludes Canceled/Failed (used to count them);
     *  - COALESCE(NULLIF(exchange_rate,0),1) so an order with a missing rate is not
     *    dropped by `total/NULL = NULL` (base orders are rate 1 — a deliberate default,
     *    not a guessed rate);
     *  - store scope (root sees all; a specific store filters), parity with OrderManager.
     *
     * @param int|string|null $storeId Null/root = all stores; otherwise this store only.
     * @return \Illuminate\Support\Collection
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-revenue-semantics
     * @aidlc-adr shop-admin_revenue-semantics
     */
    public static function getSumOrderTotalInYear($storeId = null)
    {
        return self::scopePlacedRevenue(
            self::selectRaw('DATE_FORMAT(created_at, "%Y-%m") AS ym, SUM(total/COALESCE(NULLIF(exchange_rate,0),1)) AS total_amount')
                ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") >=  DATE_FORMAT(DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH), "%Y-%m")'),
            $storeId
        )->groupBy('ym')->get();
    }

    /**
     * Dashboard order count by month — same scope as getSumOrderTotalInYear so the
     * "count" and "revenue" series line up (excludes Canceled/Failed, store-scoped).
     *
     * @param int|string|null $storeId Null/root = all stores; otherwise this store only.
     * @return \Illuminate\Support\Collection
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-revenue-semantics
     * @aidlc-adr shop-admin_revenue-semantics
     */
    public static function getCountOrderTotalInYear($storeId = null)
    {
        return self::scopePlacedRevenue(
            self::selectRaw('DATE_FORMAT(created_at, "%Y-%m") AS ym, count(*) AS count')
                ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") >=  DATE_FORMAT(DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH), "%Y-%m")'),
            $storeId
        )->groupBy('ym')->get();
    }

    /**
     * Dashboard revenue by day over the last month — "order value placed".
     * Same semantics and fixes as getSumOrderTotalInYear.
     *
     * @param int|string|null $storeId Null/root = all stores; otherwise this store only.
     * @return \Illuminate\Support\Collection
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-revenue-semantics
     * @aidlc-adr shop-admin_revenue-semantics
     */
    public static function getSumOrderTotalInMonth($storeId = null)
    {
        return self::scopePlacedRevenue(
            self::selectRaw('DATE_FORMAT(created_at, "%m-%d") AS md,
            SUM(total/COALESCE(NULLIF(exchange_rate,0),1)) AS total_amount, count(id) AS total_order')
                ->whereRaw('created_at >=  DATE_FORMAT(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH), "%Y-%m-%d")'),
            $storeId
        )->groupBy('md')->get();
    }

    /**
     * Apply the shared "placed revenue" scope: exclude Canceled/Failed, and filter by
     * store when a non-root store is given (root/null sees all — parity with OrderManager).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|string|null $storeId
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-revenue-semantics
     */
    private static function scopePlacedRevenue($query, $storeId)
    {
        $query->whereNotIn('status', [ShopOrderStatus::CANCELED, ShopOrderStatus::FAILED]);

        $root = defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : 1;
        if ($storeId !== null && (string) $storeId !== (string) $root) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }


    /**
     * Get total order of system
     *
     * @return  [type]  [return description]
     */
    public static function getTotalOrder()
    {
        return self::count();
    }


    /**
     * Get count order new
     *
     * @return  [type]  [return description]
     */
    public static function getCountOrderNew()
    {
        return self::where('status', 1)
        ->count();
    }
    
    /**
     * Get total order of system
     *
     * @return  [type]  [return description]
     */
    public static function getTopOrder()
    {
        return self::with('orderStatus')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Sum amount order
     *
     * @param   [type]  $storeId  [$storeId description]
     *
     * @return  [type]            [return description]
     */
    public static function getSumAmountOrder($storeId = null) {
        $data = (new AdminOrder)
        ->selectRaw('sum(total) as total_sum, currency')
        ->where('status', 5);//Only process order completed
        if ($storeId) {
            $data = $data->where('store_id', $storeId);
        }
        $data = $data->groupBy('currency')
        ->get()
        ->toArray();
        return $data;
    }
}
