<?php

namespace GP247\Shop\Shipping\Data;

/**
 * A printable document (label) returned by a carrier.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 */
final readonly class ShippingDocument
{
    /**
     * @param string $content  Raw bytes.
     * @param string $mime     MIME type (e.g. application/pdf).
     * @param string $filename Download file name.
     */
    public function __construct(
        public string $content,
        public string $mime = 'application/pdf',
        public string $filename = 'label.pdf',
    ) {
    }
}
