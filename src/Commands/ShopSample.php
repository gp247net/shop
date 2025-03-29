<?php

namespace GP247\Shop\Commands;

use Illuminate\Console\Command;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopCategoryDescription;

class ShopSample extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gp247:shop-sample';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'GP247 shop sample';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            // Clear existing data
            $this->info('Clearing existing data...');
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_category_description')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_category_store')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_category')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_brand')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_brand_store')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_supplier')->truncate();
            
            // Create sample categories
            $this->info('Creating sample categories...');
            $categories = [
                [
                    'id' => gp247_generate_id('shop_category'),
                    'alias' => 'am-thuc',
                    'image' => 'https://picsum.photos/400/300?random=1',
                    'parent' => '0',
                    'top' => 1,
                    'sort' => 0,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Ẩm thực',
                            'keyword' => 'am thuc, mon ngon',
                            'description' => 'Danh mục các món ăn ngon'
                        ],
                        'en' => [
                            'title' => 'Food',
                            'keyword' => 'food, cuisine',
                            'description' => 'Food and cuisine category'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id('shop_category'),
                    'alias' => 'du-lich',
                    'image' => 'https://picsum.photos/400/300?random=2', 
                    'parent' => '0',
                    'top' => 1,
                    'sort' => 0,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Du lịch',
                            'keyword' => 'du lich, dia diem',
                            'description' => 'Danh mục các địa điểm du lịch'
                        ],
                        'en' => [
                            'title' => 'Travel',
                            'keyword' => 'travel, destinations',
                            'description' => 'Travel and destinations category'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id('shop_category'),
                    'alias' => 'trai-cay',
                    'image' => 'https://picsum.photos/400/300?random=3',
                    'parent' => '0', 
                    'top' => 1,
                    'sort' => 0,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Trái cây',
                            'keyword' => 'trai cay, hoa qua',
                            'description' => 'Danh mục các loại trái cây'
                        ],
                        'en' => [
                            'title' => 'Fruits',
                            'keyword' => 'fruits, fresh fruits',
                            'description' => 'Fresh fruits category'
                        ]
                    ]
                ]
            ];

            DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($categories) {
                foreach ($categories as $category) {
                    // Create category
                    $categoryData = collect($category)->except('descriptions')->toArray();
                    $cat = ShopCategory::create($categoryData);

                    // Create descriptions
                    foreach ($category['descriptions'] as $lang => $description) {
                        ShopCategoryDescription::create([
                            'category_id' => $cat->id,
                            'lang' => $lang,
                            'title' => $description['title'],
                            'keyword' => $description['keyword'],
                            'description' => $description['description']
                        ]);
                    }

                    // Link to store
                    DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_category_store')->insert([
                        'category_id' => $cat->id,
                        'store_id' => GP247_STORE_ID_ROOT
                    ]);
                }
            });

            // Create sample brands
            $this->info('Creating sample brands...');
            $brands = [
                [
                    'id' => gp247_generate_id('shop_brand'),
                    'name' => 'Nike',
                    'alias' => 'nike',
                    'image' => 'https://picsum.photos/200/100?random=1',
                    'url' => 'https://nike.com',
                    'status' => 1,
                    'sort' => 0
                ],
                [
                    'id' => gp247_generate_id('shop_brand'),
                    'name' => 'Adidas',
                    'alias' => 'adidas',
                    'image' => 'https://picsum.photos/200/100?random=2',
                    'url' => 'https://adidas.com',
                    'status' => 1,
                    'sort' => 0
                ],
                [
                    'id' => gp247_generate_id('shop_brand'),
                    'name' => 'Puma',
                    'alias' => 'puma',
                    'image' => 'https://picsum.photos/200/100?random=3',
                    'url' => 'https://puma.com',
                    'status' => 1,
                    'sort' => 0
                ]
            ];

            DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($brands) {
                foreach ($brands as $brand) {
                    // Create brand
                    DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_brand')->insert($brand);

                    // Link to store
                    DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_brand_store')->insert([
                        'brand_id' => $brand['id'],
                        'store_id' => GP247_STORE_ID_ROOT
                    ]);
                }
            });

            // Create sample suppliers
            $this->info('Creating sample suppliers...');
            $suppliers = [
                [
                    'id' => gp247_generate_id('shop_supplier'),
                    'name' => 'ABC Corp',
                    'alias' => 'abc-corp',
                    'email' => 'contact@abc.com',
                    'phone' => '0123456789',
                    'image' => 'https://picsum.photos/200/100?random=4',
                    'address' => '123 ABC Street',
                    'url' => 'https://abc.com',
                    'status' => 1,
                    'store_id' => GP247_STORE_ID_ROOT,
                    'sort' => 0
                ],
                [
                    'id' => gp247_generate_id('shop_supplier'),
                    'name' => 'XYZ Inc',
                    'alias' => 'xyz-inc',
                    'email' => 'contact@xyz.com',
                    'phone' => '0987654321',
                    'image' => 'https://picsum.photos/200/100?random=5',
                    'address' => '456 XYZ Street',
                    'url' => 'https://xyz.com',
                    'status' => 1,
                    'store_id' => GP247_STORE_ID_ROOT,
                    'sort' => 0
                ],
                [
                    'id' => gp247_generate_id('shop_supplier'),
                    'name' => 'DEF Ltd',
                    'alias' => 'def-ltd',
                    'email' => 'contact@def.com',
                    'phone' => '0369852147',
                    'image' => 'https://picsum.photos/200/100?random=6',
                    'address' => '789 DEF Street',
                    'url' => 'https://def.com',
                    'status' => 1,
                    'store_id' => GP247_STORE_ID_ROOT,
                    'sort' => 0
                ]
            ];

            DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($suppliers) {
                foreach ($suppliers as $supplier) {
                    DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_supplier')->insert($supplier);
                }
            });

            $this->info('Created sample data successfully!');

        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
