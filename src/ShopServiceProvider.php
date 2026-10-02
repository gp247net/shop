<?php

namespace GP247\Shop;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use GP247\Shop\Commands\ShopInstall;
use GP247\Shop\Commands\ShopUninstall;
use GP247\Shop\Commands\ShopUpdate;
use GP247\Shop\Commands\ShopSample;
use GP247\Shop\Commands\ShopClearCart;
use GP247\Shop\Middleware\CurrencyMiddleware;
use GP247\Shop\Middleware\EmailIsVerifiedMiddleware;
use GP247\Shop\Middleware\CustomerAuth;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Validator;
use GP247\Shop\Admin\Models\AdminProduct;
class ShopServiceProvider extends ServiceProvider
{

    protected function initial()
    {
        //Create directory
        try {
            if (!is_dir($directory = app_path('GP247/Shop/Api'))) {
                mkdir($directory, 0777, true);
            }
            if (!is_dir($directory = app_path('GP247/Shop/Controllers'))) {
                mkdir($directory, 0777, true);
            }
            if (!is_dir($directory = app_path('GP247/Shop/Admin/Controllers'))) {
                mkdir($directory, 0777, true);
            }
        } catch (\Throwable $e) {
            $msg = '#GP247-SHOP:: '.$e->getMessage().' - Line: '.$e->getLine().' - File: '.$e->getFile();
            echo $msg;
            exit;
        }

                
        //Load publish
        try {
            $this->registerPublishing();
        } catch (\Throwable $e) {
            $msg = '#GP247-SHOP:: '.$e->getMessage().' - Line: '.$e->getLine().' - File: '.$e->getFile();
            echo $msg;
            exit;
        }

        try {
            $this->commands([
                ShopInstall::class,
                ShopUninstall::class,
                ShopUpdate::class,
                ShopSample::class,
                ShopClearCart::class,
            ]);
        } catch (\Throwable $e) {
            $msg = '#GP247-SHOP:: '.$e->getMessage().' - Line: '.$e->getLine().' - File: '.$e->getFile();
            gp247_report($msg);
            echo $msg;
            exit;
        }
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

        $this->initial();

        if (function_exists('gp247_check_core_actived') && gp247_check_core_actived()) {

            //Load helper
            try {
                foreach (glob(__DIR__.'/Library/Helpers/*.php') as $filename) {
                    require_once $filename;
                }
            } catch (\Throwable $e) {
                $msg = '#GP247-SHOP:: '.$e->getMessage().' - Line: '.$e->getLine().' - File: '.$e->getFile();
                gp247_report($msg);
                echo $msg;
                exit;
            }

            //Boot process GP247
            try {
                $this->bootDefault();
            } catch (\Throwable $e) {
                $msg = '#GP247-SHOP:: '.$e->getMessage().' - Line: '.$e->getLine().' - File: '.$e->getFile();
                gp247_report($msg);
                echo $msg;
                exit;
            }


            try {
                $this->registerRouteMiddleware();
            } catch (\Throwable $e) {
                $msg = '#GP247-SHOP:: '.$e->getMessage().' - Line: '.$e->getLine().' - File: '.$e->getFile();
                gp247_report($msg);
                echo $msg;
                exit;
            }

            $this->loadViewsFrom(__DIR__.'/Views/admin', 'gp247-shop-admin');
            $this->loadViewsFrom(__DIR__.'/Views/templates/GP247Front', 'gp247-shop-front');

            // Shop's half of the default template (screen/shop_*, account/, auth/,
            // email/, livewire/, partials/) is served straight from this package:
            // append it as the last hint path of the template namespace that
            // gp247/front opened, so an unpublished site still renders every shop
            // screen (US-TPL-template-vendor-resident, modification 20260913T200309).
            // WHY appended, not prepended: app/GP247/Templates (a site's published
            // override) must keep winning, and front's own views come first for the
            // same reason they do at publish time — the two trees never overlap.
            $this->loadViewsFrom(__DIR__.'/Views/templates', 'GP247TemplatePath');

            // Storefront Livewire (storefront Unit, ADR-006): register the interactive
            // cart/filter/checkout components for the front end. Additive: legacy ajax
            // routes remain; these components are opt-in from blade templates (strangler).
            try {
                $this->registerStorefrontLivewire();
            } catch (\Throwable $e) {
                $msg = '#GP247-SHOP::storefront-livewire:: '.$e->getMessage().' - Line: '.$e->getLine().' - File: '.$e->getFile();
                gp247_report($msg);
            }

            // Modern admin (shop-admin Unit, ADR-006/007): register the TailAdmin
            // Livewire screens against the core admin shell via the shared core
            // registrar. Additive + reversible (strangler) — legacy AdminLTE shop
            // admin views untouched. shop inherits from core, not from front.
            $this->registerAdminShell();

            //Add module to homepage admin
            gp247_add_module('homepage', 'gp247-shop-admin::component.order_month');
            gp247_add_module('homepage', 'gp247-shop-admin::component.order_year');
            gp247_add_module('homepage', 'gp247-shop-admin::component.new_order');
            gp247_add_module('homepage', 'gp247-shop-admin::component.new_customer');
            gp247_add_module('homepage', 'gp247-shop-admin::component.top_info');

            try {
                $this->validationExtend();
            } catch (\Throwable $e) {
                $msg = '#GP247-SHOP:: '.$e->getMessage().' - Line: '.$e->getLine().' - File: '.$e->getFile();
                gp247_report($msg);
                echo $msg;
                exit;
            }

            $this->eventRegister();

        }
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/Config/config.php', 'gp247-config');
        if (file_exists(__DIR__.'/Library/Const.php')) {
            require_once(__DIR__.'/Library/Const.php');
        }
        //Add middleware to front
        $configFrontMiddleware = config('gp247-config.front.middleware');
        $configFrontMiddleware[] = 'check.currency';
        $configFrontMiddleware[] = 'check.email_verified';
        config(['gp247-config.front.middleware' => $configFrontMiddleware]);

        //Exclude route language
        $arrShopRouteExcludeLanguage = [
            'customer.index',
            'customer.order_list',
            'customer.order_detail',
            'customer.address_list',
            'customer.update_address',
            'customer.change_password',
            'customer.change_infomation',
            'customer.address_detail',
            'customer.verify',
            'customer.verify_process'
        ];
        $configFrontRoute = config('gp247-config.front.route.GP247_ROUTE_EXCLUDE_LANGUAGE','');
        $configFrontRoute .= ','.implode(',', $arrShopRouteExcludeLanguage);
        config(['gp247-config.front.route.GP247_ROUTE_EXCLUDE_LANGUAGE' => $configFrontRoute]);

        //Config for file manager
        $formatConfig = [
            'folder_name' => 'format_folder_name',
            'startup_view' => 'grid',
            'max_size' => 30000, // size in KB
            'valid_mime' => [
                'image/jpeg',
                'image/pjpeg',
                'image/png',
                'image/gif',
                'image/svg+xml',
                'image/webp',
            ],
        ];
        foreach (['product', 'category', 'brand', 'supplier', 'customer'] as $item) {
            $formatConfig['folder_name'] = $item;
            config(['lfm.folder_categories.'.$item => $formatConfig]);
        }

        //Config for customer auth
        $this->mergeConfigFrom(__DIR__.'/Config/customer_auth_guards.php', 'auth.guards');
        $this->mergeConfigFrom(__DIR__.'/Config/customer_auth_passwords.php', 'auth.passwords');
        $this->mergeConfigFrom(__DIR__.'/Config/customer_auth_providers.php', 'auth.providers');

        // Register shop page-types into the LayoutBlock "Page" scope registry
        // (front.layout_page) from the ShopLayoutPage enum — single source of
        // truth (token + label + registration in one place; ADR
        // front-admin_layout-page-enum-catalog, modification 20260729T054157).
        // Values are i18n label codes (seeded in DataShopInitializeSeeder, group
        // admin.layout_block_page); same runtime-append idiom as the News plugin.
        config([
            'gp247-config.front.layout_page' => array_merge(
                (array) config('gp247-config.front.layout_page', []),
                \GP247\Shop\Support\ShopLayoutPage::registry()
            ),
        ]);

        $this->registerPaymentRequestPurposes();

    }

