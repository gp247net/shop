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