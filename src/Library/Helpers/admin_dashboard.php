<?php

if (!function_exists('gp247_shop_admin_model') && !in_array('gp247_shop_admin_model', config('gp247_functions_except', []))) {
    /**
     * Resolve a shop admin model class name when the shop package is
     * installed, so each dashboard block can guard its own optional-shop
     * queries without repeating the class_exists lookup inline.
     *
     * @param string $class Bare class name under GP247\Shop\Admin\Models.
     * @return class-string|null Fully-qualified class name, or null when absent.
     */
    function gp247_shop_admin_model(string $class): ?string
    {
        $fqcn = 'GP247\\Shop\\Admin\\Models\\' . $class;

        return class_exists($fqcn) ? $fqcn : null;
    }
}
