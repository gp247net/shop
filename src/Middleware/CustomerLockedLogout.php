<?php

namespace GP247\Shop\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Sign out, on every storefront request, a customer locked (status != 1) after
 * signing in — cart, checkout and account alike, not only the account pages that
 * use customer.auth. Appended to the front middleware group (alias
 * `customer.locked_logout`).
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-customer-locked-login-block
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
class CustomerLockedLogout
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure                  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $guard = Auth::guard('customer');
        $customer = $guard->user();
        if ($customer && (int) $customer->status !== 1) {
            $guard->logout();
        }

        return $next($request);
    }
}
