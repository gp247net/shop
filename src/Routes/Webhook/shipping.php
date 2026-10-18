<?php

use GP247\Shop\Shipping\Http\ShippingWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Carrier webhooks of the standard shipping layer (mod 20261017T185906).
 *
 * WHY its own file, loaded by ShopServiceProvider and not under Routes/Front: every
 * Routes/Front file is wrapped in the storefront middleware group (session, CSRF,
 * front.redirect, check.active) — a carrier's server-to-server POST would be rejected
 * or redirected there. The store is already resolved from the domain at boot.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-tracking-webhook
 * @aidlc-adr shop-shipping_webhook-routing
 */
Route::post(trim((string) config('gp247-config.shop.shipping.webhook_prefix', 'shipping/webhook'), '/') . '/{pluginKey}', [ShippingWebhookController::class, 'handle'])
    ->where('pluginKey', '[A-Za-z0-9_]{1,100}')
    ->middleware('throttle:' . config('gp247-config.shop.shipping.webhook_throttle', '120,1'))
    ->name('shop.shipping.webhook');
