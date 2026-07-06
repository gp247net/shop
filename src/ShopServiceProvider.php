<?php

namespace GP247\Shop;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use GP247\Shop\Commands\ShopInstall;
use GP247\Shop\Commands\ShopUninstall;
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
            $this->loadViewsFrom(__DIR__.'/Views/front', 'gp247-shop-front');

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

        //Process layout_page
        $layoutPage = config('gp247-config.front.layout_page');
        $layoutPage['shop_item_list'] = 'shop_item_list';
        $layoutPage['shop_product_detail'] = 'shop_product_detail';
        $layoutPage['shop_product_list'] = 'shop_product_list';
        $layoutPage['shop_profile'] = 'shop_profile';
        $layoutPage['shop_cart'] = 'shop_cart';
        $layoutPage['shop_checkout'] = 'shop_checkout';
        $layoutPage['shop_wishlist'] = 'shop_wishlist';
        $layoutPage['shop_compare'] = 'shop_compare';
        $layoutPage['shop_auth'] = 'shop_auth';
        $layoutPage['shop_search'] = 'shop_search';
        config(['gp247-config.front.layout_page' => $layoutPage]);

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
        \GP247\Core\AdminShell\Infrastructure\AdminShellResourceRegistrar::register(
            'gp247-shop-admin',
            'GP247\\Shop\\Admin\\Livewire',
            'shop-admin',
            [
                // Two-column managers (form + list on one page, P1): single route each.
                'brand' => [\GP247\Shop\Admin\Livewire\BrandManager::class],
                // Bespoke product screen (multilingual + variants + composition…), US-SADM-001.
                'product' => [\GP247\Shop\Admin\Livewire\ProductManager::class],
                'supplier' => [\GP247\Shop\Admin\Livewire\SupplierManager::class],
                'tax' => [\GP247\Shop\Admin\Livewire\TaxManager::class],
                'currency' => [\GP247\Shop\Admin\Livewire\CurrencyManager::class],
                'category' => [\GP247\Shop\Admin\Livewire\CategoryManager::class],
                'subscribe' => [\GP247\Shop\Admin\Livewire\SubscribeManager::class],
                'customer' => [\GP247\Shop\Admin\Livewire\CustomerManager::class],
                'attribute_group' => [\GP247\Shop\Admin\Livewire\AttributeGroupManager::class],
                // Bespoke order screen (list + detail + workflow + line-item), US-SADM-003.
                'order' => [\GP247\Shop\Admin\Livewire\OrderManager::class],
                'order_status' => [\GP247\Shop\Admin\Livewire\OrderStatusManager::class],
                'payment_status' => [\GP247\Shop\Admin\Livewire\PaymentStatusManager::class],
                'shipping_status' => [\GP247\Shop\Admin\Livewire\ShippingStatusManager::class],
                // Read-only report dashboard (US-SADM-006).
                'report' => [\GP247\Shop\Admin\Livewire\ReportManager::class],
                // Single-page config screen.
                'config' => [\GP247\Shop\Admin\Livewire\ShopConfigForm::class],
            ],
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
            $this->publishes([__DIR__.'/Views/front' => app_path('GP247/Templates/GP247Front')], 'gp247:shop-view-front');
        }
    }

    //Event register
    protected function eventRegister()
    {
        //
    }
}
