<?php
namespace GP247\Shop\Models;

use GP247\Shop\Models\ShopCurrency;
use Illuminate\Database\Eloquent\Model;

class ShopOrderTotal extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;

    protected $table = GP247_DB_PREFIX.'shop_order_total';
    protected $connection = GP247_DB_CONNECTION;
    protected $guarded = [];
    const POSITION_SUBTOTAL = 1;
    const POSITION_TAX = 2;
    const POSITION_SHIPPING_METHOD = 10;
    const POSITION_TOTAL_METHOD = 20;
    const POSITION_OTHER_FEE = 80;
    const POSITION_TOTAL = 100;
    /**
     * @deprecated Since modification 20260829T094327: `received` is a PAYMENT, not a
     * component of the order document, so it is no longer written as a shop_order_total
     * row (ADR shop-admin_money-sign-convention D3). Kept only so third-party code
     * referencing the constant keeps parsing; nothing in core emits this sort any more.
     */
    const POSITION_RECEIVED = 200;
    /**
     * Payment-status aliases, kept so existing call sites and third-party code keep
     * resolving. They now point at ShopPaymentStatus, whose values are the row ids
     * of shop_payment_status.
     *
     * THE VALUES CHANGED (0-3 -> 1-4) — that IS the fix. These constants used to be
     * one off against the table they describe, so an order recalculated by
     * AdminOrder::updateSubTotal() displayed one status too low: fully paid read as
     * "Partial payment", awaiting refund read as "Paid", and unpaid was stored as 0,
     * matching no row at all. InOut's debt filter compared against the wrong id and
     * therefore excluded the wrong orders (RISK-BIZ-payment-status-scale-split).
     *
     * They also sat in the wrong model: payment status belongs to the status table,
     * not to the order-totals row — which is likely why nobody ever checked them
     * against the seed.
     *
     * @deprecated Use ShopPaymentStatus::{UNPAID,PARTIAL,PAID,REFUND}
     *             (ADR shop-admin_payment-status-enum-alignment).
     */
    const NOT_YET_PAY = ShopPaymentStatus::UNPAID;
    const PART_PAY = ShopPaymentStatus::PARTIAL;
    const PAID = ShopPaymentStatus::PAID;
    const NEED_REFUND = ShopPaymentStatus::REFUND;

    /**
     * ╔══════════════════════════════════════════════════════════════════════╗
     * ║  SIGN MAP — THE SINGLE SOURCE OF SIGN FOR ORDER MONEY                ║
     * ╠══════════════════════════════════════════════════════════════════════╣
     * ║  Contract (ADR shop-admin_money-sign-convention, D1/D2):             ║
     * ║                                                                      ║
     * ║  D1 — every stored money value is a NON-NEGATIVE MAGNITUDE:          ║
     * ║       shop_order.{discount,received,shipping,other_fee} >= 0 AND     ║
     * ║       shop_order_total.value >= 0. No exceptions, no per-layer       ║
     * ║       variation.                                                     ║
     * ║  D2 — the SIGN lives in the formula, declared once, here:            ║
     * ║       total = subtotal + tax + Σ (SIGN_MAP[code] × value)            ║
     * ║             = subtotal + tax + shipping + other_fee − discount       ║
     * ║                                                                      ║
     * ║  Every place that sums order money reads this map — processDataTotal ║
     * ║  (storefront), AdminOrderController::postCreate (admin create),      ║
     * ║  AdminOrder::{updateSubTotal,updateRowOrderTotal} (admin edit).      ║
     * ║  NEVER re-express a sign inline: two parallel conventions is exactly ║
     * ║  the defect this replaces (RISK-BIZ-order-sign-split — a discount    ║
     * ║  silently turned into a surcharge when an admin-created order was    ║
     * ║  edited).                                                            ║
     * ║                                                                      ║
     * ║  D3 — `received` is NOT in this map and NOT part of `total`; it is   ║
     * ║       a payment: balance = total − received.                         ║
     * ║  D5 — plugins (total-method / fee / shipping) return a POSITIVE      ║
     * ║       'value'; the sign is applied here, not by the plugin.          ║
     * ╚══════════════════════════════════════════════════════════════════════╝
     *
     * @var array<string, int>
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-money-sign-convention
     * @aidlc-adr shop-admin_money-sign-convention
     */
    public const SIGN_MAP = [
        'shipping'  => 1,
        'other_fee' => 1,
        'discount'  => -1,
    ];

    /**
     * Sign of one total row, by `code` (see SIGN_MAP).
     *
     * Unknown codes (a third-party plugin adding its own line) default to +1: an
     * unrecognised charge adds to the order, which is the safe reading — a plugin
     * that means "deduct" must register its code in SIGN_MAP.
     *
     * @param string $code Total-row code (subtotal|tax|shipping|discount|other_fee|…).
     * @return int 1 or -1.
     */
    public static function signOf(string $code): int
    {
        return self::SIGN_MAP[$code] ?? 1;
    }

    /**
     * Get object total for order
     * Step 1
     */
    public static function getObjectOrderTotal()
    {
        $objects = array();
        $objects[] = self::getShippingMethod();
        foreach (self::getTotal() as  $totalMethod) {
            $objects[] = $totalMethod;
        }
        foreach (self::getOtherFee() as  $otherFeeMethod) {
            $objects[] = $otherFeeMethod;
        }
        // WHY no `received` row: it is a PAYMENT, not a component of the order
        // document, so it no longer belongs to the totals ledger (ADR
        // shop-admin_money-sign-convention D3). It lives on shop_order.received
        // alone, with balance = total − received.
        return $objects;
    }
    
    /**
     * Process data order total
     * @param  array      $objects  [description]
     * Step 2
     * @return [array]    order total after process
     */
    public static function processDataTotal(array $objects = [])
    {
        $carts  = ShopCurrency::sumCartCheckout();
        $subtotal = $carts['subTotal'];

        // Tax is charged on what is left after the discount, not on the sticker price.
        // Doing it the other way round — the behaviour this replaces — made the customer
        // pay tax on money they never spent, and left shop_order.tax describing a price
        // nobody was charged (audit F5, ADR shop-admin_order-discount-pre-tax D1/D4).
        //
        // Note what does NOT change for a percentage coupon: the total. (S+T)(1−r) and
        // Σ Sᵢ(1−r)(1+tᵢ) are the same number, even across different rates. What changes
        // is the tax figure — the one that goes on a tax return. A FIXED-amount coupon
        // is the case where the customer genuinely pays less.
        $discountValue = 0.0;
        foreach ($objects as $object) {
            if (is_array($object) && ($object['code'] ?? '') === 'discount') {
                $discountValue += (float) $object['value'];
            }
        }
        $shares = gp247_allocate_discount(
            array_column($carts['lines'] ?? [], 'subtotal'),
            $discountValue
        );
        $tax = 0.0;
        foreach (($carts['lines'] ?? []) as $index => $line) {
            $tax += round(((float) $line['subtotal'] - (float) ($shares[$index] ?? 0)) * (float) $line['rate'] / 100, 2);
        }

        //Set subtotal
        $arraySubtotal = [
            'title' => gp247_language_render('order.totals.sub_total'),
            'code' => 'subtotal',
            'value' => $subtotal,
            'text' => gp247_currency_render_symbol($subtotal),
            'sort' => self::POSITION_SUBTOTAL,
        ];

        //Set tax
        $arrayTax = [
            'title' => gp247_language_render('order.totals.tax'),
            'code' => 'tax',
            'value' => $tax,
            'text' => gp247_currency_render_symbol($tax),
            'sort' => self::POSITION_TAX,
        ];

        // set total value
        // WHY signOf(): the sign comes from the SIGN_MAP contract, never from the
        // stored value — every row carries a non-negative magnitude (D1/D2). A
        // `received` row can only appear on a legacy order; it is skipped because
        // a payment is not part of the document total (D3).
        $total = $subtotal + $tax;
        foreach ($objects as $key => $object) {
            if (is_array($object) && $object) {
                if ($object['code'] !== 'received') {
                    $total += self::signOf($object['code']) * $object['value'];
                }
                // Presentation: a deducting line is shown with a minus even though the
                // value stored and returned by the plugin is positive (D4). Done here,
                // once, so every checkout/cart view that prints `text` agrees — and so a
                // plugin author never has to think about the sign.
                if (self::signOf($object['code']) < 0 && (float) $object['value'] != 0) {
                    $objects[$key]['text'] = '-' . gp247_currency_render_symbol($object['value']);
                }
            } else {
                unset($objects[$key]);
            }
        }

        $arrayTotal = array(
            'title' => gp247_language_render('order.totals.total'),
            'code' => 'total',
            'value' => $total,
            'text' => gp247_currency_render_symbol($total),
            'sort' => self::POSITION_TOTAL,
        );
        //End total value

        $objects[] = $arraySubtotal;
        $objects[] = $arrayTax;
        $objects[] = $arrayTotal;

        //re-sort item total
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
     * Get sum value in order total
     * @param  string $code      [description]
     * @param  array $dataTotal [description]
     * @return int            [description]
     */
    public function sumValueTotal($code, $dataTotal)
    {
        $keys = array_keys(array_column($dataTotal, 'code'), $code);
        $value = 0;
        foreach ($keys as $object) {
            $value += $dataTotal[$object]['value'];
        }
        return $value;
    }

    /**
     * Get shipping method
     */
    public static function getShippingMethod()
    {
        $arrShipping = [];
        $shippingMethod = session('shippingMethod') ?? '';
        if ($shippingMethod) {
            $moduleClass = gp247_extension_get_namespace(type: 'Plugins', key: $shippingMethod);
            $moduleClass = $moduleClass . '\AppConfig';
            if (class_exists($moduleClass)) {
                $returnModuleShipping = (new $moduleClass)->getInfo();
                // Plugin money contract (ADR storefront_total-method-currency-contract):
                // a total-line plugin returns `value` as a positive magnitude in the
                // DISPLAY currency; core applies the sign via SIGN_MAP and does NOT
                // convert. This used to be the one asymmetric point (it converted while
                // getTotal/getOtherFee did not) — now ShippingStandard::getInfo() does
                // its own base→display conversion, so `value` is already display-currency.
                $arrShipping = [
                    'title' => $returnModuleShipping['title'],
                    'code' => 'shipping',
                    'value' => $returnModuleShipping['value'],
                    'text' => gp247_currency_render_symbol($returnModuleShipping['value']),
                    'sort' => self::POSITION_SHIPPING_METHOD,
                ];
            }
        }
        return $arrShipping;
    }

    /**
     * Get payment method
     */
    public static function getPaymentMethod()
    {
        $arrPayment = [];
        $paymentMethod = session('paymentMethod') ?? [];
        if ($paymentMethod) {
            $moduleClass = gp247_extension_get_namespace(type: 'Plugins', key: $paymentMethod);
            $moduleClass = $moduleClass . '\AppConfig';
            if (class_exists($moduleClass)) {
                $returnModulePayment = (new $moduleClass)->getInfo();
                $arrPayment = [
                    'title' => $returnModulePayment['title'],
                    'method' => $paymentMethod,
                ];
            }
        }
        return $arrPayment;
    }

    /**
     * Get total method
     */
    public static function getTotal()
    {
        $totalMethod = [];

        $totalMethod = session('totalMethod', []);
        if ($totalMethod && is_array($totalMethod)) {
            foreach ($totalMethod as $keyMethod => $valueMethod) {
                $classTotalConfig = gp247_extension_get_namespace(type: 'Plugins', key: $keyMethod);
                $classTotalConfig = $classTotalConfig . '\AppConfig';
                if (class_exists($classTotalConfig)) {
                    $returnModuleTotal = (new $classTotalConfig)->getInfo();
                    // Money contract: plugin returns `value` as a positive magnitude in
                    // the DISPLAY currency; core does not convert (ADR
                    // storefront_total-method-currency-contract).
                    $totalMethod[] = [
                        'title' => $returnModuleTotal['title'],
                        'code' => 'discount',
                        'value' => $returnModuleTotal['value'],
                        'text' => gp247_currency_render_symbol($returnModuleTotal['value']),
                        'sort' => self::POSITION_TOTAL_METHOD,
                    ];
                }
            }
        }
        if (!count($totalMethod)) {
            $totalMethod[] = array(
                'title' => gp247_language_render('order.totals.discount'),
                'code' => 'discount',
                'value' => 0,
                'text' => 0,
                'sort' => self::POSITION_TOTAL_METHOD,
            );
        }
        return $totalMethod;
    }

    /**
     * Get amount total
     *
     * @return  [type]  [return description]
     */
    public static function getAmountTotal() {
        $amount = 0;
        $carts  = ShopCurrency::sumCartCheckout();
        $shipping = self::getShippingMethod();
        $amount += $carts['subTotalWithTax'] ?? 0;
        $amount += $shipping['value'] ?? 0;
        // WHY signOf(): this is the amount payment gateways charge. Total-method
        // plugins now return a POSITIVE magnitude (D5), so the sign MUST be applied
        // here — summing raw values would charge the customer the discount instead
        // of deducting it (ADR shop-admin_money-sign-convention D2).
        foreach (self::getTotal() as  $totalMethod) {
            if (!is_array($totalMethod)) {
                continue;
            }
            $amount += self::signOf($totalMethod['code'] ?? '') * ($totalMethod['value'] ?? 0);
        }
        return $amount;
    }

    /**
     * Get amount total without shipping
     *
     * @return  [type]  [return description]
     */
    public static function getAmountTotalWithoutShipping() {
        $amount = 0;
        $carts  = ShopCurrency::sumCartCheckout();
        $amount += $carts['subTotalWithTax'] ?? 0;
        // WHY signOf(): same contract as getAmountTotal() — positive magnitudes in,
        // sign applied here (ADR shop-admin_money-sign-convention D2/D5).
        foreach (self::getTotal() as  $totalMethod) {
            if (!is_array($totalMethod)) {
                continue;
            }
            $amount += self::signOf($totalMethod['code'] ?? '') * ($totalMethod['value'] ?? 0);
        }
        return $amount;
    }


    /**
     * Get received value
     *
     * @deprecated Since modification 20260829T094327. `received` is a payment, not a
     * total row: it is no longer part of getObjectOrderTotal() and no new order gets a
     * `received` row (ADR shop-admin_money-sign-convention D3). Read shop_order.received
     * instead. Kept so third-party callers keep working; core calls it nowhere.
     */
    public static function getReceived()
    {
        return array(
            'title' => gp247_language_render('order.totals.received'),
            'code' => 'received',
            'value' => 0,
            'text' => 0,
            'sort' => self::POSITION_RECEIVED,
        );
    }

    /**
     * Get other fee value
     */
    public static function getOtherFee()
    {
        $otherFeeMethod = [];

        $checkOtherFeeMethod = gp247_extension_get_via_code(code: 'Fee');
        if (count($checkOtherFeeMethod)) {
            foreach ($checkOtherFeeMethod as $keyMethod => $valueMethod) {
                $classOtherFeeConfig = gp247_extension_get_namespace(type: 'Plugins', key: $keyMethod);
                $classOtherFeeConfig = $classOtherFeeConfig . '\AppConfig';
                //Check class config exist
                if (class_exists($classOtherFeeConfig)) {
                    $returnModuleOtherFee = (new $classOtherFeeConfig)->getInfo();
                    // Money contract: plugin returns `value` as a positive magnitude in
                    // the DISPLAY currency; core does not convert (ADR
                    // storefront_total-method-currency-contract).
                    $otherFeeMethod[] = [
                        'title' => $returnModuleOtherFee['title'],
                        'code' => 'other_fee',
                        'value' => $returnModuleOtherFee['value'],
                        'text' => gp247_currency_render_symbol($returnModuleOtherFee['value']),
                        'sort' => self::POSITION_OTHER_FEE,
                    ];
                }
            }
        }

        if (!count($otherFeeMethod)) {
            $otherFeeMethod[] = array(
                'title' => gp247_language_render('order.totals.other_fee'),
                'code' => 'other_fee',
                'value' => 0,
                'text' => 0,
                'sort' => self::POSITION_OTHER_FEE,
            );
        }

        return $otherFeeMethod;
    }


    protected static function boot()
    {
        parent::boot();
        // before delete() method call this
        static::deleting(
            function ($model) {
                //
            }
        );

        //Uuid
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = gp247_generate_id();
            }
        });
    }
}
