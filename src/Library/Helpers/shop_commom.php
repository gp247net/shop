<?php

//Function process view
// Prioritize checking the view exists in the current template
// If it does not exist, check in the shop view
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

/**
 * Resolve a shop email view for the active store template.
 *
 * Thin wrapper over gp247_shop_process_view() for the repeated
 * 'GP247TemplatePath::<active-template>' + email sub-path pattern used by every
 * transactional mail (order/customer). Falls back to the package view when the
 * active template does not override it.
 *
 * @param string $subPath Email view sub-path (e.g. 'email.shop_welcome_customer').
 * @return string Resolvable view name for gp247_mail_send().
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-mail-delivery-hardening
 */
if (!function_exists('gp247_shop_mail_view') && !in_array('gp247_shop_mail_view', config('gp247_functions_except', []))) {
    function gp247_shop_mail_view(string $subPath): string
    {
        return gp247_shop_process_view('GP247TemplatePath::' . gp247_store_info('template'), $subPath);
    }
}