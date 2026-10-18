<?php

namespace GP247\Shop\Shipping\Data;

/**
 * The packed parcel as the admin measured it when booking (box weight and size), which
 * usually differs from the sum of the products inside.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-product-profile
 * @aidlc-adr shop-shipping_product-shipping-profile
 */
final readonly class Parcel
{
    /**
     * @param int $weightGrams Packed weight in grams.
     * @param int $lengthCm    Box length (0 = not measured).
     * @param int $widthCm     Box width (0 = not measured).
     * @param int $heightCm    Box height (0 = not measured).
     */
    public function __construct(
        public int $weightGrams,
        public int $lengthCm = 0,
        public int $widthCm = 0,
        public int $heightCm = 0,
    ) {
    }

    /**
     * @return bool All three box dimensions are known.
     */
    public function hasDimensions(): bool
    {
        return $this->lengthCm > 0 && $this->widthCm > 0 && $this->heightCm > 0;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return ['weight_grams' => $this->weightGrams, 'length_cm' => $this->lengthCm, 'width_cm' => $this->widthCm, 'height_cm' => $this->heightCm];
    }
}
