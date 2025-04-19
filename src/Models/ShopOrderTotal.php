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
    const POSITION_RECEIVED = 200;
    const NOT_YET_PAY = 0;
    const PART_PAY = 1;
    const PAID = 2;
    const NEED_REFUND = 3;

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
        $objects[] = self::getReceived();
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
        $tax = $carts['subTotalWithTax'] - $carts['subTotal'];

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
        $total = $subtotal + $tax;
        foreach ($objects as $key => $object) {
            if (is_array($object) && $object) {
                if ($object['code'] != 'received') {
                    $total += $object['value'];
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
                $arrShipping = [
                    'title' => $returnModuleShipping['title'],
                    'code' => 'shipping',
                    'value' => gp247_currency_value($returnModuleShipping['value']),
                    'text' => gp247_currency_render($returnModuleShipping['value']),
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
        foreach (self::getTotal() as  $totalMethod) {
            $amount += $totalMethod['value'] ?? 0;
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
        foreach (self::getTotal() as  $totalMethod) {
            $amount += $totalMethod['value'] ?? 0;
        }
        return $amount;
    }


    /**
     * Get received value
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
