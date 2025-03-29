<?php

//Function process view
if (!function_exists('gp247_shop_process_view') && !in_array('gp247_shop_process_view', config('gp247_functions_except', []))) {
    function gp247_shop_process_view(string $prefix, string $subPath)
    {
        $view = $prefix . $subPath;
        if (!view()->exists($view)) {
            $view = 'gp247-shop-front::'.$subPath;
            if (!view()->exists('gp247-shop-front::'.$subPath)) {
                $view = $prefix . $subPath;
            }   
        }
        return $view;
    }
}