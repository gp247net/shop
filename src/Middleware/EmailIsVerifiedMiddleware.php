<?php

namespace GP247\Shop\Middleware;

use Closure;

class EmailIsVerifiedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $redirectToRoute
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|null
     */
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (gp247_config('customer_verify')) {
            $arrExclude = [
                'customer.verify',
                'customer.verify_resend',
                'customer.verify_process',
            ];
            if (customer()->user() && customer()->user()->hasVerifiedEmail()) {
                if (!in_array($request->route()->getName(), $arrExclude)) {
                    return redirect()->guest(gp247_route_front('customer.verify'));
                }
            } else {
                if (in_array($request->route()->getName(), $arrExclude)) {
                    return redirect(gp247_route_front('customer.index'));
                }
            }
        }

        return $next($request);
    }
}
