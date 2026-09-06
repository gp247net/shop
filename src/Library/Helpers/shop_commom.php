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
/**
 * Whether the shop module is actually installed at the DB level (its create-tables
 * migration ran), not merely present in vendor/.
 *
 * WHY the migrations-table check (not "composer package present"): a shop package
 * can sit in vendor/ before `gp247:shop-install` has created its tables. Guarding
 * shop-only wiring (e.g. contributing a config tab to the core hub) on the package
 * being present would then blow up on a half-installed site — the operational truth
 * is "the shop tables exist" (gp247.md §0). Fails safe to false on any DB error so
 * boot is never blocked.
 *
 * @return bool True when the shop create-tables migration is recorded.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-shop-config-into-hub
 */
if (!function_exists('gp247_shop_installed') && !in_array('gp247_shop_installed', config('gp247_functions_except', []))) {
    function gp247_shop_installed(): bool
    {
        try {
            return \Illuminate\Support\Facades\DB::connection(GP247_DB_CONNECTION)
                ->table('migrations')
                ->where('migration', '00_00_00_create_tables_shop')
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
