<?php
#GP247/Shop/Models/ShopOrder.php
namespace GP247\Shop\Models;

use GP247\Shop\Models\ShopOrderDetail;
use GP247\Shop\Models\ShopOrderHistory;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopOrderTotal;
use GP247\Shop\Models\ShopProduct;
use DB;
use Illuminate\Database\Eloquent\Model;


class ShopOrder extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;

    public $table = GP247_DB_PREFIX.'shop_order';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    /**
     * Cast the decimal(16,6) exchange_rate snapshot to a number: Laravel returns
     * a raw decimal column as a string, but this rate is multiplied against money
     * to convert an order back to base, so consumers need a numeric value
     * (ADR compat-foundation_exchange-rate-precision).
     *
     * @var array<string, string>
     */
    protected $casts = ['exchange_rate' => 'float', 'stock_returned_at' => 'datetime'];

    protected $gp247_customer_id = null;
    public $gp247_status = null;
    
    public function details()
    {
        return $this->hasMany(ShopOrderDetail::class, 'order_id', 'id');
    }
    public function orderTotal()
    {
        return $this->hasMany(ShopOrderTotal::class, 'order_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo('GP247\Shop\Models\ShopCustomer', 'customer_id', 'id');
    }
    public function orderStatus()
    {
        return $this->hasOne(ShopOrderStatus::class, 'id', 'status');
    }
    public function paymentStatus()
    {
        return $this->hasOne(ShopPaymentStatus::class, 'id', 'payment_status');
    }
    public function history()
    {
        return $this->hasMany(ShopOrderHistory::class, 'order_id', 'id');
    }

    /**
     * Full customer name on the order, merging first and last name.
     *
     * Mirrors ShopCustomer::getNameAttribute so admin screens can render a
     * unified `$order->name` (last name is merged only when present). Trimmed
     * to avoid a trailing space when last_name is empty/disabled by config.
     * Not a DB column and not appended to array/JSON output.
     *
     * @return string Merged "first_name last_name" (trimmed).
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-003
     */
    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Put the order's goods back into stock, at most once.
     *
     * Cancelling an order is what returns stock. Until this existed, DELETING an order
     * was the only documented way to get goods back, so an admin who wanted the goods
     * had to destroy the document — which is exactly what the user guide told them to do
     * (`gp247-docs/s-cart/product-stock-management_vi.md`).
     *
     * `stock_returned_at` is not bookkeeping for its own sake: without it, an order that
     * is cancelled, re-opened and cancelled again would hand the same goods back twice.
     * It cannot be inferred from the current status either — an order that went
     * Cancelled -> Processing -> Cancelled ends on the same status as one cancelled
     * once, with a different stock history behind it.
     *
     * @return bool True when stock was returned by this call; false when it already was.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-cancel-restock
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public function returnStockToInventory(): bool
    {
        if ($this->stock_returned_at !== null) {
            return false;
        }

        // Wrap the multi-line move in a transaction so a failure part-way cannot
        // leave inventory half-returned while the marker says otherwise
        // (ADR compat-foundation_atomic-stock-movement). Returning stock is a
        // negative delta, never guarded, so updateStock() always succeeds here.
        DB::connection(GP247_DB_CONNECTION)->transaction(function () {
            foreach ($this->details as $detail) {
                ShopProduct::updateStock($detail->product_id, -$detail->qty);
            }

            $this->stock_returned_at = gp247_time_now();
            $this->save();
        });

        return true;
    }

    /**
     * Take the goods back out of stock because the order is live again.
     *
     * Re-opening a cancelled order creates demand for the goods exactly as ordering
     * them did, so it goes through the same over-stock policy as every other point
     * that consumes stock (NFR-MAINT-order-stock-single-source). Letting it skip that
     * check would be a back door into selling what the shop does not have.
     *
     * Every line is checked BEFORE any is written. A half-applied re-open leaves
     * inventory wrong while the order still reads as cancelled — wrong in a way
     * nothing downstream could trace back to here.
     *
     * @return bool True when the stock was taken back; false when refused (not enough
     *              stock under the shop's policy) or when nothing was returned to begin with.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-cancel-restock
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public function takeStockBack(): bool
    {
        if ($this->stock_returned_at === null) {
            return false;
        }

        $details = $this->details;
        foreach ($details as $detail) {
            $product = ShopProduct::find($detail->product_id);
            if ($product !== null && !$product->hasStockForOrder($detail->qty)) {
                return false;
            }
        }

        // All-or-nothing: take the goods back inside a transaction and re-check the
        // atomic result. A concurrent order could have consumed the stock between the
        // pre-check above and here — updateStock() then returns false and we roll the
        // whole re-open back rather than leave stock negative
        // (ADR compat-foundation_atomic-stock-movement).
        try {
            DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($details) {
                foreach ($details as $detail) {
                    if (!ShopProduct::updateStock($detail->product_id, $detail->qty)) {
                        throw new \RuntimeException('stock_unavailable');
                    }
                }

                $this->stock_returned_at = null;
                $this->save();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'stock_unavailable') {
                return false;
            }
            throw $e;
        }

        return true;
    }

    /**
     * Re-split the order's discount across its lines and recompute each line's tax on
     * what is left after that share.
     *
     * This is the single place the discount→tax chain is expressed. Four paths reach it
     * — storefront checkout, admin order creation, editing a line, and editing the
     * discount total row — and they have to agree, because they all write the same
     * columns. The last of those is new: updateRowOrderTotal() never touched
     * shop_order_detail before, which is precisely how the most ordinary admin action
     * would have broken the invariant (ADR shop-admin_order-discount-pre-tax D7).
     *
     * Order of operations matters and is the substance of audit F5: the discount comes
     * off first, tax is charged on the remainder. Charging tax on the full price and
     * then deducting leaves the customer paying tax on money they never spent, and
     * leaves shop_order.tax describing a price nobody was charged.
     *
     * Everything is derived from (line totals, order discount) alone — never from the
     * shares already stored — so running it repeatedly is a no-op
     * (RISK-TECH-discount-allocation-drift).
     *
     * @return array{subtotal: float, tax: float, discount: float} Recomputed order-level sums.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-discount-pre-tax
     * @aidlc-adr shop-admin_order-discount-pre-tax
     */
    public function reallocateDiscountAndTax(): array
    {
        $lines = $this->details()->orderBy('created_at')->orderBy('id')->get();

        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += (float) $line->total_price;
        }

        // Cap here as well as in the helper: the capped figure is what gets written back
        // to the order, so a discount typed larger than the cart can never survive as a
        // stored value that later screens would faithfully render (F17).
        $discount = min(max(0.0, (float) $this->discount), $subtotal);
        $shares = gp247_allocate_discount(
            $lines->pluck('total_price')->map(fn ($v) => (float) $v)->all(),
            $discount
        );

        $tax = 0.0;
        foreach ($lines as $index => $line) {
            $share = (float) ($shares[$index] ?? 0);
            $lineTax = $this->lineTaxFor($line, $share);

            // Touch the row only when something actually changed: this runs on every
            // line edit, and rewriting unchanged rows would churn updated_at on the
            // whole order for no reason.
            if ((float) $line->discount != $share || (float) $line->tax != $lineTax) {
                $line->discount = $share;
                $line->tax = $lineTax;
                $line->save();
            }
            $tax += $lineTax;
        }

        return ['subtotal' => $subtotal, 'tax' => $tax, 'discount' => $discount];
    }

    /**
     * Write the reallocated figures back onto the order header, its total rows and
     * the payment-derived fields — the single write-back both order-creation paths
     * (storefront createOrder + admin updateSubTotal) share.
     *
     * WHY shared: the header must equal the sum of the lines at ALL times, including
     * the instant the order is created. The storefront used to call
     * reallocateDiscountAndTax() and DISCARD its result, so shop_order.tax kept the
     * session snapshot while the lines were re-taxed — the invariant held only after
     * the first admin edit (RISK-BIZ-order-header-line-drift). Both writers going
     * through one method is what stops them drifting apart.
     *
     * @param array{subtotal: float, tax: float, discount: float} $sums From reallocateDiscountAndTax().
     * @return void
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-checkout-total-freshness
     * @aidlc-adr storefront_checkout-idempotency
     */
    public function syncOrderTotals(array $sums): void
    {
        $this->subtotal = $sums['subtotal'];
        $this->tax = $sums['tax'];
        // The cap in reallocate can lower the applied discount; store what was applied.
        $this->discount = $sums['discount'];

        // The one sign convention (ADR shop-admin_money-sign-convention D2):
        // total = subtotal + tax + Σ(signOf(code) × value).
        $total = (float) $sums['subtotal'] + (float) $sums['tax']
            + ShopOrderTotal::signOf('shipping') * (float) $this->shipping
            + ShopOrderTotal::signOf('other_fee') * (float) $this->other_fee
            + ShopOrderTotal::signOf('discount') * (float) $sums['discount'];
        $this->total = $total;
        $this->save();

        // Keep the total ROWS in step with the header (they are what the detail
        // screen and invoice print). Only touch a row that exists and changed.
        $rowValues = [
            'subtotal' => (float) $sums['subtotal'],
            'tax' => (float) $sums['tax'],
            'discount' => (float) $sums['discount'],
            'total' => $total,
        ];
        foreach ($rowValues as $code => $value) {
            $row = ShopOrderTotal::where('order_id', $this->id)->where('code', $code)->first();
            if ($row !== null && (float) $row->value != $value) {
                $row->value = $value;
                $row->save();
            }
        }

        // received/balance/payment_status are derived from the ledger (ADR
        // shop_order-payment-ledger); recompute after total changed.
        $this->recalcReceived();
    }

    /**
     * Tax for one line after its discount share, recovering the rate when the line
     * predates the column.
     *
     * The naive form — `(total_price − share) × tax_rate` — quietly WIPES the tax of
     * every line written before `tax_rate` existed, because their rate reads 0 while
     * their amount does not. Recalculation runs on ordinary admin edits, so the first
     * person to touch an old order would zero its tax with nothing to warn them.
     *
     * So a rate of 0 next to a non-zero amount is read as "rate unknown" rather than
     * "no tax", and recovered from the amount — the same derivation the upgrade
     * migration performs, applied lazily for anything it could not reach. When there is
     * no base to divide by, the stored amount is kept untouched: inventing a rate there
     * would be a guess, and guessing on money is what policy P4 forbids.
     *
     * @param ShopOrderDetail $line  Order line.
     * @param float           $share Discount allocated to this line.
     * @return float Tax amount for the line.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-discount-pre-tax
     * @aidlc-adr shop-admin_order-discount-pre-tax
     */
    private function lineTaxFor($line, float $share): float
    {
        $lineTotal = (float) $line->total_price;
        $storedTax = (float) $line->tax;
        $rate = (float) $line->tax_rate;

        if ($rate == 0 && $storedTax != 0) {
            if ($lineTotal <= 0) {
                return $storedTax;
            }
            $rate = $storedTax / $lineTotal * 100;
        }

        return round(($lineTotal - $share) * $rate / 100, 2);
    }

    /**
     * Whether money has ever passed through this order.
     *
     * Two signals, OR-ed rather than AND-ed, because they cover different eras of
     * the data:
     *  - a ledger row covers everything written since the ledger existed;
     *  - a non-zero `received` covers older orders whose money predates the ledger,
     *    or was written straight into the column.
     *
     * `withTrashed()` is the part that is easy to get wrong. ShopOrderTransaction
     * uses SoftDeletes, so the plain relation hides withdrawn movements — yet money
     * that was recorded and then reversed is still proof the order handled money,
     * and an order in exactly that state (trashed row, received back to 0) would
     * otherwise sail through both checks.
     *
     * With money, the cheap mistake is refusing a delete that was fine; the
     * expensive one is allowing a delete that destroys evidence. Hence OR.
     *
     * @return bool True when the order holds, or once held, money.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-delete-money-guard
     * @aidlc-adr shop-admin_order-delete-money-guard
     */
    public function hasMoneyRecorded(): bool
    {
        if ((float) $this->received != 0) {
            return true;
        }

        return $this->transactions()->withTrashed()->exists();
    }

    /**
     * Why this order may not be deleted, or null when deletion is allowed.
     *
     * Mirrors ShopCurrency::deleteBlockReason() so admin screens can turn the reason
     * into a message that says what to do instead. Only one reason exists today; the
     * shape is kept so a second never has to reshape the callers.
     *
     * Two reasons, and they guard different things. Money is evidence: deleting an
     * order that handled money destroys the record of it. Shipping is physical: once
     * the goods have left, the order describes something that really happened — and
     * since deletion now returns stock, deleting a shipped order would credit
     * inventory for goods sitting at a customer's address.
     *
     * @return string|null Reason slug (`has_money` | `has_shipped`), or null if deletable.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-delete-money-guard
     * @aidlc-adr shop-admin_order-delete-money-guard
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public function deleteBlockReason(): ?string
    {
        if ($this->hasMoneyRecorded()) {
            return 'has_money';
        }

        if (ShopShippingStatus::goodsHaveLeft($this->shipping_status)) {
            return 'has_shipped';
        }

        return null;
    }

    protected static function boot()
    {
        parent::boot();

        // ╔══════════════════════════════════════════════════════════════════╗
        // ║  STOCK-RESTORE CONTRACT — READ BEFORE TOUCHING THIS BLOCK       ║
        // ╠══════════════════════════════════════════════════════════════════╣
        // ║  When a whole order is deleted, stock is restored here           ║
        // ║  in TWO intentional steps:                                       ║
        // ║                                                                  ║
        // ║  Step 1 — restore stock MANUALLY via foreach:                    ║
        // ║    ShopProduct::updateStock($id, -$qty)  ← negative = restore   ║
        // ║                                                                  ║
        // ║  Step 2 — cascade-delete detail rows via QUERY-BUILDER:          ║
        // ║    $order->details()->delete()                                   ║
        // ║    ↑ This is a raw DB delete, NOT Eloquent model delete().       ║
        // ║    ↑ It BYPASSES ShopOrderDetail::boot() deleting event.        ║
        // ║                                                                  ║
        // ║  WHY query-builder and not Eloquent delete() per row?            ║
        // ║  ShopOrderDetail::boot() deleting also calls updateStock().      ║
        // ║  If we used a foreach + $detail->delete() here instead,          ║
        // ║  updateStock() would fire TWICE per row:                         ║
        // ║    once from Step 1 above + once from ShopOrderDetail event      ║
        // ║  = stock restored DOUBLE → inventory inflated silently.          ║
        // ║                                                                  ║
        // ║  ⚠️  DANGER — never refactor Step 2 to:                         ║
        // ║      foreach ($order->details as $d) { $d->delete(); }          ║
        // ║  That switches from query-builder to Eloquent and triggers       ║
        // ║  the ShopOrderDetail event, causing double-restore.              ║
        // ║                                                                  ║
        // ║  SINGLE-ROW deletes (e.g. HasOrderItems::deleteItem) go through ║
        // ║  Eloquent $detail->delete() → ShopOrderDetail event fires →     ║
        // ║  stock restored once, correctly.                                 ║
        // ╚══════════════════════════════════════════════════════════════════╝
        static::deleting(function ($order) {
            // Step 0: refuse outright if this order ever handled money.
            //
            // WHY FIRST, before anything else in this hook: the restore loop below
            // hands quantities back to stock. A guard placed after it would let a
            // BLOCKED order still inflate inventory — order untouched, stock wrong,
            // and nothing anywhere to show it happened
            // (ADR shop-admin_order-delete-money-guard; RISK-BIZ-order-hard-delete-money-loss).
            if ($order->deleteBlockReason() !== null) {
                return false;
            }

            // Give the goods back before the record disappears.
            //
            // Deleting a money-free, unshipped order means the order should never have
            // existed — a mistake, a test, a duplicate — so its effect on stock should
            // not exist either. Leaving that to the admin ("cancel first, then delete")
            // was a rule they had to remember, and forgetting it lost the stock
            // permanently: the order was gone, with nothing left to trace it to.
            //
            // Safe to call unconditionally: returnStockToInventory() is a no-op when the
            // goods are already back, so an order that was cancelled and then deleted
            // does not get credited twice (ADR shop-admin_order-cancel-vs-delete D4).
            $order->returnStockToInventory();

            // Leave a last audit line before the order goes. History is INTENTIONALLY
            // kept (tombstone): the most destructive action must leave a trace of who
            // did it. Rows are orphaned relative to shop_order on purpose — consumers
            // read history with a LEFT JOIN (ADR shop-admin_order-history-tombstone).
            $order->addOrderHistory([
                'order_id' => $order->id,
                'content' => 'Order deleted',
                'admin_id' => (function_exists('admin') && admin()->user()) ? admin()->user()->id : 0,
                'order_status_id' => (int) $order->status,
            ]);

            // The cascade stays for the money/line children: deletion is permanent, so
            // they must go with the parent or become orphans nothing can reach.
            // Query-builder deletes, per the contract above — going through Eloquent
            // would fire ShopOrderDetail::deleting and restore stock a second time.
            // history() is NOT cascaded — see the tombstone note above.
            $order->details()->delete();
            $order->orderTotal()->delete();
        });

        //Uuid
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'OD-'.gp247_token(8);
            }
        });
    }


    /**
     * Whether this order is a finalised document whose STRUCTURE must not change.
     *
     * Done/Refunded/Canceled are chốt: editing the header, lines or fee rows would
     * corrupt a document downstream relies on for reconciliation / invoice / refund
     * (ADR shop-admin_order-finalized-lock). This gates STRUCTURE only — changing the
     * status (reopening) and recording payments/refunds stay allowed; reopening is the
     * legitimate way to make an order editable again.
     *
     * @return bool True when the order is finalised.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-finalized-lock
     * @aidlc-adr shop-admin_order-finalized-lock
     */
    public function isLocked(): bool
    {
        return in_array(
            (int) $this->status,
            [ShopOrderStatus::DONE, ShopOrderStatus::REFUNDED, ShopOrderStatus::CANCELED],
            true
        );
    }

    /**
     * The single seam every order-status transition goes through.
     *
     * Carries ALL transition side effects, so no caller can bypass them again:
     * entering Canceled returns the goods to stock (idempotent via
     * stock_returned_at), leaving Canceled takes them back and can REFUSE when
     * stock is short, a history row is written, the update-status event fires,
     * and the Done(5) revenue hooks fire in both directions. Before this seam,
     * only the Livewire admin screen had the stock/hook wiring — the gateway
     * cancel path and updateStatus() silently skipped all of it
     * (RISK-BIZ-cancel-path-bypass-restock).
     *
     * Same status in and out is a no-op (no history noise, no events).
     *
     * @param int   $newStatus Target ShopOrderStatus id.
     * @param array $history   Optional: content, admin_id, customer_id.
     * @return string|null Null on success; an i18n key naming the block reason
     *                     when the transition is refused (status unchanged).
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-cancel-restock
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public function changeStatus(int $newStatus, array $history = []): ?string
    {
        $old = (int) $this->status;
        if ($old === $newStatus) {
            return null;
        }

        if ($old !== ShopOrderStatus::CANCELED && $newStatus === ShopOrderStatus::CANCELED) {
            $this->returnStockToInventory();
        }
        if ($old === ShopOrderStatus::CANCELED && $newStatus !== ShopOrderStatus::CANCELED) {
            // takeStockBack() is all-or-nothing: false with stock_returned_at
            // still set means "not enough stock", so the transition must be
            // refused BEFORE the status is written — otherwise the order reads
            // as live while its goods are still in the warehouse.
            if (!$this->takeStockBack() && $this->stock_returned_at !== null) {
                return 'admin.order.reopen_blocked_stock';
            }
        }

        $this->update(['status' => $newStatus]);

        $content = trim((string) ($history['content'] ?? ''));
        if ($content === '') {
            $content = 'Change <b>status</b> from \'' . $old . '\' to \'' . $newStatus . '\'';
        }
        $this->addOrderHistory([
            'order_id' => $this->id,
            'content' => $content,
            'admin_id' => $history['admin_id'] ?? 0,
            'customer_id' => $history['customer_id'] ?? 0,
            'order_status_id' => $newStatus,
        ]);

        gp247_event_order_update_status($this);

        // WHY function_exists: optional template/plugin hooks (e.g. vendor
        // revenue settlement) — fired from the seam so EVERY path settles,
        // not just the admin screen.
        if ($old !== ShopOrderStatus::DONE && $newStatus === ShopOrderStatus::DONE && function_exists('gp247_order_success_finish')) {
            gp247_order_success_finish($this->id);
        }
        if ($old === ShopOrderStatus::DONE && $newStatus !== ShopOrderStatus::DONE && function_exists('gp247_order_success_unfinish')) {
            gp247_order_success_unfinish($this->id);
        }

        return null;
    }

    /**
     * Update the status of an order by id — legacy plugin API.
     *
     * Kept with its original signature for backward compatibility; delegates
     * to changeStatus() so the stock/history/event/hook side effects apply on
     * this path too (they never did before). A refused re-open (stock short)
     * leaves the status unchanged, mirroring the admin screen.
     *
     * @param [type] $orderId
     * @param integer $status
     * @param array $history Optional: content, user_id, admin_id.
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-cancel-restock
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public function updateStatus($orderId, $status = 0, $history = [])
    {
        $order = $this->find($orderId);
        if ($order) {
            $order->changeStatus((int) $status, [
                'content' => $history['content'] ?? '',
                'customer_id' => $history['user_id'] ?? 0,
                'admin_id' => $history['admin_id'] ?? 0,
            ]);
        }
    }


    public function scopeSort($query, $sortBy = null, $sortOrder = 'asc')
    {
        $sortBy = $sortBy ?? 'sort';
        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Create new order
     * @param  [array] $dataOrder
     * @param  [array] $dataTotal
     * @param  [array] $arrCartDetail
     * @return [array]
     */
    public function createOrder($dataOrder, $dataTotal, $arrCartDetail)
    {
        //Process escape
        $dataOrder     = gp247_clean($dataOrder);
        $dataTotal     = gp247_clean($dataTotal);
        $arrCartDetail = gp247_clean($arrCartDetail);
        try {
            DB::connection(GP247_DB_CONNECTION)->beginTransaction();
            $dataOrder['domain'] = url('/');
            $uID = $dataOrder['customer_id'] ?? 0;
            $adminID = $dataOrder['admin_id'] ?? 0;
            unset($dataOrder['admin_id']);
            $currency = $dataOrder['currency'];
            $exchange_rate = $dataOrder['exchange_rate'];

            //Insert order
            $order = ShopOrder::create($dataOrder);
            $orderID = $order->id;
            //End insert order

            //Insert order total
            foreach ($dataTotal as $key => $row) {
                $row = gp247_clean($row);
                $row['id'] = gp247_generate_id();
                $row['order_id'] = $orderID;
                $row['created_at'] = gp247_time_now();
                $dataTotal[$key] = $row;
            }
            ShopOrderTotal::insert($dataTotal);
            //End order total

            //Order detail
            foreach ($arrCartDetail as $cartDetail) {
                $pID = $cartDetail['product_id'];
                $product = ShopProduct::find($pID);
                
                //Check product flash sale over stock
                if (function_exists('gp247_product_flash_check_over') && !gp247_product_flash_check_over($pID, $cartDetail['qty'])) {
                    throw new \Exception(gp247_language_render('cart.item_over_qty', ['sku' => $product->sku, 'qty' => $cartDetail['qty']]));
                }

                //If product out of stock — unified predicate shared with admin
                // (ADR shop-admin_order-stock-parity, revised 2026-08-16 / modification
                // 20260816T175134). hasStockForOrder() also honours product_stock, so
                // this no longer blocks when stock management is off (prior inline bug).
                if (!$product->hasStockForOrder($cartDetail['qty'])) {
                    throw new \Exception(gp247_language_render('cart.item_over_qty', ['sku' => $product->sku, 'qty' => $cartDetail['qty']]));
                }
                // WHY: line-level tax on the same basis as the cart total, so
                // shop_order.tax equals Σ shop_order_detail.tax (ADR
                // shop-admin_tax-standardization, D2/D4). Since modification
                // 20260820T232338 the incoming price IS the effective unit price
                // (attribute surcharges included — ADR
                // storefront_order-line-effective-price), so the old re-add of
                // option prices from the attribute JSON is gone: the JSON is
                // descriptive only and re-adding it would double-count.
                $tax = gp247_line_tax($cartDetail['price'], $cartDetail['qty'], $product->getTaxValue());

                $cartDetail['order_id'] = $orderID;
                $cartDetail['currency'] = $currency;
                $cartDetail['exchange_rate'] = $exchange_rate;
                $cartDetail['sku'] = $product->sku;
                $cartDetail['tax'] = $tax;
                // Freeze the RATE on the document, not just the amount. Without it the
                // tax cannot be recomputed once a discount moves the base — and an order
                // placed today would silently change its tax the day someone edits the
                // product's tax (ADR shop-admin_order-discount-pre-tax D5).
                $cartDetail['tax_rate'] = (float) $product->getTaxValue();
                $cartDetail['store_id'] = $cartDetail['store_id'];
                $cartDetail['attribute'] = json_encode($cartDetail['attribute']);
                $this->addOrderDetail($cartDetail);

                //Update stock flash sale
                if (function_exists('gp247_product_flash_update_stock')) {
                    gp247_product_flash_update_stock($pID, $cartDetail['qty']);
                }

                //Update stock and sold — atomic decrement. A false result means a
                //concurrent order took the stock after hasStockForOrder() above; throw
                //so the whole order rolls back rather than overselling
                //(ADR compat-foundation_atomic-stock-movement).
                if (!ShopProduct::updateStock($pID, $cartDetail['qty'])) {
                    throw new \Exception(gp247_language_render('cart.item_over_qty', ['sku' => $product->sku, 'qty' => $cartDetail['qty']]));
                }
            }
            //End order detail

            // Split the discount across the lines and re-tax each on what is left. Done
            // here, from the persisted rows, rather than trusting the figures computed
            // upstream: both use the same allocation, but only this one is guaranteed to
            // see the lines in the order they were actually stored — and the remainder
            // cent has to land on the same line in both places or the invariant
            // `Σ line discount = order discount` would hold only by luck (D7).
            $order->refresh();
            // Use the result: write the reallocated subtotal/tax/discount back onto the
            // header + total rows so the invariant shop_order.tax = Σ detail.tax holds
            // from the instant the order exists, not only after the first admin edit
            // (RISK-BIZ-order-header-line-drift). Previously this call's result was
            // discarded on the storefront path.
            $sums = $order->reallocateDiscountAndTax();
            $order->syncOrderTotals($sums);

            //Add history
            $dataHistory = [
                'order_id' => $orderID,
                'content' => 'New order',
                'customer_id' => $uID,
                'admin_id' => $adminID,
                'order_status_id' => $order->status,
            ];
            $this->addOrderHistory($dataHistory);

            //Process Discount
            $totalMethod = session('totalMethod') ?? [];
            foreach ($totalMethod as $keyPlugin => $codeApply) {
                if ($codeApply) {
                    $moduleClass = gp247_extension_get_namespace(type: 'Plugins', key: $keyPlugin);
                    $moduleClass = $moduleClass . '\Controllers\FrontController';
                    //Check class exist
                    if (class_exists($moduleClass) && method_exists($moduleClass, 'apply')) {
                        $arrReturnModuleDiscount = (new $moduleClass)->apply($codeApply, $uID, $msg = 'Order #' . $orderID);
                        if ($arrReturnModuleDiscount['error'] == 1) {
                            throw new \Exception($arrReturnModuleDiscount['msg']);
                        }
                    }
                }
            }
            // End process Discount

            DB::connection(GP247_DB_CONNECTION)->commit();

            // Process event created
            gp247_event_order_created($order);

            $return = ['error' => 0, 'orderID' => $orderID, 'msg' => "", 'detail' => $order];
        } catch (\Throwable $e) {
            DB::connection(GP247_DB_CONNECTION)->rollBack();

            // Concurrent double-submit: the unique index on checkout_token rejected the
            // second insert. Treat it as success by replaying the order that won the
            // race, so the loser sees the same order-success page instead of an error
            // (US-LW-checkout-idempotency, ADR storefront_checkout-idempotency).
            $token = $dataOrder['checkout_token'] ?? null;
            if ($token) {
                $existing = self::where('checkout_token', $token)->first();
                if ($existing) {
                    return ['error' => 0, 'orderID' => $existing->id, 'msg' => '', 'detail' => $existing, 'replayed' => true];
                }
            }

            $return = ['error' => 1, 'msg' => $e->getMessage()];
        }
        return $return;
    }

    /**
     * Add order history
     * @param [array] $dataHistory
     */
    public function addOrderHistory($dataHistory)
    {
        return ShopOrderHistory::create($dataHistory);
    }

    /**
     * Add order detail
     * @param [type] $dataDetail [description]
     */
    public function addOrderDetail($dataDetail)
    {
        return ShopOrderDetail::create($dataDetail);
    }


    /**
     * Start new process get data
     *
     * @return  new model
     */
    public function start()
    {

        return new ShopOrder;

    }

    /**
     * Get order detail
     *
     * @param   [int]  $orderID
     *
     */
    public function getDetail($orderID)
    {
        if (empty($orderID)) {
            return null;
        }
        $customer = customer()->user();
        if ($customer) {
            return $this->where('id', $orderID)
                ->where('customer_id', $customer->id)
                ->first();
        } else {
            return null;
        }
    }


    public function setCustomerId($customerId)
    {
        $this->gp247_customer_id = $customerId;
        return $this;
    }



    /**
     * Get list order new
     */
    public function getOrderNew()
    {
        $this->gp247_status = 1;
        return $this;
    }

    /**
     * Get list order processing
     */
    public function getOrderProcessing()
    {
        $this->gp247_status = 2;
        return $this;
    }

    /**
     * Get list order hold
     */
    public function getOrderHold()
    {
        $this->gp247_status = 3;
        return $this;
    }

    /**
     * Get list order canceld
     */
    public function getOrderCanceled()
    {
        $this->gp247_status = 4;
        return $this;
    }

    /**
     * Get list order done
     */
    public function getOrderDone()
    {
        $this->gp247_status = 5;
        return $this;
    }

    /**
     * Get list order failed
     */
    public function getOrderFailed()
    {
        $this->gp247_status = 6;
        return $this;
    }

    /**
     * Get list order refunded
     */
    public function getOrderRefunded()
    {
        $this->gp247_status = 7;
        return $this;
    }

    /**
     * build Query
     */
    public function buildQuery()
    {
        if ($this->gp247_customer_id != null) {
            $query = $this->with('orderTotal')->where('customer_id', $this->gp247_customer_id);
        } else {
            $query = $this->with('orderTotal')->with('details');
        }

        if ($this->gp247_status != null) {
            $query = $query->where('status', $this->gp247_status);
        }

        $query = $this->processMoreQuery($query);
        

        if ($this->random) {
            $query = $query->inRandomOrder();
        } else {
            if (is_array($this->gp247_sort) && count($this->gp247_sort)) {
                foreach ($this->gp247_sort as  $rowSort) {
                    if (is_array($rowSort) && count($rowSort) == 2) {
                        $query = $query->sort($rowSort[0], $rowSort[1]);
                    }
                }
            } else {
                $query = $query->orderBy('created_at', 'desc');
            }
        }

        return $query;
    }

    /**
     * The order's payment ledger — every payment and refund, oldest first.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(ShopOrderTransaction::class, 'order_id', 'id');
    }

    /**
     * Record money COLLECTED from the customer.
     *
     * This is the seam every payment path goes through — gateway capture, an admin
     * taking money by hand, an accountant recording a receipt against the order. It is
     * deliberately the only way `received` moves (NFR-MAINT-order-received-derived):
     * before the ledger existed, `received` was written directly in several places and a
     * gateway capture wrote it nowhere at all, so a paid order still showed the full
     * balance outstanding.
     *
     * Idempotent on the gateway reference: replaying a webhook, or a shopper
     * double-submitting, records the money once (NFR-SEC-payment-idempotency). The
     * caller does not have to guard — and must not rely on an exception to notice,
     * since the duplicate is a normal event, not an error.
     *
     * @param float       $amount   Money taken, in the ORDER's currency. Non-positive is ignored.
     * @param string|null $method   Payment plugin key, or null when taken by hand.
     * @param string|null $txnId    Gateway reference; the idempotency key when present.
     * @param mixed       $paidAt   When the money moved (any Carbon-parsable value); defaults to now.
     * @param string|null $note
     * @param mixed       $adminId  Admin who recorded it, when it was recorded by hand.
     * @return ShopOrderTransaction|null The row, or the existing one when this was a replay.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-payment-ledger
     * @aidlc-adr shop_order-payment-ledger
     */
    public function recordPayment(
        float $amount,
        ?string $method = null,
        ?string $txnId = null,
        $paidAt = null,
        ?string $note = null,
        $adminId = null
    ): ?ShopOrderTransaction {
        return $this->recordMovement(ShopOrderTransaction::TYPE_PAYMENT, $amount, $method, $txnId, $paidAt, $note, $adminId);
    }

    /**
     * Record money GIVEN BACK to the customer.
     *
     * A refund is one row in the same ledger, not a flag on the order: refunding part of
     * an order used to be inexpressible, so a partial refund flipped the whole order to
     * "refunded" and the amount was stored nowhere. Status follows the money — an order
     * still holding collected money does not become fully refunded.
     *
     * @param float       $amount  Money returned, in the ORDER's currency. Non-positive is ignored.
     * @param string|null $method
     * @param string|null $txnId   Gateway refund reference; the idempotency key when present.
     * @param mixed       $paidAt
     * @param string|null $note
     * @param mixed       $adminId
     * @return ShopOrderTransaction|null
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-payment-ledger
     * @aidlc-adr shop_order-payment-ledger
     */
    public function recordRefund(
        float $amount,
        ?string $method = null,
        ?string $txnId = null,
        $paidAt = null,
        ?string $note = null,
        $adminId = null
    ): ?ShopOrderTransaction {
        return $this->recordMovement(ShopOrderTransaction::TYPE_REFUND, $amount, $method, $txnId, $paidAt, $note, $adminId);
    }

    /**
     * Write one ledger row, then re-derive the order's money.
     *
     * @param string      $type    ShopOrderTransaction::TYPE_*.
     * @param float       $amount
     * @param string|null $method
     * @param string|null $txnId
     * @param mixed       $paidAt
     * @param string|null $note
     * @param mixed       $adminId
     * @return ShopOrderTransaction|null
     */
    private function recordMovement(
        string $type,
        float $amount,
        ?string $method,
        ?string $txnId,
        $paidAt,
        ?string $note,
        $adminId
    ): ?ShopOrderTransaction {
        // A zero or negative movement is not money changing hands. Refusing it here keeps
        // the magnitude invariant (L1) true by construction rather than by convention.
        if ($amount <= 0) {
            return null;
        }

        $txnId = ($txnId === '') ? null : $txnId;

        if (ShopOrderTransaction::alreadyRecorded($txnId)) {
            return ShopOrderTransaction::where('gateway_transaction_id', $txnId)->first();
        }

        // WHY the snapshot: the row must stay readable after the catalogue's rates move,
        // and `amount_base` is fixed here so aggregate reports never re-convert — which
        // is what silently mixes units once the base currency is rebased.
        $rate = (float) ($this->exchange_rate ?: 0);
        $movement = ShopOrderTransaction::create([
            'order_id' => $this->id,
            'type' => $type,
            'amount' => $amount,
            'amount_base' => round($rate > 0 ? $amount / $rate : $amount, 2),
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'method' => $method,
            'gateway_transaction_id' => $txnId,
            'paid_at' => $paidAt ?: gp247_time_now(),
            'admin_id' => $adminId,
            'customer_id' => $this->customer_id,
            'note' => $note,
        ]);

        $this->recalcReceived();

        return $movement;
    }

    /**
     * Re-derive `received`, `balance` and `payment_status` from the ledger.
     *
     * `received` is kept as a column on purpose — a cache, so invoices, order lists and
     * reporting plugins keep reading what they always read. The cost of that choice is
     * that the column CAN drift from the ledger if anything writes it directly, which is
     * why every writer must come through recordPayment()/recordRefund()
     * (RISK-TECH-received-derivation-drift).
     *
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-payment-ledger
     * @aidlc-adr shop_order-payment-ledger
     */
    public function recalcReceived(): void
    {
        $received = ShopOrderTransaction::netReceived($this->id);
        $balance = (float) $this->total - $received;

        $this->received = $received;
        $this->balance = $balance;
        $this->payment_status = ShopPaymentStatus::deriveFrom($received, $balance);
        $this->save();
    }

    /**
     * Mark the order as paid in full.
     *
     * @deprecated Since modification 20260829T144711. Use recordPayment() with the amount
     *             the gateway actually captured: this shortcut cannot express a deposit,
     *             a partial capture or an amount that differs from the order total, and
     *             it carries no gateway reference, so replaying it is not idempotent.
     *             Kept as a thin alias so existing payment plugins keep working.
     *
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-payment-ledger
     * @aidlc-adr shop_order-payment-ledger
     */
    public function processPaymentPaid()
    {
        $outstanding = (float) $this->total - ShopOrderTransaction::netReceived($this->id);
        if ($outstanding > 0) {
            $this->recordPayment($outstanding, $this->payment_method, null, null, 'processPaymentPaid() (deprecated)');

            return;
        }

        // Already settled (or overpaid) — nothing to collect, but keep the derived
        // columns honest in case they were written before the ledger existed.
        $this->recalcReceived();
    }
}
