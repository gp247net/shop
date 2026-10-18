<?php

namespace GP247\Shop\Shipping\Data;

use GP247\Shop\Shipping\ShipmentStatus;

/**
 * One carrier event, normalised. `status = null` marks an informational event (kept
 * in the timeline, never moves the shipment state). `codSettled` means the carrier
 * has reconciled the cash it collected (US-shop-shipping-cod).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-tracking-webhook
 */
final readonly class TrackingEvent
{
    /**
     * @param string              $trackingNumber Carrier tracking number.
     * @param ShipmentStatus|null $status         Normalised status (null = informational).
     * @param string              $providerStatus Carrier's raw status code.
     * @param int                 $occurredAt     Unix time of the event at the carrier.
     * @param string              $message        Carrier reason / description.
     * @param bool                $codSettled     Carrier reconciled the collected cash.
     * @param Money|null          $codAmount      Cash collected, if reported.
     * @param Money|null          $fee            Carrier fee, if reported.
     */
    public function __construct(
        public string $trackingNumber,
        public ?ShipmentStatus $status,
        public string $providerStatus,
        public int $occurredAt,
        public string $message = '',
        public bool $codSettled = false,
        public ?Money $codAmount = null,
        public ?Money $fee = null,
    ) {
    }

    /**
     * @param string $pluginKey Provider plugin key.
     * @return string Key that makes a replayed event a no-op.
     */
    public function dedupeKey(string $pluginKey): string
    {
        return substr($pluginKey . ':' . $this->trackingNumber . ':' . $this->providerStatus . ':' . $this->occurredAt, 0, 190);
    }
}
