<?php

namespace GP247\Shop\Commands;

use Illuminate\Console\Command;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopCategoryDescription;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductDescription;
use GP247\Shop\Models\ShopProductStore;
use GP247\Shop\Models\ShopProductPromotion;
use GP247\Shop\Models\ShopProductCategory;
use Carbon\Carbon;

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
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_supplier')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_product')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_product_description')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_product_store')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_product_category')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_product_promotion')->truncate();
            
            // Create sample categories
            $this->info('Creating sample categories...');
            $categories = [
                [
                    'id' => gp247_generate_id(),
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
                    'id' => gp247_generate_id(),
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
                    'id' => gp247_generate_id(),
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

            $categoryIds = [];

            DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($categories, &$categoryIds) {
                foreach ($categories as $category) {
                    // Create category
                    $categoryData = collect($category)->except('descriptions')->toArray();
                    $cat = ShopCategory::create($categoryData);
                    $categoryIds[] = $cat->id;

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
                    'id' => gp247_generate_id(),
                    'name' => 'Nike',
                    'alias' => 'nike',
                    'image' => 'https://picsum.photos/200/100?random=1',
                    'url' => 'https://nike.com',
                    'status' => 1,
                    'sort' => 0
                ],
                [
                    'id' => gp247_generate_id(),
                    'name' => 'Adidas',
                    'alias' => 'adidas',
                    'image' => 'https://picsum.photos/200/100?random=2',
                    'url' => 'https://adidas.com',
                    'status' => 1,
                    'sort' => 0
                ],
                [
                    'id' => gp247_generate_id(),
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
                }
            });

            // Create sample suppliers
            $this->info('Creating sample suppliers...');
            $suppliers = [
                [
                    'id' => gp247_generate_id(),
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
                    'id' => gp247_generate_id(),
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
                    'id' => gp247_generate_id(),
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

            // Create sample products for each category
            $this->info('Creating sample products...');

            foreach ($categoryIds as $categoryKey => $categoryId) {
                // Create 3 products per category
                for ($i = 1; $i <= 3; $i++) {
                    $hasPromotion = ($i <= 2) ? true : false; // First 2 products have promotion
                    $productId = gp247_generate_id();
                    $productNumber = $categoryKey * 3 + $i;
                    
                    // Basic product data
                    $productData = [
                        'id' => $productId,
                        'sku' => 'SAMPLE-' . $categoryKey . '-' . $i,
                        'alias' => 'sample-product-' . $productNumber,
                        'image' => 'https://picsum.photos/500/500?random=' . $productNumber,
                        'brand_id' => null,
                        'supplier_id' => null,
                        'price' => rand(100, 500), // Random price between 100 and 500
                        'cost' => 0,
                        'stock' => 100,
                        'sold' => 0,
                        'minimum' => 1,
                        'weight_class' => 'kg',
                        'weight' => 1,
                        'length_class' => 'cm',
                        'length' => 10,
                        'width' => 10,
                        'height' => 10,
                        'kind' => 0, // Single product
                        'tag' => 0, // Physical product
                        'tax_id' => 0,
                        'status' => 1,
                        'sort' => 0,
                        'view' => 0,
                        'date_available' => Carbon::now()->format('Y-m-d H:i:s'),
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    ];

                    // Product descriptions
                    $productDescriptions = [
                        'vi' => [
                            'product_id' => $productId,
                            'lang' => 'vi',
                            'name' => 'Sản phẩm mẫu ' . $productNumber . ' - Tiếng Việt',
                            'keyword' => 'sample, product',
                            'description' => 'Mô tả ngắn cho sản phẩm mẫu ' . $productNumber,
                            'content' => '<p>Nội dung chi tiết cho sản phẩm mẫu ' . $productNumber . '</p>'
                        ],
                        'en' => [
                            'product_id' => $productId,
                            'lang' => 'en',
                            'name' => 'Sample product ' . $productNumber . ' - English',
                            'keyword' => 'sample, product',
                            'description' => 'Short description for sample product ' . $productNumber,
                            'content' => '<p>Detailed content for sample product ' . $productNumber . '</p>'
                        ]
                    ];

                    DB::connection(GP247_DB_CONNECTION)->transaction(function () use (
                        $productData, 
                        $productDescriptions, 
                        $categoryId, 
                        $hasPromotion, 
                        $productId
                    ) {
                        // Create product
                        ShopProduct::create($productData);

                        // Create descriptions
                        foreach ($productDescriptions as $description) {
                            ShopProductDescription::create($description);
                        }

                        // Link to category
                        ShopProductCategory::create([
                            'product_id' => $productId,
                            'category_id' => $categoryId
                        ]);

                        // Link to store
                        ShopProductStore::create([
                            'product_id' => $productId,
                            'store_id' => GP247_STORE_ID_ROOT
                        ]);

                        // Create promotion if needed
                        if ($hasPromotion) {
                            $promotionPrice = floor($productData['price'] * 0.8); // 20% discount, rounded down to ensure integer
                            ShopProductPromotion::create([
                                'product_id' => $productId,
                                'price_promotion' => $promotionPrice,
                                'date_start' => Carbon::now()->format('Y-m-d H:i:s'),
                                'date_end' => Carbon::now()->addMonths(2)->format('Y-m-d H:i:s')
                            ]);
                        }
                    });
                }
            }

            $this->info('Created sample data successfully!');

        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
