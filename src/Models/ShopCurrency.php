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
    protected $casts = ['exchange_rate' => 'float', 'is_base' => 'boolean'];

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
     * Resolve the base (functional) currency code — the unit every product price,
     * cost and promotion value is stored in ("bit" in the store owner's terms).
     *
     * Single, explicit source of truth: the one row flagged is_base=1. This
     * replaces the former implicit "active currency with exchange_rate=1" scan and
     * its store-default fallback, which mislabelled the base whenever a site had
     * zero or several rate=1 currencies, or when the display-default currency
     * differed from the price-storage unit (RISK-BIZ-implicit-base-currency,
     * NFR-MAINT-base-currency-single-source).
     *
     * @return string|null Base currency code, or null when no base is designated
     *                     (e.g. an upgraded site the admin has not picked one for yet).
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-base-currency-explicit
     * @aidlc-adr currency-base-system-scope
     */
    public static function getBaseCode(): ?string
    {
        return self::where('is_base', 1)->value('code') ?: null;
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
     * when it is the base currency, is the store default, is referenced by any
     * order, or is the last active currency.
     *
     * WHY gp247_store_info('currency') rather than self::getCode(): the admin
     * area does not run CurrencyMiddleware, so getCode() would return the class
     * default instead of the configured store currency. gp247_store_info reads
     * the same config source the middleware reads.
     *
     * @return string|null One of 'base'|'default'|'in_use'|'last_active', or null when deletable.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-currency-delete-guard
     * @aidlc-adr ADR-007
     * @aidlc-adr currency-base-system-scope
     */
    public function deleteBlockReason(): ?string
    {
        // WHY first: the base is the unit all catalog prices are stored in;
        // deleting it would orphan every price. Base is changed only via rebase().
        if ((bool) $this->is_base === true) {
            return 'base';
        }
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
        // Defense-in-depth: keep the base invariant on ANY Eloquent save — the
        // base currency must always have exchange_rate=1 and status=1 (B2/B3).
        // rebase() and seeders use the query builder (which bypasses model events)
        // by design, so this guard never fights the legitimate base-swap flow; it
        // only stops an admin edit / API call from breaking a live base.
        static::saving(function ($model) {
            if ((bool) $model->is_base === true
                && ((float) $model->exchange_rate !== 1.0 || (int) $model->status !== 1)) {
                return false;
            }
        });
    }

    /**
     * Change the base (functional) currency while preserving every product's
     * economic value, atomically.
     *
     * The base is the unit all catalog prices/costs/promotions are stored in.
     * Moving the base to another currency without touching the numbers would
     * silently re-price the whole catalog (100 stored as "100 USD" would be read
     * as "100 VND"). Instead this rescales the stored figures so the displayed
     * value in every currency is unchanged (ADR currency-rebase-value-preserving,
     * proof: (price*r)*(oldRate(C)/r) = price*oldRate(C) for every currency C).
     *
     * Let r = the NEW base's current exchange_rate (units of NEW per 1 OLD base
     * unit). In one transaction (NFR-AVAIL-rebase-atomicity):
     *   1. Multiply the 4 base-denominated price columns by r (bulk UPDATE at the
     *      DB layer — never loads the catalog into PHP; RISK-TECH-rebase-bulk-price-update).
     *   2. Rescale every currency's exchange_rate by dividing by r.
     *   3. Pin NEW.exchange_rate = 1 exactly (avoids float drift from step 2) and
     *      OLD.exchange_rate = $newRateForOldBase (defaults, via the UI suggestion,
     *      to 1/r → perfect value preservation; a different value is a deliberate
     *      admin choice).
     *   4. Swap the is_base flag: NEW = 1, OLD = 0.
     * Order snapshots (shop_order*) are immutable and intentionally untouched.
     *
     * @param string $newBaseCode        Code of the currency to become the base; must be active and not already the base.
     * @param float  $newRateForOldBase  New exchange_rate for the outgoing base; must be finite, > 0 and != 1.
     * @return void
     * @throws \InvalidArgumentException When no base is set, the target is missing/inactive/already base, or the rate is invalid.
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-base-currency-explicit
     * @aidlc-story US-SADM-currency-rebase-ui
     * @aidlc-adr currency-rebase-value-preserving
     */
    public static function rebase(string $newBaseCode, float $newRateForOldBase): void
    {
        $oldBase = self::where('is_base', 1)->first();
        if ($oldBase === null) {
            throw new \InvalidArgumentException('No base currency is set; cannot rebase.');
        }

        $newBase = self::where('code', $newBaseCode)->first();
        if ($newBase === null) {
            throw new \InvalidArgumentException('Target currency does not exist: ' . $newBaseCode);
        }
        if ($newBase->code === $oldBase->code) {
            throw new \InvalidArgumentException('Target currency is already the base.');
        }
        if ((int) $newBase->status !== 1) {
            throw new \InvalidArgumentException('Target currency must be active to become the base.');
        }

        if (!is_finite($newRateForOldBase) || $newRateForOldBase <= 0 || $newRateForOldBase == 1.0) {
            throw new \InvalidArgumentException('The outgoing base must be given a real exchange rate (> 0 and != 1).');
        }

        // r = current rate of the target currency, expressed in the OLD base unit.
        $r = (float) $newBase->exchange_rate;
        if (!is_finite($r) || $r <= 0) {
            throw new \InvalidArgumentException('Target currency has an invalid exchange rate.');
        }

        // WHY sprintf('%.6F', ...) then interpolate into DB::raw: $r is a
        // fully-controlled float (never user text) and this yields a plain decimal
        // literal (no exponent, no locale comma) safe for bulk `col = col * r`.
        // The scale matches the exchange_rate column's decimal(16,6).
        $factor = self::sqlNumericLiteral($r);

        $connection = self::query()->getConnection();
        $prefix = GP247_DB_PREFIX;

        $connection->transaction(function () use ($connection, $prefix, $factor, $r, $oldBase, $newBase, $newRateForOldBase) {
            // 1. Recompute the 4 base-denominated price columns × r at the DB layer.
            $connection->statement("UPDATE {$prefix}shop_product SET price = price * {$factor}, cost = cost * {$factor}");
            $connection->statement("UPDATE {$prefix}shop_product_attribute SET add_price = add_price * {$factor}");
            $connection->statement("UPDATE {$prefix}shop_product_promotion SET price_promotion = price_promotion * {$factor}");

            // 2. Rescale every currency's rate ÷ r (keeps cross-rates consistent).
            $connection->table($prefix . 'shop_currency')
                ->update(['exchange_rate' => $connection->raw("exchange_rate / {$factor}")]);

            // 3. Pin the new base to exactly 1 (avoid ÷ drift) and set the old base's
            //    real rate. 4. Swap the is_base flag. Query builder is used on
            //    purpose so the saving() guard does not reject the transient state.
            $connection->table($prefix . 'shop_currency')
                ->where('code', $newBase->code)
                ->update(['exchange_rate' => 1, 'is_base' => 1]);
            $connection->table($prefix . 'shop_currency')
                ->where('code', $oldBase->code)
                ->update(['exchange_rate' => $newRateForOldBase, 'is_base' => 0]);
        });

        // Live-request caches now hold pre-rebase base/rates — clear them so the
        // rest of the request (and the redirect that follows) reads fresh data.
        self::resetStaticCache();
    }

    /**
     * Format a controlled float as a plain SQL decimal literal safe to interpolate
     * into a `col = col * n` expression: fixed-point (no scientific notation), a
     * dot decimal separator regardless of locale, capped at the exchange_rate
     * column scale (6). Not for user input — callers pass values they own.
     *
     * @param float $value A finite, positive number controlled by the caller.
     * @return string A bare decimal literal, e.g. "20000.000000".
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-base-currency-explicit
     * @aidlc-adr currency-rebase-value-preserving
     */
    private static function sqlNumericLiteral(float $value): string
    {
        return sprintf('%.6F', $value);
    }

    /**
     * Clear the per-request static caches so subsequent reads reflect the latest
     * currency rows (used after a rebase mutates rates and the base flag).
     *
     * @return void
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-base-currency-explicit
     * @aidlc-adr currency-rebase-value-preserving
     */
    public static function resetStaticCache(): void
    {
        self::$list              = null;
        self::$getArray          = null;
        self::$getCodeActive     = null;
        self::$checkListCurrency = [];
    }
}
