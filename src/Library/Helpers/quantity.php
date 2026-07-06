<?php
/**
 * File function process product quantity format (integer vs decimal)
 * @see aidlc-docs/design-artifacts/adrs/shop-admin_quantity-format-config.md (ADR-016)
 */

if (!function_exists('gp247_qty_decimal_enabled') && !in_array('gp247_qty_decimal_enabled', config('gp247_functions_except', []))) {
    /**
     * Whether decimal product quantities are allowed across the shop package
     * (Shop Config key `product_qty_decimal`, global scope). Defaults to false
     * (integer-only) when the key has not been seeded yet, matching the
     * seeder default value '0'.
     *
     * @return bool
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-005
     * @aidlc-adr ADR-016
     */
    function gp247_qty_decimal_enabled(): bool
    {
        if (!function_exists('gp247_config')) {
            return false;
        }

        $value = gp247_config('product_qty_decimal');

        return $value === '1' || $value === 1 || $value === true;
    }
}

if (!function_exists('gp247_qty_rule') && !in_array('gp247_qty_rule', config('gp247_functions_except', []))) {
    /**
     * Laravel validation rule fragment for a product-quantity field, switching
     * between `integer` and `numeric` based on `product_qty_decimal`. Two minimum
     * bounds are accepted because a sensible minimum differs between modes (e.g.
     * min:1 for whole units vs min:0.01 for decimal units).
     *
     * @param string $minWhenInteger Minimum bound applied when decimal mode is disabled.
     * @param string $minWhenDecimal Minimum bound applied when decimal mode is enabled.
     * @return string
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-001, US-SADM-003, US-LW-004
     * @aidlc-adr ADR-016
     */
    function gp247_qty_rule(string $minWhenInteger = '1', string $minWhenDecimal = '0.01'): string
    {
        return gp247_qty_decimal_enabled()
            ? 'numeric|min:' . $minWhenDecimal
            : 'integer|min:' . $minWhenInteger;
    }
}

if (!function_exists('gp247_qty_format') && !in_array('gp247_qty_format', config('gp247_functions_except', []))) {
    /**
     * Format a product quantity for display, honouring `product_qty_decimal`.
     * Display-only — never mutates the stored decimal(15,2) value (NFR-MAINT-008).
     *
     * @param mixed $value
     * @return string
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-001
     * @aidlc-adr ADR-016
     */
    function gp247_qty_format($value): string
    {
        $number = (float) $value;

        return gp247_qty_decimal_enabled()
            ? number_format($number, 2, '.', '')
            : (string) round($number);
    }
}
