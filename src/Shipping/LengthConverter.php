<?php

namespace GP247\Shop\Shipping;

/**
 * Converts a product dimension stored with its `length_class` to whole centimetres —
 * the unit every carrier receives through the shipping contract.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-product-profile
 * @aidlc-adr shop-shipping_product-shipping-profile
 */
final class LengthConverter
{
    /** @var array<string, float> Centimetres per unit, keyed by lower-cased length class. */
    private const CM_PER_UNIT = [
        'mm' => 0.1,
        'millimeter' => 0.1,
        'cm' => 1.0,
        'centimeter' => 1.0,
        'm' => 100.0,
        'meter' => 100.0,
        'in' => 2.54,
        'inch' => 2.54,
        'ft' => 30.48,
        'foot' => 30.48,
    ];

    /**
     * @param float|int|string|null $value Dimension value.
     * @param string|null           $class Length class; unknown/empty = centimetres.
     * @return int Whole centimetres (a real dimension never rounds to 0), never negative.
     */
    public static function toCm(float|int|string|null $value, ?string $class): int
    {
        $number = is_numeric($value) ? (float) $value : 0.0;
        if ($number <= 0) {
            return 0;
        }
        $factor = self::CM_PER_UNIT[strtolower(trim((string) $class))] ?? 1.0;

        return max(1, (int) round($number * $factor));
    }
}
