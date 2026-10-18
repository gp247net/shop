<?php

namespace GP247\Shop\Shipping\Http;

use GP247\Shop\Shipping\Contracts\HandlesShippingWebhooks;
use GP247\Shop\Shipping\Contracts\ShippingProvider;
use GP247\Shop\Shipping\Models\ShopShipment;
use GP247\Shop\Shipping\ShipmentService;
use GP247\Shop\Shipping\ShippingProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Single carrier callback endpoint: POST {prefix}/{pluginKey}. The provider verifies
 * and normalises; the core de-duplicates and applies (ADR shop-shipping_webhook-routing,
 * NFR-SEC-shipping-webhook-verify). Registered outside the storefront middleware group:
 * a server-to-server call has no session/CSRF and must never be redirected.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-tracking-webhook
 * @aidlc-adr shop-shipping_webhook-routing
 */
class ShippingWebhookController
{
    /**
     * @param Request $request   Carrier request.
     * @param string  $pluginKey Provider plugin key (route segment).
     * @return Response
     */
    public function handle(Request $request, string $pluginKey): Response
    {
        $registry = ShippingProviderRegistry::instance();
        $provider = $registry->provider($pluginKey);
        if (!$provider instanceof HandlesShippingWebhooks || !$registry->supports($pluginKey, ShippingProvider::CAP_WEBHOOK)) {
            return response('', 404);
        }

        $storeOf = static fn (string $tracking): ?string => ShopShipment::where('plugin_key', $pluginKey)
            ->where('tracking_number', $tracking)
            ->value('store_id');

        try {
            $result = $provider->handleWebhook($request, $storeOf);
        } catch (\Throwable $e) {
            gp247_report('[GP247 shipping] webhook of "' . $pluginKey . '" failed: ' . $e->getMessage());

            return response('', 500);
        }
        if (!$result->verified) {
            return response('', $result->httpStatus >= 400 ? $result->httpStatus : 403);
        }

        try {
            ShipmentService::instance()->applyEvents($pluginKey, $result->events, 'webhook');
        } catch (\Throwable $e) {
            // WHY 500: let the carrier retry — nothing was half-applied (each event is its own transaction).
            gp247_report('[GP247 shipping] applying webhook events of "' . $pluginKey . '" failed: ' . $e->getMessage());

            return response('', 500);
        }

        return response('OK', $result->httpStatus);
    }
}
