<?php

//Function process view
if (!function_exists('gp247_shop_process_view') && !in_array('gp247_shop_process_view', config('gp247_functions_except', []))) {
    function gp247_shop_process_view(string $prefix, string $subPath)
    {
        if (strpos($prefix, '.') === false) {
            $prefix = $prefix . '.';
        }
        $view = $prefix . $subPath;
        if (!view()->exists($view)) {
            $viewShop = 'gp247-shop-front::'.$subPath;
            if (view()->exists($viewShop)) {
                $view = $viewShop;
            }   
        }
        return $view;
    }
}