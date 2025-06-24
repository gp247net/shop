<?php

namespace GP247\Shop\DB\seeders;

use Illuminate\Database\Seeder;
use GP247\Core\Models\AdminConfig;
use GP247\Core\Models\AdminStore;
use GP247\Front\Models\FrontLink;
use GP247\Front\Models\FrontLayoutBlock;


class DataShopDefaultSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $storeId = empty(session('lastStoreId')) ? GP247_STORE_ID_ROOT : session('lastStoreId');

        $store = AdminStore::find($storeId);

        if (!$store) {
            gp247_report(msg: 'Store # ' . $storeId . ' not found in command DataShopDefaultSeeder');
            return;
        }


        $dataConfig = $this->dataConfigShop($storeId);
        AdminConfig::insertOrIgnore($dataConfig);
        
        $links = [
            [
                'name' => 'front.all_product',
                'url' => 'route_front::product.all',
                'target' => '_self', 
                'group' => 'menu', // menu main
                'sort' => 2,
                'status' => 1,
            ],

        ];
        foreach ($links as $link) {
            $frontLink = FrontLink::create([
                'id' => (string)\Illuminate\Support\Str::orderedUuid(),
                'name' => $link['name'],
                'url' => $link['url'],
                'target' => $link['target'],
                'group' => $link['group'],
                'sort' => $link['sort'],
                'status' => $link['status'],
                'module' => 'gp247/shop',
            ]);

            // Attach to store using model relationship
            $frontLink->stores()->attach($storeId);
        }

        // Add new layout block
        FrontLayoutBlock::insert([
            [
                'id'       => (string)\Illuminate\Support\Str::orderedUuid(),
                'name'     => 'Product Home (Shop Package)',
                'position' => 'bottom',
                'page'     => 'front_home',
                'text'     => 'shop_product_home',
                'type'     => 'view',
                'sort'     => 10,
                'status'   => 1,
                'template' => $store->template,
                'store_id' => $storeId,
            ],
            [
                'id'       => (string)\Illuminate\Support\Str::orderedUuid(),
                'name'     => 'Product Last View (Shop Package)',
                'position' => 'left',
                'page'     => 'shop_product_detail,shop_product_list,shop_home,shop_search',
                'text'     => 'shop_product_last_view',
                'type'     => 'view',
                'sort'     => 20,
                'status'   => 1,
                'template' => $store->template,
                'store_id' => $storeId,
            ]
        ]);
    

    }

        
    public function dataConfigShop($storeId) {
        $dataConfig = [
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_brand','value' => '1','sort' => '0','detail' => 'product.config_manager.brand','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_brand_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_supplier','value' => '1','sort' => '0','detail' => 'product.config_manager.supplier','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_supplier_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_price','value' => '1','sort' => '0','detail' => 'product.config_manager.price','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_price_required','value' => '1','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_cost','value' => '1','sort' => '0','detail' => 'product.config_manager.cost','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_cost_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_promotion','value' => '1','sort' => '0','detail' => 'product.config_manager.promotion','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_promotion_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_stock','value' => '1','sort' => '0','detail' => 'product.config_manager.stock','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_stock_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_kind','value' => '1','sort' => '0','detail' => 'product.config_manager.kind','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_tag','value' => '1','sort' => '0','detail' => 'product.config_manager.tag','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_tag_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_attribute','value' => '1','sort' => '0','detail' => 'product.config_manager.attribute','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_attribute_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_available','value' => '1','sort' => '0','detail' => 'product.config_manager.available','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_available_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_weight','value' => '1','sort' => '0','detail' => 'product.config_manager.weight','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_weight_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_length','value' => '1','sort' => '0','detail' => 'product.config_manager.length','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_length_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute','key' => 'product_tag','value' => '1','sort' => '0','detail' => 'product.config_manager.tag','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config_attribute_required','key' => 'product_tag_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_display_out_of_stock','value' => '1','sort' => '19','detail' => 'product.config_manager.product_display_out_of_stock','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'show_date_available','value' => '1','sort' => '21','detail' => 'product.config_manager.show_date_available','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_use_button_add_to_cart','value' => '1','sort' => '22','detail' => 'product.config_manager.product_use_button_add_to_cart','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_use_button_wishlist','value' => '1','sort' => '23','detail' => 'product.config_manager.product_use_button_wishlist','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_use_button_compare','value' => '1','sort' => '24','detail' => 'product.config_manager.product_use_button_compare','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'product_config','key' => 'product_tax','value' => 'auto','sort' => '0','detail' => 'product.config_manager.tax','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_lastname','value' => '1','sort' => '1','detail' => 'customer.config_manager.lastname','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_lastname_required','value' => '1','sort' => '1','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_address1','value' => '1','sort' => '2','detail' => 'customer.config_manager.address1','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_address1_required','value' => '1','sort' => '2','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_address2','value' => '1','sort' => '2','detail' => 'customer.config_manager.address2','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_address2_required','value' => '1','sort' => '2','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_address3','value' => '0','sort' => '2','detail' => 'customer.config_manager.address3','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_address3_required','value' => '0','sort' => '2','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_company','value' => '0','sort' => '0','detail' => 'customer.config_manager.company','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_company_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_postcode','value' => '0','sort' => '0','detail' => 'customer.config_manager.postcode','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_postcode_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_country','value' => '1','sort' => '0','detail' => 'customer.config_manager.country','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_country_required','value' => '1','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_group','value' => '0','sort' => '0','detail' => 'customer.config_manager.group','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_group_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_birthday','value' => '0','sort' => '0','detail' => 'customer.config_manager.birthday','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_birthday_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_sex','value' => '0','sort' => '0','detail' => 'customer.config_manager.sex','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_sex_required','value' => '0','sort' => '0','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_phone','value' => '1','sort' => '0','detail' => 'customer.config_manager.phone','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_phone_required','value' => '1','sort' => '1','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute','key' => 'customer_name_kana','value' => '0','sort' => '0','detail' => 'customer.config_manager.name_kana','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config_attribute_required','key' => 'customer_name_kana_required','value' => '0','sort' => '1','detail' => '','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'customer_config','key' => 'customer_verify','value' => '0','sort' => '1','detail' => 'customer.config_manager.customer_verify','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'admin_config','key' => 'ADMIN_NAME','value' => 'GP247 System','sort' => '0','detail' => 'admin.env.ADMIN_NAME','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'admin_config','key' => 'ADMIN_TITLE','value' => 'GP247 Admin','sort' => '0','detail' => 'admin.env.ADMIN_TITLE','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'admin_config','key' => 'hidden_copyright_footer','value' => '0','sort' => '0','detail' => 'admin.env.hidden_copyright_footer','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'admin_config','key' => 'hidden_copyright_footer_admin','value' => '0','sort' => '0','detail' => 'admin.env.hidden_copyright_footer_admin','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'display_config','key' => 'product_top','value' => '12','sort' => '0','detail' => 'store.display.product_top','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'display_config','key' => 'product_list','value' => '12','sort' => '0','detail' => 'store.display.list_product','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'display_config','key' => 'product_relation','value' => '4','sort' => '0','detail' => 'store.display.relation_product','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'display_config','key' => 'product_viewed','value' => '4','sort' => '0','detail' => 'store.display.viewed_product','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'display_config','key' => 'item_list','value' => '12','sort' => '0','detail' => 'store.display.item_list','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'display_config','key' => 'item_top','value' => '12','sort' => '0','detail' => 'store.display.item_top','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'shop_allow_guest','value' => '1','sort' => '11','detail' => 'admin.order.shop_allow_guest','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'product_preorder','value' => '1','sort' => '18','detail' => 'admin.order.product_preorder','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'product_buy_out_of_stock','value' => '1','sort' => '20','detail' => 'admin.order.product_buy_out_of_stock','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'use_shipping','value' => '0','sort' => '20','detail' => 'admin.order.use_shipping','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'order_config','key' => 'use_payment','value' => '0','sort' => '20','detail' => 'admin.order.use_payment','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'captcha_config','key' => 'captcha_mode','value' => '0','sort' => '20','detail' => 'admin.captcha.captcha_mode','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'captcha_config','key' => 'captcha_page','value' => '[]','sort' => '10','detail' => 'admin.captcha.captcha_page','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'captcha_config','key' => 'captcha_method','value' => '','sort' => '0','detail' => 'admin.captcha.captcha_method','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'config_layout','key' => 'link_account','value' => '1','sort' => '0','detail' => 'admin.config_layout.link_account','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'config_layout','key' => 'link_language','value' => '1','sort' => '0','detail' => 'admin.config_layout.link_language','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'config_layout','key' => 'link_currency','value' => '1','sort' => '0','detail' => 'admin.config_layout.link_currency','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'config_layout','key' => 'link_cart','value' => '1','sort' => '0','detail' => 'admin.config_layout.link_cart','store_id' => $storeId],

            ['group' => 'gp247_cart','code' => 'sendmail_config','key' => 'welcome_customer','value' => '0','sort' => '1','detail' => 'sendmail_config.welcome_customer','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'sendmail_config','key' => 'order_success_to_admin','value' => '0','sort' => '2','detail' => 'sendmail_config.order_success_to_admin','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'sendmail_config','key' => 'order_success_to_customer','value' => '0','sort' => '3','detail' => 'sendmail_config.order_success_to_cutomer','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'sendmail_config','key' => 'contact_to_customer','value' => '0','sort' => '4','detail' => 'sendmail_config.contact_to_customer','store_id' => $storeId],
            ['group' => 'gp247_cart','code' => 'sendmail_config','key' => 'contact_to_admin','value' => '1','sort' => '5','detail' => 'sendmail_config.contact_to_admin','store_id' => $storeId],


        ];
        return $dataConfig;
    }

}
