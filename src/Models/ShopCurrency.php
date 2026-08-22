<?php
/**
 * @author Lanh Le <lanhktc@gmail.com>
 */
namespace GP247\Shop\Models;

use Cart;
use GP247\Shop\Services\CartItem;
use Illuminate\Database\Eloquent\Model;

class ShopCurrency extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    public $table = GP247_DB_PREFIX.'shop_currency';
    protected static $code              = '';
    protected static $name              = '';
    protected static $symbol            = '';
    protected static $exchange_rate     = 1;
    protected static $precision         = 2;
    protected static $symbol_first      = 0;
    protected static $thousands         = ',';
    protected static $decimal           = '.';
    protected static $list              = null;
    protected static $getArray          = null;
    protected static $getCodeActive     = null;
    protected static $checkListCurrency = [];
    protected $guarded                  = [];
    protected $connection = GP247_DB_CONNECTION;

    /**
     * WHY float, not decimal:6: exchange_rate is stored as decimal(16,6) (exact)
     * but Laravel hands a raw decimal column back as a STRING. getValue()
     * (money * rate) and every consumer expect a number, so cast to float —
     * matching how this column already behaved when it was a float column, while
     * the DB keeps full precision (ADR compat-foundation_exchange-rate-precision).
     *
     * @var array<string, string>
     */
    protected $casts = ['exchange_rate' => 'float'];

    public static function getListAll()
    {
        if (!self::$list) {
            self::$list = self::get()
                ->keyBy('code');
        }
        return self::$list;
    }

    public static function getCodeActive()
    {
        if (self::$getCodeActive === null) {
            self::$getCodeActive = self::where('status', 1)
                ->pluck('name', 'code')
                ->all();
        }
        return self::$getCodeActive;
    }


    public static function getCodeAll()
    {
        if (self::$getArray === null) {
            self::$getArray = self::pluck('name', 'code')->all();
        }
        return self::$getArray;
    }

    /**
     * [setCode description]
     * @param [type] $code [description]
     */
    public static function setCode($code)
    {
        self::$code = $code;
        if (empty(self::$checkListCurrency[$code])) {
            self::$checkListCurrency[$code] = self::where('code', $code)->first();
        }
        $checkCurrency = self::$checkListCurrency[$code];
        if ($checkCurrency) {
            self::$name          = $checkCurrency->name;
            self::$symbol        = $checkCurrency->symbol;
            self::$exchange_rate = $checkCurrency->exchange_rate;
            self::$precision     = $checkCurrency->precision;
            self::$symbol_first  = $checkCurrency->symbol_first;
            self::$thousands     = $checkCurrency->thousands;
            self::$decimal       = ($checkCurrency->thousands == '.') ? ',' : '.';
        }
    }

    /**
     * [getCurrency description]
     * @return [type] [description]
     */
    public static function getCurrency()
    {
        return [
            'code'          => self::$code,
            'name'          => self::$name,
            'symbol'        => self::$symbol,
            'exchange_rate' => self::$exchange_rate,
            'precision'     => self::$precision,
            'symbol_first'  => self::$symbol_first,
            'thousands'     => self::$thousands,
            'decimal'       => self::$decimal,
        ];
    }

    /*
     * [getCode description]
     * @return [type] [description]
     */
    public static function getCode()
    {
        return self::$code;
    }

    /**
     * [getRate description]
     * @return [type] [description]
     */
    public static function getRate()
    {
        return self::$exchange_rate;
    }

    /**
     * [getValue description]
     * @param  float  $money [description]
     * @param  [type] $rate  [description]
     * @return [type]        [description]
     */
    public static function getValue(float $money, $rate = null)
    {
        if (!empty($rate)) {
            return $money * $rate;
        } else {
            return $money * self::$exchange_rate;
        }
    }

    /**
     * Format a money value as a display string.
     *
     * Precision follows the CURRENCY being rendered (its `precision`), not the
     * value — so every amount of one currency shows the same number of decimals
     * (the ecommerce minor-unit convention), instead of the legacy value-conditional
     * behaviour where whole numbers dropped the decimals and fractional numbers kept
     * them (inconsistent within a single currency).
     *
     * The optional $precision/$thousands args let render()/onlyRender() format a
     * TARGET currency other than the active one; when omitted they default to the
     * active currency's static props, keeping every existing caller
     * (gp247_currency_*, all Blade) backward-compatible. The decimal separator is
     * always derived from the effective thousands separator, the same rule setCode()
     * uses, so a passed $thousands never mismatches a stale self::$decimal.
     *
     * @param  float    $money     Amount to format (already converted to the target currency).
     * @param  int|null $precision Decimals to show; null → active currency precision.
     * @param  string|null $thousands Thousands separator; null → active currency separator.
     * @return string Formatted money string.
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-storefront-currency-display-precision
     * @aidlc-adr shop_currency-display-precision
     */
    public static function format(float $money, $precision = null, $thousands = null)
    {
        $precision = ($precision === null) ? self::$precision : $precision;
        $thousands = ($thousands === null) ? self::$thousands : $thousands;
        $decimal   = ($thousands == '.') ? ',' : '.';

        return number_format($money, (int) $precision, $decimal, $thousands);
    }

    /**
     * [render description]
     * @param  float   $money                [description]
     * @param  [type]  $currency             [description]
     * @param  [type]  $rate                 [description]
     * @param  boolean $space_between_symbol [description]
     * @param  boolean $includeSymbol       [description]
     * @return [type]                        [description]
     */
    public static function render(float $money, $currency = null, $rate = null, $space_between_symbol = false, $includeSymbol = true)
    {
        $space_symbol = ($space_between_symbol) ? ' ' : '';
        $dataCurrency = self::getCurrency();
        if ($currency) {
            if (empty(self::$checkListCurrency[$currency])) {
                self::$checkListCurrency[$currency] = self::where('code', $currency)->first();
            }
            $checkCurrency = self::$checkListCurrency[$currency];
            if ($checkCurrency) {
                $dataCurrency = $checkCurrency;
            }
        }
        //Get currently value
        $value = self::getValue($money, $rate);

        $symbol = ($includeSymbol) ? $dataCurrency['symbol'] : '';

        // Format with the TARGET currency's precision/thousands (not the active
        // currency's static props), so a non-active $currency renders correctly.
        $p = $dataCurrency['precision'];
        $t = $dataCurrency['thousands'];

        if ($dataCurrency['symbol_first']) {
            if ($money < 0) {
                return '-' . $symbol . $space_symbol . self::format(abs($value), $p, $t);
            } else {
                return $symbol . $space_symbol . self::format($value, $p, $t);
            }
        } else {
            return self::format($value, $p, $t) . $space_symbol . $symbol;
        }
    }

    /**
     * [onlyRender description]
     * @param  float   $money                [description]
     * @param  [type]  $currency             [description]
     * @param  boolean $space_between_symbol [description]
     * @param  boolean $includeSymbol       [description]
     * @return [type]                        [description]
     */
    public static function onlyRender(float $money, $currency, $space_between_symbol = false, $includeSymbol = true)
    {
        if (empty(self::$checkListCurrency[$currency])) {
            self::$checkListCurrency[$currency] = self::where('code', $currency)->first();
        }
        $checkCurrency = self::$checkListCurrency[$currency];

        $space_symbol  = ($space_between_symbol) ? ' ' : '';
        $symbol        = ($includeSymbol) ? ($checkCurrency['symbol'] ?? '') : '';
        // Format with the passed currency's precision/thousands; null falls back
        // to the active currency's static props inside format() when absent.
        $p = $checkCurrency['precision'] ?? null;
        $t = $checkCurrency['thousands'] ?? null;
        if (($checkCurrency['symbol_first'] ?? false)) {
            if ($money < 0) {
                return '-' . $symbol . $space_symbol . self::format(abs($money), $p, $t);
            } else {
                return $symbol . $space_symbol . self::format($money, $p, $t);
            }
        } else {
            return self::format($money, $p, $t) . $space_symbol . $symbol;
        }
    }

    /**
     * Sum value of cart
     *
     * @param   float  $rate     [$rate description]
     *
     * @return  [array]
     */
    public static function sumCartCheckout(float $rate = 0)
    {
        $dataCheckout = session('dataCheckout') ?? [];
        $rate = ($rate) ? $rate : self::$exchange_rate;
        $dataReturn = [];
        $sumSubtotal  = 0;
        $sumSubtotalWithTax  = 0;
        foreach ($dataCheckout as $item) {
            // WHY: session('dataCheckout') is read straight from the session, not
            // via Cart::content(), so when session.serialization = json it comes
            // back as a plain array instead of a CartItem (see CartService::getContent).
            $item = CartItem::hydrate($item);
            $product = (new ShopProduct)->getDetail(key:$item->id, type:'id', storeId: $item->storeId);
            if($product) {
                $priceItem = $product->getFinalPrice();
                $priceItem += gp247_cart_options_price($item->options);
                $priceConverted = self::getValue($priceItem, $rate);
                $lineSubtotal = $priceConverted * $item->qty;
                // WHY: line-level rounding (round the whole line once) so the order-total
                // tax equals the sum of per-line taxes persisted on shop_order_detail
                // (ADR shop-admin_tax-standardization, D2). gp247_line_tax is the single
                // source shared with ShopCartController::addOrder.
                $lineTax = gp247_line_tax($priceConverted, $item->qty, $product->getTaxValue());
                $sumSubtotal += $lineSubtotal;
                $sumSubtotalWithTax += $lineSubtotal + $lineTax;
            }
        }
        $dataReturn['subTotal'] = $sumSubtotal;
        $dataReturn['subTotalWithTax'] = $sumSubtotalWithTax;
        return $dataReturn;
    }


    public static function getListRate()
    {
        return self::pluck('exchange_rate', 'code')->all();
    }

    public static function getListActive()
    {
        return self::where('status', 1)
            ->sort()
            ->get();
    }
    
    public function scopeSort($query, $sortBy = null, $sortOrder = 'asc')
    {
        $sortBy = $sortBy ?? 'sort';
        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Determine whether this currency may be deleted, returning a business
     * reason key when deletion must be blocked, or null when it is safe.
     *
     * The invariant is derived from live data and keyed on the portable
     * business key `code` (never the surrogate id): a currency is protected
     * when it is the store default, is referenced by any order, or is the last
     * active currency.
     *
     * WHY gp247_store_info('currency') rather than self::getCode(): the admin
     * area does not run CurrencyMiddleware, so getCode() would return the class
     * default instead of the configured store currency. gp247_store_info reads
     * the same config source the middleware reads.
     *
     * @return string|null One of 'default'|'in_use'|'last_active', or null when deletable.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-currency-delete-guard
     * @aidlc-adr ADR-007
     */
    public function deleteBlockReason(): ?string
    {
        if ($this->code === gp247_store_info('currency')) {
            return 'default';
        }
        if (ShopOrder::where('currency', $this->code)->exists()) {
            return 'in_use';
        }
        // Direct count avoids getCodeActive()'s static cache going stale within
        // a request after a status toggle.
        if ((int) $this->status === 1 && self::where('status', 1)->count() <= 1) {
            return 'last_active';
        }
        return null;
    }

    protected static function boot()
    {
        parent::boot();
        // Defense-in-depth: block deletion of a protected currency on ANY path
        // (Livewire, seeder, tinker, future callers), not only the admin screen.
        static::deleting(function ($model) {
            if ($model->deleteBlockReason() !== null) {
                return false;
            }
        });
    }
}
