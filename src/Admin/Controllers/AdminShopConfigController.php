<?php

namespace GP247\Shop\Admin\Controllers;

use GP247\Core\Controllers\RootAdminController;
use GP247\Core\Models\AdminLanguage;
use GP247\Core\Models\AdminConfig;
use GP247\Core\Models\AdminPage;
use GP247\Shop\Models\ShopTax;

class AdminShopConfigController extends RootAdminController
{
    public $templates;
    public $languages;
    public $timezones;

    public function __construct()
    {
        parent::__construct();
        foreach (timezone_identifiers_list() as $key => $value) {
            $timezones[$value] = $value;
        }
        $this->templates = gp247_extension_get_installed(type: 'Templates', active: true);
        $this->languages = AdminLanguage::getListActive();
        $this->timezones = $timezones;
    }

    public function index()
    {
        $id = session('adminStoreId');
        $data = [
            'title' => gp247_language_render('admin.menu_titles.shop_config'),
            'subTitle' => '',
        ];

        // Sendmail config
        $dataSendmailConfigDefault = [
            'code' => 'sendmail_config',
            'storeId' => $id,
            'keyBy' => 'key',
        ];
        $sendmailConfigsDefault = AdminConfig::getListConfigByCode($dataSendmailConfigDefault);

        // Customer config
        $dataCustomerConfigDefault = [
            'code' => 'customer_config',
            'storeId' => GP247_STORE_ID_GLOBAL,
            'keyBy' => 'key',
        ];
        $customerConfigsDefault = AdminConfig::getListConfigByCode($dataCustomerConfigDefault);


        $dataCustomerConfigAttribute = [
            'code' => 'customer_config_attribute',
            'storeId' => GP247_STORE_ID_GLOBAL,
            'keyBy' => 'key',
        ];
        $customerConfigsAttribute = AdminConfig::getListConfigByCode($dataCustomerConfigAttribute);

        $dataCustomerConfigAttributeRequired = [
            'code' => 'customer_config_attribute_required',
            'storeId' => GP247_STORE_ID_GLOBAL,
            'keyBy' => 'key',
        ];
        $customerConfigsAttributeRequired = AdminConfig::getListConfigByCode($dataCustomerConfigAttributeRequired);
        //End customer

        //Product config
        $taxs = (new ShopTax)->getListAll();
        $productConfigQuery = [
            'code' => 'product_config',
            'storeId' => GP247_STORE_ID_GLOBAL,
            'keyBy' => 'key',
        ];
        $productConfig = AdminConfig::getListConfigByCode($productConfigQuery);

        $productConfigAttributeQuery = [
            'code' => 'product_config_attribute',
            'storeId' => GP247_STORE_ID_GLOBAL,
            'keyBy' => 'key',
        ];
        $productConfigAttribute = AdminConfig::getListConfigByCode($productConfigAttributeQuery);

        $productConfigAttributeRequiredQuery = [
            'code' => 'product_config_attribute_required',
            'storeId' => GP247_STORE_ID_GLOBAL,
            'keyBy' => 'key',
        ];
        $productConfigAttributeRequired = AdminConfig::getListConfigByCode($productConfigAttributeRequiredQuery);

        $orderConfigQuery = [
            'code' => 'order_config',
            'storeId' => GP247_STORE_ID_GLOBAL,
            'keyBy' => 'key',
        ];
        $orderConfig = AdminConfig::getListConfigByCode($orderConfigQuery);

        $configDisplayQuery = [
            'code' => 'display_config',
            'storeId' => $id,
            'keyBy' => 'key',
        ];
        $configDisplay = AdminConfig::getListConfigByCode($configDisplayQuery);

        $configCaptchaQuery = [
            'code' => 'captcha_config',
            'storeId' => $id,
            'keyBy' => 'key',
        ];
        $configCaptcha = AdminConfig::getListConfigByCode($configCaptchaQuery);

        $configCustomizeQuery = [
            'code' => 'admin_custom_config',
            'storeId' => $id,
            'keyBy' => 'key',
        ];
        $configCustomize = AdminConfig::getListConfigByCode($configCustomizeQuery);


        $configLayoutQuery = [
            'code' => 'config_layout',
            'storeId' => $id,
            'keyBy' => 'key',
        ];
        $configLayout = AdminConfig::getListConfigByCode($configLayoutQuery);

        $data['smtp_method'] = ['' => 'None Secirity', 'TLS' => 'TLS', 'SSL' => 'SSL'];
        $data['captcha_page'] = [
            'register' => gp247_language_render('admin.captcha.captcha_page_register'),
            'forgot'   => gp247_language_render('admin.captcha.captcha_page_forgot_password'),
            'checkout' => gp247_language_render('admin.captcha.captcha_page_checkout'),
            'contact'  => gp247_language_render('admin.captcha.captcha_page_contact'),
            'review'   => gp247_language_render('admin.captcha.captcha_page_review'),
        ];
        //End email
        
        $data['sendmailConfigsDefault']           = $sendmailConfigsDefault;
        $data['customerConfigsDefault']           = $customerConfigsDefault;
        $data['customerConfigsAttribute']         = $customerConfigsAttribute;
        $data['customerConfigsAttributeRequired'] = $customerConfigsAttributeRequired;
        $data['taxs']                            = $taxs;
        $data['productConfig']                   = $productConfig;
        $data['productConfigAttribute']          = $productConfigAttribute;
        $data['productConfigAttributeRequired']  = $productConfigAttributeRequired;
        $data['configLayout']                    = $configLayout;
        $data['pluginCaptchaInstalled']         = gp247_captcha_get_plugin_installed();
        $data['configDisplay']                   = $configDisplay;
        $data['orderConfig']                     = $orderConfig;
        $data['configCaptcha']                   = $configCaptcha;
        $data['configCustomize']                 = $configCustomize;
        $data['templates']                       = $this->templates;
        $data['timezones']                       = $this->timezones;
        $data['languages']                       = $this->languages;
        $data['storeId']                         = $id;
        $data['urlUpdateConfig']                 = gp247_route_admin('admin_config.update');
        $data['urlUpdateConfigGlobal']           = gp247_route_admin('admin_config_global.update');

        return view('gp247-shop-admin::screen.config_shop_default')
            ->with($data);
    }
}
