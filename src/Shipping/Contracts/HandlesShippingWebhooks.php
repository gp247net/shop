<?php

namespace GP247\Shop\Shipping\Contracts;

use Closure;
use GP247\Shop\Shipping\Data\WebhookResult;
use Illuminate\Http\Request;

/**
 * Capability `webhook`: turn a carrier callback into verified, normalised tracking
 * events. The core owns the route ({prefix}/shipping/webhook/{pluginKey}), throttling
 * and de-duplication; the provider owns authentication — it must reject what it cannot
 * verify, and confirm money-bearing states from a trusted source (signature or a
 * call back to the carrier API) (NFR-SEC-shipping-webhook-verify).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-tracking-webhook
 * @aidlc-adr shop-shipping_webhook-routing
 */
interface HandlesShippingWebhooks
{
    /**
     * @param Request $request Raw carrier request.
     * @param Closure $storeOf fn(string $trackingNumber): ?string — store id owning a shipment
     *                         of this provider, or null when the shipment is unknown.
     * @return WebhookResult verified=false ⇒ the core writes nothing.
     */
    public function handleWebhook(Request $request, Closure $storeOf): WebhookResult;
}