    /**
     * Payment requests (money in/out outside checkout) belong to the shop: register their
     * defaults — the free-form purpose, the "recorded by hand" gateway — the two order
     * purposes (collect an order's balance, refund an order), the shop's currency list
     * (code => decimals) and the encrypted link-token column. Payment plugins add their
     * gateways and purposes to the same `gp247-config.payment` arrays.
     *
     * WHY whole-array writes: purpose keys contain dots ("order.balance"), which
     * dot-notation config() would split into nested keys.
     *
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-payment-request-balance
     * @aidlc-story US-SADM-order-payment-request-refund
     */
    private function registerPaymentRequestPurposes(): void
    {
        $payment = (array) config('gp247-config.payment', []);
        // Defaults first, so whatever a payment plugin registered (gateways, purposes)
        // is kept whichever provider ran first.
        $payment['purposes'] = array_merge([
            'free' => [
                'label' => 'admin.payment_request.purpose_free',
                'directions' => ['in', 'out'],
                'resolver' => null,
            ],
        ], (array) ($payment['purposes'] ?? []), [
            \GP247\Shop\Payment\OrderBalancePurpose::KEY => [
                'label' => 'admin.order.payment_request_purpose_balance',
                'directions' => ['in'],
                'resolver' => \GP247\Shop\Payment\OrderBalancePurpose::class,
                'available' => [\GP247\Shop\Payment\OrderPurpose::class, 'available'],
                'subject' => [
                    'types' => [\GP247\Shop\Payment\OrderPurpose::SUBJECT_TYPE => 'admin.order.payment_request_subject_order'],
                    'label' => 'admin.order.payment_request_subject_order',
                    'help' => 'admin.order.payment_request_subject_help',
                ],
            ],
            \GP247\Shop\Payment\OrderRefundPurpose::KEY => [
                'label' => 'admin.order.payment_request_purpose_refund',
                'directions' => ['out'],
                'resolver' => \GP247\Shop\Payment\OrderRefundPurpose::class,
                'available' => [\GP247\Shop\Payment\OrderPurpose::class, 'available'],
                'subject' => [
                    'types' => [\GP247\Shop\Payment\OrderPurpose::SUBJECT_TYPE => 'admin.order.payment_request_subject_order'],
                    'label' => 'admin.order.payment_request_subject_order',
                    'help' => 'admin.order.payment_request_subject_help',
                ],
            ],
        ]);
        // WHY a [class, method] callable and not a closure: config:cache must be able to
        // serialise the config.
        $payment['currencies'] ??= [\GP247\Shop\Payment\OrderPurpose::class, 'currencyCatalog'];
        $payment['currency_labels'] ??= [\GP247\Shop\Payment\OrderPurpose::class, 'currencyLabels'];
        $payment['gateways'] = array_merge([
            'manual' => [
                'label' => 'admin.payment_request.gateway_manual',
                'capabilities' => ['collect', 'refund', 'payout'],
                'driver' => \GP247\Shop\Payment\Gateways\ManualGateway::class,
            ],
        ], (array) ($payment['gateways'] ?? []));
        config(['gp247-config.payment' => $payment]);

        // The payment link token is encrypted at rest: let gp247:doctor and the key
        // rotation cover it (they skip the column while the table is not there yet).
        $security = (array) config('gp247-config.security', []);
        $columns = (array) ($security['encrypted_columns'] ?? []);
        $columns['payment_request'] = array_values(array_unique(array_merge((array) ($columns['payment_request'] ?? []), ['public_token_enc'])));
        $security['encrypted_columns'] = $columns;
        config(['gp247-config.security' => $security]);
    }

