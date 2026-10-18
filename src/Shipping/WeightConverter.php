<?php

namespace GP247\Shop\Shipping;

/**
 * Converts a product weight stored with its `weight_class` to whole grams — the unit
 * every carrier quote in the shipping contract uses.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-origin-config
 */
final class WeightConverter
{
    /** @var array<string, float> Grams per unit, keyed by lower-cased weight class. */
    private const GRAMS_PER_UNIT = [
        'g' => 1.0,
        'gr' => 1.0,
        'gram' => 1.0,
        'grams' => 1.0,
        'kg' => 1000.0,
        'kilogram' => 1000.0,
        'kilograms' => 1000.0,
        'lb' => 453.59237,
        'lbs' => 453.59237,
        'pound' => 453.59237,
        'oz' => 28.349523125,
        'ounce' => 28.349523125,
    ];

    /**
     * @param float|int|string|null $value Weight value.
     * @param string|null           $class Weight class (g, kg, lb, oz…); unknown/empty = grams.
     * @return int Whole grams, never negative.
     */
    public static function toGrams(float|int|string|null $value, ?string $class): int
    {
        $number = is_numeric($value) ? (float) $value : 0.0;
        if ($number <= 0) {
            return 0;
        }
        $factor = self::GRAMS_PER_UNIT[strtolower(trim((string) $class))] ?? 1.0;

        return (int) round($number * $factor);
    }
}
