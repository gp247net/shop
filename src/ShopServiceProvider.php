<?php

namespace GP247\Shop;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use GP247\Shop\Commands\ShopInstall;
use GP247\Shop\Commands\ShopUninstall;
use GP247\Shop\Commands\ShopSample;
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
            if (!is_dir($directory = app_path('GP247/Shop/Controllers/Admin'))) {
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

        $this->mergeConfigFrom(__DIR__.'/Config/customer_auth_guards.php', 'auth.guards');
        $this->mergeConfigFrom(__DIR__.'/Config/customer_auth_passwords.php', 'auth.passwords');
        $this->mergeConfigFrom(__DIR__.'/Config/customer_auth_providers.php', 'auth.providers');

    }

    public function bootDefault()
    {
        view()->share('modelProduct', (new \GP247\Shop\Models\ShopProduct));
        view()->share('modelOrder', (new \GP247\Shop\Models\ShopOrder));
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
            $this->publishes([__DIR__.'/Views/admin' => resource_path('views/vendor/gp247-shop-admin')], 'gp247:view-shop-admin');
            $this->publishes([__DIR__.'/Views/front' => app_path('GP247/Templates/Default')], 'gp247:view-shop-front');
        }
    }

    //Event register
    protected function eventRegister()
    {
        //
    }
}
