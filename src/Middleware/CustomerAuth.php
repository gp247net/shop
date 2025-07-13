<?php

namespace GP247\Shop\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CustomerAuth
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $redirectTo = gp247_route_front('customer.login');
        if (Auth::guard('customer')->guest() && !$this->shouldPassThrough($request)) {
            return redirect()->guest($redirectTo);
        }

        return $next($request);
    }

    /**
     * Determine if the request has a URI that should pass through verification.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    protected function shouldPassThrough($request)
    {
        $routeName = $request->route()->getName();
        $excepts = [
            'customer.login',
            'customer.postLogin',
            'customer.logout',
            'customer.forgot',
            'customer.register',
            'customer.postRegister',
            'customer.password_reset',
            'customer.password_request',
        ];
        return in_array($routeName, $excepts);
    }
}
