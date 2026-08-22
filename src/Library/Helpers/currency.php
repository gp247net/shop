<?php
/**
 * File function process currency
 * @author Lanh Le <lanhktc@gmail.com>
 */
use GP247\Shop\Models\ShopCurrency;

if (!function_exists('gp247_currency_render') && !in_array('gp247_currency_render', config('gp247_functions_except', []))) {
    /**
     * Render currency: format number, change amount, add symbol
     *
     * @param   float  $money                 [$money description]
     * @param   [type] $currency              [$currency description]
     * @param   null   $rate                  [$rate description]
     * @param   null   $space_between_symbol  [$space_between_symbol description]
     * @param   false  $useSymbol             [$useSymbol description]
     * @param   true                          [ description]
     *
     * @return  [type]                        [return description]
     */
    function gp247_currency_render(float $money, $currency = null, $rate = null, $space_between_symbol = false, $useSymbol = true)
    {
        return ShopCurrency::render($money, $currency, $rate, $space_between_symbol, $useSymbol);
    }
}

if (!function_exists('gp247_currency_render_symbol') && !in_array('gp247_currency_render_symbol', config('gp247_functions_except', []))) {
    /**
     * Only render symbol, dont change amount
     *
     * @param   float  $money                 [$money description]
     * @param   [type] $currency              [$currency description]
     * @param   null   $space_between_symbol  [$space_between_symbol description]
     * @param   false  $includeSymbol        [$includeSymbol description]
     * @param   true                          [ description]
     *
     * @return  [type]                        [return description]
     */
    function gp247_currency_render_symbol(float $money, $currency = null, $space_between_symbol = false, $includeSymbol = true)
    {
        $currency = $currency ? $currency : gp247_currency_code();
        return ShopCurrency::onlyRender($money, $currency, $space_between_symbol, $includeSymbol);
    }
}


if (!function_exists('gp247_currency_value') && !in_array('gp247_currency_value', config('gp247_functions_except', []))) {
    /**
     * Get value of amount with specify exchange rate
     * if dont specify rate, will use exchange rate default
     *
     * @param   float  $money  [$money description]
     * @param   float  $rate   [$rate description]
     * @param   null           [ description]
     *
     * @return  [type]         [return description]
     */
    function gp247_currency_value(float $money, float $rate = null)
    {
        return ShopCurrency::getValue($money, $rate);
    }
}

//Get code currency
if (!function_exists('gp247_currency_code') && !in_array('gp247_currency_code', config('gp247_functions_except', []))) {
    function gp247_currency_code()
    {
        return ShopCurrency::getCode();
    }
}

//Get rate currency
if (!function_exists('gp247_currency_rate') && !in_array('gp247_currency_rate', config('gp247_functions_except', []))) {
    function gp247_currency_rate()
    {
        return ShopCurrency::getRate();
    }
}

//Format value without symbol
if (!function_exists('gp247_currency_format') && !in_array('gp247_currency_format', config('gp247_functions_except', []))) {
    function gp247_currency_format(float $money)
    {
        return ShopCurrency::format($money);
    }
}

//Get currency info
if (!function_exists('gp247_currency_info') && !in_array('gp247_currency_info', config('gp247_functions_except', []))) {
    function gp247_currency_info()
    {
        return ShopCurrency::getCurrency();
    }
}

//Get all currencies
if (!function_exists('gp247_currency_all') && !in_array('gp247_currency_all', config('gp247_functions_except', []))) {
    function gp247_currency_all()
    {
        return ShopCurrency::getListActive();
    }
}

//Get array code, name of currencies active
if (!function_exists('gp247_currency_all_active') && !in_array('gp247_currency_all_active', config('gp247_functions_except', []))) {
    function gp247_currency_all_active()
    {
        return ShopCurrency::getCodeActive();
    }
}

if (!function_exists('gp247_base_currency_code') && !in_array('gp247_base_currency_code', config('gp247_functions_except', []))) {
    /**
     * Get the base (functional) currency code — the unit product prices/cost/promotion
     * are stored in. Single source for the admin money-input hint.
     *
     * @return string|null Base currency code, or null when it cannot be resolved.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-money-input-currency-hint
     * @aidlc-adr shop-admin_money-input-currency-hint
     */
    function gp247_base_currency_code(): ?string
    {
        return ShopCurrency::getBaseCode();
    }
}

if (!function_exists('gp247_money_hint') && !in_array('gp247_money_hint', config('gp247_functions_except', []))) {
    /**
     * Build the context-aware currency-code hint shown beside a money input.
     *
     * Products pass no code (base currency is resolved); order screens pass the
     * order's own currency (edit) or the selected currency (create). When no code
     * can be resolved, falls back to the configurable i18n label product.base_unit_hint.
     *
     * @param string|null $code Explicit currency code for the context; null → base currency.
     * @return string Display hint, e.g. "(VND)" or the i18n fallback label.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-money-input-currency-hint
     * @aidlc-adr shop-admin_money-input-currency-hint
     */
    function gp247_money_hint($code = null): string
    {
        if (empty($code)) {
            $code = gp247_base_currency_code();
        }
        if (!empty($code)) {
            return '(' . $code . ')';
        }
        return gp247_language_render('product.base_unit_hint');
    }
}