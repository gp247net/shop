<?php
namespace GP247\Shop\Controllers;

use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopCategory;
class ShopProductController extends RootFrontController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Process front all products
     *
     * @param [type] ...$params
     * @return void
     */
    public function allProductsProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_allProducts();
    }

    /**
     * All products
     * @return [view]
     */
    private function _allProducts()
    {
        $dataSearch = $this->processFilter(['sort', 'price', 'brand', 'category']);

        $products = (new ShopProduct)
            ->setLimit(gp247_config('product_list'))
            ->setPaginate();
        if (!empty($dataSearch['sort'])) {
            $products->setSort($dataSearch['sort']);
        }
        if (!empty($dataSearch['price'])) {
            $products->setRangePrice($dataSearch['price']);
        }
        if (!empty($dataSearch['brand'])) {
            $products->getProductToBrand($dataSearch['brand']);
        }
        if (!empty($dataSearch['category'])) {
            $products->getProductToCategory($dataSearch['category']);
        }
        $products = $products->getData();

        $subPath = 'screen.shop_product_list';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            array(
                'title'       => gp247_language_render('front.all_product'),
                'keyword'     => '',
                'description' => '',
                'products'    => $products,
                'layout_page' => 'shop_product_list',
                'filter_sort' => gp247_clean(data: request('filter_sort'), hight: true),
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('front.all_product')],
                ],
            )
        );
    }

    /**
     * Process front product detail
     *
     * @param [type] ...$params
     * @return void
     */
    public function productDetailProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            $alias = $params[1] ?? '';
            gp247_lang_switch($lang);
        } else {
            $alias = $params[0] ?? '';
        }
        return $this->_productDetail($alias);
    }

    /**
     * Get product detail
     *
     * @param   [string]  $alias      [$alias description]
     *
     * @return  [mix]
     */
    private function _productDetail($alias)
    {
        $storeId = config('app.storeId');
        $product = (new ShopProduct)->getDetail($alias, $type = 'alias', $storeId);
        if ($product && $product->status && (!gp247_config('product_stock', $storeId) || gp247_config('product_display_out_of_stock', $storeId) || $product->stock > 0)) {
            //Update last view
            $product->view += 1;
            $product->date_lastview = gp247_time_now();
            $product->save();
            //End last viewed

            //Product last view
            $productsLastView = \Illuminate\Support\Facades\Cookie::get('productsLastView');
            $arrlastView = empty($productsLastView) ? array() : json_decode($productsLastView, true);
            if (is_array($arrlastView)) {
                $arrlastView[$product->id] = gp247_time_now();
                arsort($arrlastView);
                \Cookie::queue('productsLastView', json_encode($arrlastView), (1440 * config('gp247-config.shop.cart_expire.lastview')));
            }
            //End product last view

            $categories = $product->categories->keyBy('id')->toArray();
            $arrCategoriId = array_keys($categories);

            //first category
            $categoryFirst = $product->categories->first();
            if ($categoryFirst) {
                $dataCategoryFirst = [
                    'url' => $categoryFirst->getUrl(),
                    'title' => $categoryFirst->getTitle(),
                ];
            } else {
                $dataCategoryFirst = [
                    'url' => '',
                    'title' => '',
                ];
            }

            $productRelation = (new ShopProduct)
                ->getProductToCategory($arrCategoriId)
                ->setLimit(gp247_config('product_relation', $storeId))
                ->setRandom()
                ->getData();

            $subPath = 'screen.shop_product_detail';
            $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
            gp247_check_view($view);
            return view(
                $view,
                array(
                    'title'           => $product->name,
                    'description'     => $product->description,
                    'keyword'         => $product->keyword,
                    'productId'       => $product->id,
                    'product'         => $product,
                    'productRelation' => $productRelation,
                    'og_image'        => gp247_file($product->getImage()),
                    'layout_page'     => 'shop_product_detail',
                    'breadcrumbs'     => [
                        ['url'        => gp247_route_front('product.all'), 'title' => gp247_language_render('front.all_product')],
                        $dataCategoryFirst,
                        ['url'        => '', 'title' => $product->name],
                    ],
                )
            );
        } else {
            return $this->itemNotFound();
        }
    }
    
    /**
     * Process filter
     *
     * @param array $arrFilter
     * @return array
     */
    public function processFilter(array $arrFilter) {
        $dataSearch = [];

        //Keywork
        if (in_array('keyword', $arrFilter)) {
            $keyword = request('keyword');
            $keyword = gp247_clean(data: $keyword, hight: true);
            $dataSearch['keyword'] = $keyword;
        }

        if (in_array('sort', $arrFilter)) {
            $sortBy = 'sort';
            $sortOrder = 'desc';
            $filter_sort = request('filter_sort');
            $filter_sort = gp247_clean(data: $filter_sort, hight: true);
            $filterArr = [
                'price_desc' => ['price', 'desc'],
                'price_asc' => ['price', 'asc'],
                'sort_desc' => ['sort', 'desc'],
                'sort_asc' => ['sort', 'asc'],
                'id_desc' => ['id', 'desc'],
                'id_asc' => ['id', 'asc'],
            ];
            if (array_key_exists($filter_sort, $filterArr)) {
                $sortBy = $filterArr[$filter_sort][0];
                $sortOrder = $filterArr[$filter_sort][1];
                $dataSearch['sort'] = [$sortBy, $sortOrder];
            }
        }

        if (in_array('price', $arrFilter)) {
            $filter_price = request('price');
            $filter_price = gp247_clean(data: $filter_price, hight: true);
            $dataSearch['price'] = $filter_price;
        }

        if (in_array('brand', $arrFilter)) {
            $filter_brand = request('brand');
            $filter_brand = gp247_clean(data: $filter_brand, hight: true);
            if ($filter_brand) {
                $arr_brand = explode(',', $filter_brand);
                $arr_brand_id = ShopBrand::whereIn('alias', $arr_brand)->pluck('id')->toArray();
                $dataSearch['brand'] = $arr_brand_id;
            }
        }

        if (in_array('category', $arrFilter)) {
            $filter_category = request('category');
            $filter_category = gp247_clean(data: $filter_category, hight: true);
            if ($filter_category) { 
                $arr_category = explode(',', $filter_category);
                $arr_category_id = ShopCategory::whereIn('alias', $arr_category)->pluck('id')->toArray();
                //Sub category
                $arrayMid = ShopCategory::where('parent', $arr_category_id)->pluck('id')->toArray();
                $arraySmall = ShopCategory::whereIn('parent', $arrayMid)->pluck('id')->toArray();

                $arr_category_id = array_merge($arr_category_id, $arrayMid, $arraySmall);
                $dataSearch['category'] = $arr_category_id;
            }
        }

        return $dataSearch;
    }

    /**
     * Get data filter
     *
     * @return array
     */
    public function dataFilter() {
        $dataSearch = $this->processFilter(['sort', 'price', 'brand', 'category', 'keyword']);
        $products = (new ShopProduct);
        if (!empty($dataSearch['sort'])) {
            $products->setSort($dataSearch['sort']);
        }
        if (!empty($dataSearch['price'])) {
            $products->setRangePrice($dataSearch['price']);
        }
        if (!empty($dataSearch['brand'])) {
            $products->getProductToBrand($dataSearch['brand']);
        }
        if (!empty($dataSearch['category'])) {
            $products->getProductToCategory($dataSearch['category']);
        }
        if (!empty($dataSearch['keyword'])) {
            $products->setKeyword($dataSearch['keyword']);
        }
        $products->setLimit(gp247_config('product_list'))
        ->setPaginate();
        return $products->getData();
    }
}
