<?php

namespace GP247\Shop\Middleware;

use GP247\Shop\Models\ShopCurrency;
use Closure;
use Session;

class CurrencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $currency = session('currency') ?? gp247_store_info('currency');
        if (!array_key_exists($currency, gp247_currency_all_active())) {
            $currency = array_key_first(gp247_currency_all_active());
        }
        ShopCurrency::setCode($currency);
        return $next($request);
    }
}
