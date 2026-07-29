<?php

namespace GP247\Shop\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use GP247\Core\Models\AdminMenu;
use GP247\Shop\Models\ShopCurrency;
use GP247\Shop\Models\ShopPaymentStatus;
use GP247\Shop\Models\ShopShippingStatus;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Core\Models\AdminHome;
use GP247\Core\Models\AdminConfig;

class DataShopInitializeSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Preparing update data version
        $this->updateDataVersion();

        // Delete old data
        $checkIdBlockShop = AdminMenu::where('key', 'ADMIN_SHOP_BLOCK')->first();
        $checkIdBlockCatalog = AdminMenu::where('key', 'ADMIN_SHOP_CATALOG')->first();
        $checkIdBlockOrder = AdminMenu::where('key', 'ADMIN_SHOP_ORDER')->first();
        $checkIdBlockCustomer = AdminMenu::where('key', 'ADMIN_SHOP_CUSTOMER')->first();
        $checkIdBlockReport = AdminMenu::where('key', 'ADMIN_SHOP_REPORT')->first();
        $checkIdBlockSetting = AdminMenu::where('key', 'ADMIN_SHOP_SETTING')->first();
        if ($checkIdBlockShop) {
            AdminMenu::where('id', $checkIdBlockShop->id)->delete();
            AdminMenu::whereIn('parent_id', 
            [
                $checkIdBlockShop->id ?? 0, 
                $checkIdBlockOrder->id ?? 0, 
                $checkIdBlockCatalog->id ?? 0, 
                $checkIdBlockReport->id ?? 0, 
                $checkIdBlockCustomer->id ?? 0, 
                $checkIdBlockSetting->id ?? 0
            ])
            ->delete();
        }




        // Insert new data
        $idBlockShop = AdminMenu::insertGetId(
            [
                'parent_id' => 0,
                'sort'      => 90,
                'title'     => 'admin.menu_titles.ADMIN_SHOP_BLOCK',
                'icon'      => 'nav-icon fab fa-shopify',
                'key'       => 'ADMIN_SHOP_BLOCK',
            ]
        );
        $idBlockCatalog = AdminMenu::insertGetId(
            [
                'parent_id' => $idBlockShop,
                'sort'      => 70,
                'title'     => 'admin.menu_titles.ADMIN_SHOP_CATALOG',
                'icon'      => 'nav-icon fas fa-folder-open',
                'key'       => 'ADMIN_SHOP_CATALOG',
            ]
        );
        $idBlockOrder = AdminMenu::insertGetId(
            [
                'parent_id' => $idBlockShop,
                'sort'      => 60,
                'title'     => 'admin.menu_titles.ADMIN_SHOP_ORDER',
                'icon'      => 'nav-icon fas fa-shopping-basket',
                'key'       => 'ADMIN_SHOP_ORDER',
            ]
        );
        $idBlockCustomer = AdminMenu::insertGetId(
            [
                'parent_id' => $idBlockShop,
                'sort'      => 50,
                'title'     => 'admin.menu_titles.ADMIN_SHOP_CUSTOMER',
                'icon'      => 'nav-icon fas fa-user',
                'key'       => 'ADMIN_SHOP_CUSTOMER',
            ]
        );

        $idBlockShipping = AdminMenu::insertGetId(
            [
                'parent_id' => $idBlockShop,
                'sort'      => 40,
                'title'     => 'admin.menu_titles.ADMIN_SHOP_SHIPPING',
                'icon'      => 'nav-icon fas fa-truck',
                'key'       => 'ADMIN_SHOP_SHIPPING',
            ]
        );
        $idBlockPayment = AdminMenu::insertGetId(
            [
                'parent_id' => $idBlockShop,
                'sort'      => 30,
                'title'     => 'admin.menu_titles.ADMIN_SHOP_PAYMENT',
                'icon'      => 'nav-icon fas fa-recycle',
                'key'       => 'ADMIN_SHOP_PAYMENT',
            ]
        );
        $idBlockReport = AdminMenu::insertGetId(
            [
                'parent_id' => $idBlockShop,
                'sort'      => 20,
                'title'     => 'admin.menu_titles.ADMIN_SHOP_REPORT',
                'icon'      => 'nav-icon fas fa-chart-line',
                'key'       => 'ADMIN_SHOP_REPORT',
            ]
        );
        $idBlockSetting = AdminMenu::insertGetId(
            [
                'parent_id' => $idBlockShop,
                'sort'      => 10,
                'title'     => 'admin.menu_titles.ADMIN_SHOP_SETTING',
                'icon'      => 'nav-icon fas fa-cogs',
                'key'       => 'ADMIN_SHOP_SETTING',
            ]
        );



        ShopCurrency::insertOrIgnore(
           [
                ['id' => '1','name' => 'USD Dola','code' => 'USD','symbol' => '$','exchange_rate' => '1','precision' => '2','symbol_first' => '1','thousands' => ',','status' => '1','sort' => '0'],
                ['id' => '2','name' => 'VietNam Dong','code' => 'VND','symbol' => '₫','exchange_rate' => '20000','precision' => '0','symbol_first' => '0','thousands' => ',','status' => '1','sort' => '1'],
           ]
        );

        AdminMenu::insertOrIgnore(
            [
                //Catalog manager
                ['parent_id' => $idBlockCatalog,'sort' => 40,'title' => 'admin.menu_titles.product','icon' => 'far fa-file-image','uri' => 'admin::product','key' => null,'type' => 0],
                ['parent_id' => $idBlockCatalog,'sort' => 30,'title' => 'admin.menu_titles.category','icon' => 'fas fa-folder-open','uri' => 'admin::category','key' => null,'type' => 0],
                ['parent_id' => $idBlockCatalog,'sort' => 20,'title' => 'admin.menu_titles.supplier','icon' => 'fas fa-user-secret','uri' => 'admin::supplier','key' => null,'type' => 0],
                ['parent_id' => $idBlockCatalog,'sort' => 10,'title' => 'admin.menu_titles.brand','icon' => 'fas fa-university','uri' => 'admin::brand','key' => null,'type' => 0],
                // Order manager
                ['parent_id' => $idBlockOrder,'sort' => 40,'title' => 'admin.menu_titles.order','icon' => 'fas fa-shopping-cart','uri' => 'admin::order','key' => null,'type' => 0],

                //Customer manager
                ['parent_id' => $idBlockCustomer,'sort' => 30,'title' => 'admin.menu_titles.customer','icon' => 'fas fa-user','uri' => 'admin::customer','key' => null,'type' => 0],
                ['parent_id' => $idBlockCustomer,'sort' => 20,'title' => 'admin.menu_titles.subscribe','icon' => 'fas fa-user-circle','uri' => 'admin::subscribe','key' => null,'type' => 0],
                //Report manager
                ['parent_id' => $idBlockReport,'sort' => 30,'title' => 'admin.menu_titles.report_product','icon' => 'fas fa-bars','uri' => 'admin::report/product','key' => null,'type' => 0],
                //Setting manager
                ['parent_id' => $idBlockSetting,'sort' => 100,'title' => 'admin.menu_titles.currency','icon' => 'far fa-money-bill-alt','uri' => 'admin::currency','key' => null,'type' => 0],
                ['parent_id' => $idBlockSetting,'sort' => 90,'title' => 'admin.menu_titles.shipping_status','icon' => 'fas fa-truck','uri' => 'admin::shipping_status','key' => null,'type' => 0],
                ['parent_id' => $idBlockSetting,'sort' => 80,'title' => 'admin.menu_titles.order_status','icon' => 'fas fa-asterisk','uri' => 'admin::order_status','key' => null,'type' => 0],
                ['parent_id' => $idBlockSetting,'sort' => 70,'title' => 'admin.menu_titles.payment_status','icon' => 'fas fa-recycle','uri' => 'admin::payment_status','key' => null,'type' => 0],
                ['parent_id' => $idBlockSetting,'sort' => 60,'title' => 'admin.menu_titles.attribute_group','icon' => 'fas fa-braille','uri' => 'admin::attribute_group','key' => null,'type' => 0],
                ['parent_id' => $idBlockSetting,'sort' => 40,'title' => 'admin.menu_titles.tax','icon' => 'far fa-calendar-minus','uri' => 'admin::tax','key' => null,'type' => 0],
                ['parent_id' => $idBlockSetting,'sort' => 10,'title' => 'admin.menu_titles.shop_config','icon' => 'fas fa-sliders-h','uri' => 'admin::shop_config','key' => null,'type' => 0],


            ]
        );



        $dataPaymentStatus = $this->dataPaymentStatus();
        ShopPaymentStatus::insertOrIgnore($dataPaymentStatus);

        $dataShippingStatus = $this->dataShippingStatus();
        ShopShippingStatus::insertOrIgnore($dataShippingStatus);

        $dataOrderStatus = $this->dataOrderStatus();
        ShopOrderStatus::insertOrIgnore($dataOrderStatus);


        //Add module to homepage admin
        AdminHome::whereIn('view',
            [
                'gp247-shop-admin::component.order_month',
                'gp247-shop-admin::component.order_year',
                'gp247-shop-admin::component.new_order',
                'gp247-shop-admin::component.new_customer',
                'gp247-shop-admin::component.top_info'
            ]
        )->delete();
        AdminHome::insertOrIgnore(
            [
                ['size' => 12, 'sort'=> 100,'view' => 'gp247-shop-admin::component.top_info','status' => 1],
                ['size' => 6, 'sort'=> 90,'view' => 'gp247-shop-admin::component.order_month','status' => 1],
                ['size' => 6, 'sort'=> 85,'view' => 'gp247-shop-admin::component.order_year','status' => 1],
                ['size' => 6, 'sort'=> 70,'view' => 'gp247-shop-admin::component.new_order','status' => 1],
                ['size' => 6, 'sort'=> 60,'view' => 'gp247-shop-admin::component.new_customer','status' => 1],
            ]
        );


        $dataConfig = $this->dataConfigShop();
        AdminConfig::insertOrIgnore($dataConfig);

        // Language rows are seeded by the dedicated DataShopLanguageSeeder so
        // they can be refreshed independently of the shop's menu/config data.
        $this->call(DataShopLanguageSeeder::class);
    }

    public function dataPaymentStatus() {
        $dataPaymentStatus = [
            ['id' => '1','name' => 'Unpaid'],
            ['id' => '2','name' => 'Partial payment'],
            ['id' => '3','name' => 'Paid'],
            ['id' => '4','name' => 'Refund'],
        ];
        return $dataPaymentStatus;
    }

    public function dataShippingStatus() {
        $dataShippingStatus = [
            ['id' => '1','name' => 'Not sent'],
            ['id' => '2','name' => 'Sending'],
            ['id' => '3','name' => 'Shipping done'],
            ['id' => '4','name' => 'Refunded'],
        ];
        return $dataShippingStatus;
    }

    public function dataAtrributeGroup() {
        $dataAtrributeGroup = [
            ['id' => 1,'name' => 'Color','status' => '1','sort' => '1','type' => 'radio'],
            ['id' => 2,'name' => 'Size','status' => '1','sort' => '2','type' => 'select'],
        ];
        return $dataAtrributeGroup;
    }


    public function dataOrderStatus() {
        $dataOrderStatus = [
            ['id' => '1','name' => 'New'],
            ['id' => '2','name' => 'Processing'],
            ['id' => '3','name' => 'Hold'],
            ['id' => '4','name' => 'Canceled'],
            ['id' => '5','name' => 'Done'],
            ['id' => '6','name' => 'Failed'],
            ['id' => '7','name' => 'Refunded'],
        ];
        return $dataOrderStatus;
    }

    public function dataConfigShop() {
        $dataConfig = [
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_brand','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.brand','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_brand_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_supplier','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.supplier','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_supplier_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_price','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.price','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_price_required','value' => '1','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_cost','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.cost','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_cost_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_promotion','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.promotion','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_promotion_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_stock','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.stock','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_stock_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_kind','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.kind','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_tag','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.tag','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_tag_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_attribute','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.attribute','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_attribute_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_available','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.available','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_available_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_weight','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.weight','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_weight_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_length','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.length','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_length_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_tag','value' => '1','sort' => '0','detail' => 'admin.product.config_manager.tag','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_tag_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_display_out_of_stock','value' => '1','sort' => '19','detail' => 'admin.product.config_manager.product_display_out_of_stock','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'show_date_available','value' => '1','sort' => '21','detail' => 'admin.product.config_manager.show_date_available','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_use_button_add_to_cart','value' => '1','sort' => '22','detail' => 'admin.product.config_manager.product_use_button_add_to_cart','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_use_button_wishlist','value' => '1','sort' => '23','detail' => 'admin.product.config_manager.product_use_button_wishlist','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_use_button_compare','value' => '1','sort' => '24','detail' => 'admin.product.config_manager.product_use_button_compare','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_tax','value' => 'auto','sort' => '0','detail' => 'admin.product.config_manager.tax','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_qty_decimal','value' => '0','sort' => '25','detail' => 'admin.product.config_manager.product_qty_decimal','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_email','value' => '1','sort' => '0','detail' => 'admin.customer.config_manager.email','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_email_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_lastname','value' => '0','sort' => '1','detail' => 'admin.customer.config_manager.lastname','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_lastname_required','value' => '0','sort' => '1','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_address1','value' => '1','sort' => '2','detail' => 'admin.customer.config_manager.address1','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_address1_required','value' => '1','sort' => '2','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_address2','value' => '1','sort' => '2','detail' => 'admin.customer.config_manager.address2','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_address2_required','value' => '0','sort' => '2','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_address3','value' => '0','sort' => '2','detail' => 'admin.customer.config_manager.address3','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_address3_required','value' => '0','sort' => '2','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_company','value' => '0','sort' => '0','detail' => 'admin.customer.config_manager.company','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_company_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_postcode','value' => '0','sort' => '0','detail' => 'admin.customer.config_manager.postcode','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_postcode_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_country','value' => '0','sort' => '0','detail' => 'admin.customer.config_manager.country','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_country_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_group','value' => '0','sort' => '0','detail' => 'admin.customer.config_manager.group','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_group_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_birthday','value' => '0','sort' => '0','detail' => 'admin.customer.config_manager.birthday','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_birthday_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_sex','value' => '0','sort' => '0','detail' => 'admin.customer.config_manager.sex','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_sex_required','value' => '0','sort' => '0','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_phone','value' => '0','sort' => '0','detail' => 'admin.customer.config_manager.phone','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_phone_required','value' => '0','sort' => '1','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_name_kana','value' => '0','sort' => '0','detail' => 'admin.customer.config_manager.name_kana','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_name_kana_required','value' => '0','sort' => '1','detail' => '','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'customer_config','key' => 'customer_verify','value' => '0','sort' => '1','detail' => 'admin.customer.config_manager.customer_verify','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'shop_allow_guest','value' => '1','sort' => '11','detail' => 'admin.order.shop_allow_guest','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'product_preorder','value' => '1','sort' => '18','detail' => 'admin.order.product_preorder','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'product_buy_out_of_stock','value' => '1','sort' => '20','detail' => 'admin.order.product_buy_out_of_stock','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'use_shipping','value' => '0','sort' => '20','detail' => 'admin.order.use_shipping','store_id' => GP247_STORE_ID_GLOBAL],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'use_payment','value' => '0','sort' => '20','detail' => 'admin.order.use_payment','store_id' => GP247_STORE_ID_GLOBAL],

        ];
        return $dataConfig;
    }


    public function updateDataVersion() {

    }

}
