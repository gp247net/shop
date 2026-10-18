<?php

namespace GP247\Shop\Shipping\Data;

/**
 * Outcome of a carrier callback, as decided by the provider.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-tracking-webhook
 * @aidlc-adr shop-shipping_webhook-routing
 */
final readonly class WebhookResult
{
    /**
     * @param bool                      $verified   False ⇒ the core writes nothing.
     * @param array<int, TrackingEvent> $events     Normalised events.
     * @param int                       $httpStatus Status code to answer the carrier with.
     */
    public function __construct(
        public bool $verified,
        public array $events = [],
        public int $httpStatus = 200,
    ) {
    }

    /**
     * @param int $httpStatus 4xx answered to the caller.
     * @return self
     */
    public static function rejected(int $httpStatus = 403): self
    {
        return new self(false, [], $httpStatus);
    }
}
