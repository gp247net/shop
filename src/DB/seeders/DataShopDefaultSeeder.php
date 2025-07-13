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
