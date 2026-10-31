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
        self::apply();
        return $next($request);
    }

    /**
     * Set the currency of this request: the one chosen in the session, else the store's,
     * else the first active one. Also used by the cart / checkout API, whose requests do
     * not run the web middleware group.
     *
     * @return void
     */
    public static function apply(): void
    {
        $currency = session('currency') ?? gp247_store_info('currency');
        if (!array_key_exists($currency, gp247_currency_all_active())) {
            $currency = array_key_first(gp247_currency_all_active());
        }
        ShopCurrency::setCode($currency);
    }
}