    /**
     * Register the modern (Livewire/TailAdmin) shop-admin shell: the component
     * view namespace and the full-page routes inside the core admin group, via
     * the shared core registrar (AdminShellResourceRegistrar). shop declares only
     * its resources; the mechanism lives once in core (rule ui-tailadmin P3).
     * Components reference Livewire classes by ::class and are routed only when
     * the class exists, so screens light up as they ship (strangler).
     *
     * @return void
     */
    protected function registerAdminShell()
    {
        // No separate loadViewsFrom here: the modern Livewire screens' views live
        // in the same Views/admin tree as the legacy views and resolve under the
        // single `gp247-shop-admin::` namespace already registered above — avoids
        // registering two Blade namespaces for one physical root.
        // After the cutover (PA-1), all shop-admin screens are routed via
        // Routes/Admin/*.php (loaded by core routes.php) under clean `admin_<res>.*`
        // names at `/<res>/...`, and every manager's `baseRoute()` targets those.
        // The shared registrar therefore no longer registers any routes here — doing
        // so only duplicated the same components under an orphaned `/shop-admin/<res>`
        // URL space.
        //
        // What remains essential is the Livewire component namespace: components
        // resolve as `gp247-shop-admin::<name>` (e.g. gp247-shop-admin::product-manager)
        // and that name travels in every `livewire/update` round-trip, so the
        // namespace must stay registered or interactivity breaks. Register it
        // directly — same pattern as registerStorefrontLivewire().
        if (!class_exists(\Livewire\Livewire::class)) {
            return;
        }

        \Livewire\Livewire::addNamespace(
            'gp247-shop-admin',
            classNamespace: 'GP247\\Shop\\Admin\\Livewire',
        );

        $this->registerConfigHubTab();
    }

