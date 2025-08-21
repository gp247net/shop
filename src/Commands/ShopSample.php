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
use GP247\Shop\Models\ShopProductGroup;
use GP247\Shop\Models\ShopProductBuild;
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
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_attribute_group')->truncate();
            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_product_attribute')->truncate();

            
            // Create sample categories
            $this->info('Creating sample categories...');
            $cateRoot1 = gp247_generate_id();
            $cateRoot2 = gp247_generate_id();
            $cateRoot3 = gp247_generate_id();
            $cateRoot4 = gp247_generate_id();
            $categories = [
                [
                    'id' => $cateRoot1,
                    'alias' => 'am-thuc',
                    'image' => 'https://picsum.photos/400/300?random=1',
                    'parent' => '',
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
                // Create subcategories for "Ẩm thực" (Food)
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'pho-nam-dinh',
                    'image' => 'https://picsum.photos/400/300?random=11',
                    'parent' => $cateRoot1,
                    'top' => 0,
                    'sort' => 1,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Phở Nam Định',
                            'keyword' => 'pho nam dinh, am thuc',
                            'description' => 'Danh mục các sản phẩm Phở Nam Định'
                        ],
                        'en' => [
                            'title' => 'Pho Nam Dinh',
                            'keyword' => 'pho nam dinh, food',
                            'description' => 'Category of Pho Nam Dinh products'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'nem-chua-thanh-hoa',
                    'image' => 'https://picsum.photos/400/300?random=12',
                    'parent' => $cateRoot1,
                    'top' => 0,
                    'sort' => 2,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Nem chua Thanh Hóa',
                            'keyword' => 'nem chua thanh hoa, am thuc',
                            'description' => 'Danh mục các sản phẩm Nem chua Thanh Hóa'
                        ],
                        'en' => [
                            'title' => 'Nem Chua Thanh Hoa',
                            'keyword' => 'nem chua thanh hoa, food',
                            'description' => 'Category of Nem Chua Thanh Hoa products'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'bun-cha-ha-noi',
                    'image' => 'https://picsum.photos/400/300?random=13',
                    'parent' => $cateRoot1,
                    'top' => 0,
                    'sort' => 3,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Bún Chả Hà Nội',
                            'keyword' => 'bun cha ha noi, am thuc',
                            'description' => 'Danh mục các sản phẩm Bún Chả Hà Nội'
                        ],
                        'en' => [
                            'title' => 'Bun Cha Ha Noi',
                            'keyword' => 'bun cha ha noi, food',
                            'description' => 'Category of Bun Cha Ha Noi products'
                        ]
                    ]
                ],
                [
                    'id' => $cateRoot2,
                    'alias' => 'du-lich',
                    'image' => 'https://picsum.photos/400/300?random=2', 
                    'parent' => '',
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
                    'alias' => 'vinh-ha-long',
                    'image' => 'https://picsum.photos/400/300?random=21',
                    'parent' => $cateRoot2,
                    'top' => 0,
                    'sort' => 1,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Vịnh Hạ Long',
                            'keyword' => 'vinh ha long, du lich',
                            'description' => 'Danh mục các sản phẩm, dịch vụ tại Vịnh Hạ Long'
                        ],
                        'en' => [
                            'title' => 'Ha Long Bay',
                            'keyword' => 'ha long bay, travel',
                            'description' => 'Category of products and services at Ha Long Bay'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'dong-phong-nha',
                    'image' => 'https://picsum.photos/400/300?random=22',
                    'parent' => $cateRoot2,
                    'top' => 0,
                    'sort' => 2,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Động Phong Nha',
                            'keyword' => 'dong phong nha, du lich',
                            'description' => 'Danh mục các sản phẩm, dịch vụ tại Động Phong Nha'
                        ],
                        'en' => [
                            'title' => 'Phong Nha Cave',
                            'keyword' => 'phong nha cave, travel',
                            'description' => 'Category of products and services at Phong Nha Cave'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'trang-an',
                    'image' => 'https://picsum.photos/400/300?random=23',
                    'parent' => $cateRoot2,
                    'top' => 0,
                    'sort' => 3,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Tràng An',
                            'keyword' => 'trang an, du lich',
                            'description' => 'Danh mục các sản phẩm, dịch vụ tại Tràng An'
                        ],
                        'en' => [
                            'title' => 'Trang An',
                            'keyword' => 'trang an, travel',
                            'description' => 'Category of products and services at Trang An'
                        ]
                    ]
                ],
                [
                    'id' => $cateRoot3,
                    'alias' => 'van-hoa',
                    'image' => 'https://picsum.photos/400/300?random=3', 
                    'parent' => '',
                    'top' => 1,
                    'sort' => 0,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Văn hóa',
                            'keyword' => 'van hoa, van nghe',
                            'description' => 'Danh mục các sản phẩm văn hóa'
                        ],
                        'en' => [
                            'title' => 'Culture',
                            'keyword' => 'culture, art',
                            'description' => 'Culture and art category'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'gom-bat-trang',
                    'image' => 'https://picsum.photos/400/300?random=31',
                    'parent' => $cateRoot3,
                    'top' => 0,
                    'sort' => 1,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Gốm Bát Tràng',
                            'keyword' => 'gom bat trang, gom su',
                            'description' => 'Danh mục các sản phẩm gốm Bát Tràng'
                        ],
                        'en' => [
                            'title' => 'Bat Trang Pottery',
                            'keyword' => 'bat trang pottery, ceramics',
                            'description' => 'Category of Bat Trang pottery products'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'tranh-dong-ho',
                    'image' => 'https://picsum.photos/400/300?random=32',
                    'parent' => $cateRoot3,
                    'top' => 0,
                    'sort' => 2,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Tranh Đông Hồ',
                            'keyword' => 'tranh dong ho, tranh dan gian',
                            'description' => 'Danh mục các sản phẩm tranh Đông Hồ'
                        ],
                        'en' => [
                            'title' => 'Dong Ho Paintings',
                            'keyword' => 'dong ho paintings, folk paintings',
                            'description' => 'Category of Dong Ho folk paintings'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'chieu-coi-nga-son',
                    'image' => 'https://picsum.photos/400/300?random=33',
                    'parent' => $cateRoot3,
                    'top' => 0,
                    'sort' => 3,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Chiếu cói Nga Sơn',
                            'keyword' => 'chieu coi nga son, chieu coi',
                            'description' => 'Danh mục các sản phẩm chiếu cói Nga Sơn'
                        ],
                        'en' => [
                            'title' => 'Nga Son Rush Mats',
                            'keyword' => 'nga son rush mats, rush mats',
                            'description' => 'Category of Nga Son rush mat products'
                        ]
                    ]
                ],
                [
                    'id' => $cateRoot4,
                    'alias' => 'trai-cay',
                    'image' => 'https://picsum.photos/400/300?random=4',
                    'parent' => '', 
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
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'trai-cay-nam-bo',
                    'image' => 'https://picsum.photos/400/300?random=41',
                    'parent' => $cateRoot4,
                    'top' => 0,
                    'sort' => 1,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Trái Cây Nam Bộ',
                            'keyword' => 'trai cay nam bo, hoa qua nam bo',
                            'description' => 'Danh mục các loại trái cây đặc sản Nam Bộ'
                        ],
                        'en' => [
                            'title' => 'Southern Fruits',
                            'keyword' => 'southern fruits, vietnamese fruits',
                            'description' => 'Category of Southern Vietnam specialty fruits'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'vai-thieu',
                    'image' => 'https://picsum.photos/400/300?random=42',
                    'parent' => $cateRoot4,
                    'top' => 0,
                    'sort' => 2,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Vải Thiều',
                            'keyword' => 'vai thieu, vai thieu bac giang',
                            'description' => 'Danh mục các sản phẩm vải thiều'
                        ],
                        'en' => [
                            'title' => 'Lychee',
                            'keyword' => 'lychee, bac giang lychee',
                            'description' => 'Category of lychee products'
                        ]
                    ]
                ],
                [
                    'id' => gp247_generate_id(),
                    'alias' => 'nhan-long',
                    'image' => 'https://picsum.photos/400/300?random=43',
                    'parent' => $cateRoot4,
                    'top' => 0,
                    'sort' => 3,
                    'status' => 1,
                    'descriptions' => [
                        'vi' => [
                            'title' => 'Nhãn Lồng',
                            'keyword' => 'nhan long, nhan long hung yen',
                            'description' => 'Danh mục các sản phẩm nhãn lồng Hưng Yên'
                        ],
                        'en' => [
                            'title' => 'Longan',
                            'keyword' => 'longan, hung yen longan',
                            'description' => 'Category of Hung Yen longan products'
                        ]
                    ]
                ]
            ];

            $categoryIds = [];

            foreach ($categories as $category) {
                // Create category
                $categoryData = collect($category)->except('descriptions')->toArray();
                $cat = ShopCategory::create($categoryData);
                if ($category['parent'] != '0') {
                    $categoryIds[] = $cat->id;
                }
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

            foreach ($brands as $brand) {
                // Create brand
                DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_brand')->insert($brand);
            }

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

            foreach ($suppliers as $supplier) {
                DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_supplier')->insert($supplier);
            }

            DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX.'shop_attribute_group')->insert([
                'name' => 'Color',
                'status' => 1,
                'sort' => 0,
                'type' => 'radio',
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // Create sample products for each category
            $this->info('Creating sample products...');
            
            $arrProductSingleIds = [];
            foreach ($categoryIds as $categoryKey => $categoryId) {
                // Create 3 products per category
                for ($i = 1; $i <= 3; $i++) {
                    $hasPromotion = ($i <= 2) ? true : false; // First 2 products have promotion
                    $arrProductSingleIds[] = $productId = gp247_generate_id();
                    $productNumber = $categoryKey * 3 + $i;
                    
                    // Randomly assign brand_id as null or one of the $brands ids
                    $brandIds = array_column($brands, 'id');
                    $randomBrand = rand(0, count($brandIds)); // Có thể là null (nếu bằng count)
                    if ($randomBrand === count($brandIds)) {
                        $brandId = null;
                    } else {
                        $brandId = $brandIds[$randomBrand];
                    }
                    // Basic product data
                    $productData = [
                        'id' => $productId,
                        'sku' => 'SAMPLE-' . $categoryKey . '-' . $i,
                        'alias' => 'sample-product-' . $productNumber,
                        'image' => 'https://picsum.photos/500/500?random=' . $productNumber,
                        'brand_id' => $brandId,
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
                        'created_at' => Carbon::now()->format('Y-m-d 00:00:00'),
                        'updated_at' => Carbon::now()->format('Y-m-d 00:00:00'),
                    ];

                    // Product descriptions
                    $productDescriptions = [
                        'vi' => [
                            'product_id' => $productId,
                            'lang' => 'vi',
                            'name' => 'Sản phẩm mẫu ' . $productNumber . ' - Tiếng Việt',
                            'keyword' => 'sample, product',
                            'description' => 'Mô tả ngắn cho sản phẩm mẫu ' . $productNumber,
                            'content' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>'
                        ],
                        'en' => [
                            'product_id' => $productId,
                            'lang' => 'en',
                            'name' => 'Sample product ' . $productNumber . ' - English',
                            'keyword' => 'sample, product',
                            'description' => 'Short description for sample product ' . $productNumber,
                            'content' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>'
                        ]
                    ];

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
                }
            }

            // Create product bundles and groups within the same transaction
            $this->createProductBundle($arrProductSingleIds, $categoryIds);
            $this->createProductGroup($arrProductSingleIds, $categoryIds);
            
            $this->info('Created sample data successfully!');
    }


    /**
     * Create product bundle
     */
    private function createProductBundle($productSingleIds, $categoryIds)
    {
        $this->info('Creating product bundles...');
        for ($i = 1; $i <= 3; $i++) {
            $productId = gp247_generate_id();
            // Basic product data
            $productData = [
                'id' => $productId,
                'sku' => 'SAMPLE-BUNDLE-' . $i,
                'alias' => 'sample-bundle-' . $i,
                'image' => 'https://picsum.photos/500/500?random=2' . $i,
                'brand_id' => null,
                'supplier_id' => null,
                'price' => rand(500, 1000), // Random price between 100 and 500
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
                'kind' => GP247_PRODUCT_BUILD,
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
                    'name' => 'Sản phẩm bộ ' . $i . ' - Tiếng Việt',
                    'keyword' => 'sample, product',
                    'description' => 'Mô tả ngắn cho sản phẩm mẫu ' . $i,
                    'content' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>'
                ],
                'en' => [
                    'product_id' => $productId,
                    'lang' => 'en',
                    'name' => 'Product bundle ' . $i . ' - English',
                    'keyword' => 'sample, product',
                    'description' => 'Short description for sample product ' . $i,
                    'content' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>'
                ]
            ];
            // Create product
            $product = ShopProduct::create($productData);

            // Create descriptions
            foreach ($productDescriptions as $description) {
                ShopProductDescription::create($description);
            }

            // Link to category
            ShopProductCategory::create([
                'product_id' => $productId,
                'category_id' => $categoryIds[array_rand($categoryIds)]
            ]);

            // Link to store
            ShopProductStore::create([
                'product_id' => $productId,
                'store_id' => GP247_STORE_ID_ROOT
            ]);

            // Random 2 product from $productSingleIds
            $randomProductIds = [];
            if (is_array($productSingleIds) && count($productSingleIds) >= 2) {
                // Shuffle array and get first 2 elements
                $shuffled = $productSingleIds;
                shuffle($shuffled);
                $randomProductIds = array_slice($shuffled, 0, 2);
            }

            if (count($randomProductIds) > 0) {
                $arrDataBuild = [];
                foreach ($randomProductIds as $key => $pID) {
                    if ($pID) {
                        $arrDataBuild[$pID] = new ShopProductBuild(['product_id' => $pID, 'quantity' => 1]);
                    }
                }
                $product->builds()->saveMany($arrDataBuild);
            }
        }
    }


    /**
     * Create product group
     */
    private function createProductGroup($productSingleIds, $categoryIds)
    {
        $this->info('Creating product groups...');
        for ($i = 1; $i <= 3; $i++) {
            $productId = gp247_generate_id();
            // Basic product data
            $productData = [
                'id' => $productId,
                'sku' => 'SAMPLE-GROUP-' . $i,
                'alias' => 'sample-group-' . $i,
                'image' => 'https://picsum.photos/500/500?random=3' . $i,
                'brand_id' => null,
                'supplier_id' => null,
                'price' => 0,
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
                'kind' => GP247_PRODUCT_GROUP,
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
                    'name' => 'Sản phẩm nhóm ' . $i . ' - Tiếng Việt',
                    'keyword' => 'sample, product',
                    'description' => 'Mô tả ngắn cho sản phẩm mẫu ' . $i,
                    'content' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>'
                ],
                'en' => [
                    'product_id' => $productId,
                    'lang' => 'en',
                    'name' => 'Product group ' . $i . ' - English',
                    'keyword' => 'sample, product',
                    'description' => 'Short description for sample product ' . $i,
                    'content' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. <br>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>'
                ]
            ];
            // Create product
            $product = ShopProduct::create($productData);

            // Create descriptions
            foreach ($productDescriptions as $description) {
                ShopProductDescription::create($description);
            }

            // Link to category
            ShopProductCategory::create([
                'product_id' => $productId,
                'category_id' => $categoryIds[array_rand($categoryIds)]
            ]);

            // Link to store
            ShopProductStore::create([
                'product_id' => $productId,
                'store_id' => GP247_STORE_ID_ROOT
            ]);

            // Random 2 product from $productSingleIds
            $randomProductIds = [];
            if (is_array($productSingleIds) && count($productSingleIds) >= 2) {
                // Shuffle array and get first 2 elements
                $shuffled = $productSingleIds;
                shuffle($shuffled);
                $randomProductIds = array_slice($shuffled, 0, 2);
            }

            if (count($randomProductIds) > 0) {
                $arrDataGroup = [];
                foreach ($randomProductIds as $key => $pID) {
                    if ($pID) {
                        $arrDataGroup[$pID] = new ShopProductGroup(['product_id' => $pID]);
                    }
                }
                $product->groups()->saveMany($arrDataGroup);
            }
        }
    }

}
