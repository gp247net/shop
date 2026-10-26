<?php

namespace GP247\Shop\Api\Middleware;

use Closure;
use GP247\Core\Api\Support\ApiErrorResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Refuse a customer API token whose owner has been locked (status != 1) since the
 * token was issued (alias `api.customer.active`, after auth:customer-api).
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-customer-locked-login-block
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
class CustomerActive
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $customer = Auth::guard('customer-api')->user();
        if (!$customer || (int) $customer->status !== 1) {
            return ApiErrorResponder::unauthenticated();
        }

        return $next($request);
    }
}