    /**
     * Contribute the shop configuration screen as a tab on the core Configuration
     * hub (SettingsHub), so every config lives on one screen
     * (US-SADM-shop-config-into-hub). Core exposes the registry seam; shop plugs in
     * here without core referencing shop (ADR admin-shell_config-hub-tab-registry).
     *
     * Guarded by gp247_shop_installed() — the DB-level install truth, not "package
     * present" (gp247.md §0): a half-installed shop must not push a tab that would
     * then fail to render. The tab declares its own authUri so the hub gates it by
     * `admin_shop_config`, unchanged.
     *
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-shop-config-into-hub
     * @aidlc-adr admin-shell_config-hub-tab-registry
     */
    protected function registerConfigHubTab(): void
    {
        if (!class_exists(\GP247\Core\AdminShell\Support\SettingsHubTabRegistry::class)) {
            return;
        }
        if (!function_exists('gp247_shop_installed') || !gp247_shop_installed()) {
            return;
        }

        $prefix = defined('GP247_ADMIN_PREFIX') ? GP247_ADMIN_PREFIX : 'gp247_admin';

        \GP247\Core\AdminShell\Support\SettingsHubTabRegistry::register(
            key: 'shop',
            label: 'admin.menu_titles.shop_config',
            component: 'gp247-shop-admin::shop-config-form',
            authUri: $prefix . '/shop_config',
            order: 10,
        );
    }

    /**
     * Register the storefront Livewire components (CartManager, etc.).
     *
     * Uses addNamespace so components resolve under 'gp247-shop-front' without
     * requiring Composer autoload at the host — consistent with the admin-shell
     * and plugin scaffold patterns. Guarded by class_exists so sites without
     * Livewire are unaffected (NFR-AVAIL-002, shared-host support).
     *
     * @return void
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    protected function registerStorefrontLivewire(): void
    {
        if (!class_exists(\Livewire\Livewire::class)) {
            return;
        }

        \Livewire\Livewire::addNamespace(
            'gp247-shop-front',
            classNamespace: 'GP247\\Shop\\Front\\Livewire',
        );

        // Livewire's own `livewire/update` endpoint only runs the `web`
        // middleware group (see HandleRequests::ensureListeningForUpdates),
        // never the app's `front` group — so `check.currency` never re-runs
        // there and ShopCurrency::$exchange_rate silently resets to its
        // class default (1) for the whole AJAX request. Every price
        // conversion done inside a Livewire action (ProductFilter::applyPrice,
        // price rendering on re-render) would then use rate=1 instead of the
        // shopper's actual session currency. Mirrors the persistent-middleware
        // fix already applied to admin RBAC (see
        // AdminShellServiceProvider::boot, ADR-001) for the same class of bug.
        \Livewire\Livewire::addPersistentMiddleware([
            CurrencyMiddleware::class,
        ]);
    }

    public function bootDefault()
    {
        view()->share('modelProduct', (new \GP247\Shop\Models\ShopProduct));
        view()->share('modelOrder', (new \GP247\Shop\Models\ShopOrder));
        view()->share('modelCategory', (new \GP247\Shop\Models\ShopCategory));
        view()->share('modelBrand', (new \GP247\Shop\Models\ShopBrand));
    }

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'check.currency'     => CurrencyMiddleware::class,
        'check.email_verified'     => EmailIsVerifiedMiddleware::class,
        //Customer auth
        'customer.auth' => CustomerAuth::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected function middlewareGroups()
    {
        return [
            'customer' => config('gp247-config.shop.middleware'),
        ];
    }

    /**
     * Register the route middleware.
     *
     * @return void
     */
    protected function registerRouteMiddleware()
    {
        // register route middleware.
        foreach ($this->routeMiddleware as $key => $middleware) {
            app('router')->aliasMiddleware($key, $middleware);
        }

        // register middleware group.
        foreach ($this->middlewareGroups() as $key => $middleware) {
            app('router')->middlewareGroup($key, array_values($middleware));
        }
    }

    /**
     * Validattion extend
     *
     * @return  [type]  [return description]
     */
    protected function validationExtend()
    {
        Validator::extend('product_sku_unique', function ($attribute, $value, $parameters, $validator) {
            $productId = $parameters[0] ?? '';
            return (new AdminProduct)
                ->checkProductValidationAdmin('sku', $value, $productId, session('adminStoreId'));
        });

        Validator::extend('product_alias_unique', function ($attribute, $value, $parameters, $validator) {
            $productId = $parameters[0] ?? '';
            return (new AdminProduct)
                ->checkProductValidationAdmin('alias', $value, $productId, session('adminStoreId'));
        });
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/Views/admin' => resource_path('views/vendor/gp247-shop-admin')], 'gp247:shop-view-admin');
            // WHY: 'Default' was removed entirely (modification 20260705T124936,
            // ADR-014 Amend #1) — GP247Front is now the sole/default template.
            // OPT-IN since modification 20260913T200309: gp247:shop-install no
            // longer runs this. These views are served from the package; a site
            // publishes them (or single files via gp247:template-publish) only to
            // override, and then stops receiving updates for what it published.
            $this->publishes([__DIR__.'/Views/templates/GP247Front' => app_path('GP247/Templates/GP247Front')], 'gp247:shop-view-front');
        }
    }

    //Event register
    protected function eventRegister()
    {
        //
    }
}
