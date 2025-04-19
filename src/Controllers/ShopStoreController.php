<?php
namespace GP247\Shop\Controllers;

use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopCategory;

class ShopStoreController extends RootFrontController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process front shop page
     *
     * @param [type] ...$params
     * @return void
     */
    public function shopProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_shop();
    }

    /**
     * Shop page
     * @return [view]
     */
    private function _shop()
    {
        $filter_sort = request('filter_sort');
        
        $products = $this->processProductList();

        $subPath = 'screen.shop_home';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            array(
                'title'       => gp247_language_render('front.shop'),
                'keyword'     => gp247_store_info('keyword'),
                'description' => gp247_store_info('description'),
                'products'    => $products,
                'layout_page' => 'shop_home',
                'filter_sort' => $filter_sort,
                'breadcrumbs'        => [
                    ['url'           => '', 'title' => gp247_language_render('front.shop')],
                ],
            )
        );
    }

    /**
     * Process product list
     *
     * @return  [type]  [return description]
     */
    protected function processProductList() {
        $sortBy = 'sort';
        $sortOrder = 'asc';
        $arrBrandId = [];
        $categoryId = '';
        $filter_sort = request('filter_sort');
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
        }
        $keyword = request('keyword');
        $cid = request('cid');
        $bid = request('bid');
        $price = request('price');
        $brand = request('brand');
        $category = request('category');
        if ($bid) {
            $arrBrandId = explode(',', $bid);
        } else {
            if ($brand) {
                $arrAliasBrand = explode(',', $brand);
                $arrBrandId = ShopBrand::whereIn('alias', $arrAliasBrand)->pluck('id')->toArray();
            }
        }

        if ($cid) {
            $categoryId = trim($cid);
        } else {
            if ($category) {
                $categoryId = ShopCategory::where('alias', $category)->first();
                if ($categoryId) {
                    $categoryId = $categoryId->id;
                }
            }
        }

        $products = (new ShopProduct);

        if ($keyword) {
            $products = $products->setKeyword($keyword);
        }
        //Filter category
        if ($categoryId) {
            $arrCate = (new ShopCategory)->getListSub($categoryId);
            $products = $products->getProductToCategory($arrCate);
        }
        //filter brand
        if ($arrBrandId) {
            $products = $products->getProductToBrand($arrBrandId);
        }
        //Filter price
        if ($price) {
            $products = $products->setRangePrice($price);
        }

        $products = $products
            ->setLimit(gp247_config('product_list'))
            ->setPaginate()
            ->setSort([$sortBy, $sortOrder])
            ->getData();

        return $products;
    }
}
