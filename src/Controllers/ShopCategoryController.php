<?php
namespace GP247\Shop\Controllers;

use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopProduct;

class ShopCategoryController extends RootFrontController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process front get category all
     *
     * @param [type] ...$params
     * @return void
     */
    public function allCategoriesProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_allCategories();
    }

    /**
     * display list category root (parent = 0)
     * @return [view]
     */
    private function _allCategories()
    {
        $sortBy = 'sort';
        $sortOrder = 'asc';
        $filter_sort = request('filter_sort');
        $filterArr = [
            'sort_desc' => ['sort', 'desc'],
            'sort_asc' => ['sort', 'asc'],
            'id_desc' => ['id', 'desc'],
            'id_asc' => ['id', 'asc'],
        ];
        if (array_key_exists($filter_sort, $filterArr)) {
            $sortBy = $filterArr[$filter_sort][0];
            $sortOrder = $filterArr[$filter_sort][1];
        }

        $itemsList = (new ShopCategory)
            ->getCategoryRoot()
            ->setSort([$sortBy, $sortOrder])
            ->setPaginate()
            ->setLimit(gp247_config('item_list'))
            ->getData();

        $subPath = 'screen.shop_item_list';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            array(
                'title'       => gp247_language_render('front.categories'),
                'itemsList'   => $itemsList,
                'keyword'     => '',
                'description' => '',
                'layout_page' => 'shop_item_list',
                'filter_sort' => $filter_sort,
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('front.categories')],
                ],
            )
        );
    }

    /**
     * Process front get category detail
     *
     * @param [type] ...$params
     * @return void
     */
    public function categoryDetailProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            $alias = $params[1] ?? '';
            gp247_lang_switch($lang);
        } else {
            $alias = $params[0] ?? '';
        }
        return $this->_categoryDetail($alias);
    }


    /**
     * Category detail: list category child + product list
     * @param  [string] $alias
     * @return [view]
     */
    private function _categoryDetail($alias)
    {
        $sortBy = 'sort';
        $sortOrder = 'asc';
        $arrBrandId = [];
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
        $bid = request('bid');
        $price = request('price');
        $brand = request('brand');

        if ($bid) {
            $arrBrandId = explode(',', $bid);
        } else {
            if ($brand) {
                $arrAliasBrand = explode(',', $brand);
                $arrBrandId = ShopBrand::whereIn('alias', $arrAliasBrand)->pluck('id')->toArray();
            }
        }

        $category = (new ShopCategory)->getDetail($alias, $type = 'alias');

        if ($category) {
            
            $products = (new ShopProduct);

            if ($keyword) {
                $products = $products->setKeyword($keyword);
            }
            //Filter category
            $arrCate = (new ShopCategory)->getListSub($category->id);
            $products = $products->getProductToCategory($arrCate);

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

            $subCategory = (new ShopCategory)
                ->setParent($category->id)
                ->setLimit(gp247_config('item_list'))
                ->setPaginate()
                ->getData();

            $subPath = 'screen.shop_product_list';
            $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
            gp247_check_view($view);

            return view(
                $view,
                array(
                    'title'       => $category->title,
                    'categoryId'  => $category->id,
                    'description' => $category->description,
                    'keyword'     => $category->keyword,
                    'products'    => $products,
                    'category'    => $category,
                    'subCategory' => $subCategory,
                    'layout_page' => 'shop_product_list',
                    'og_image'    => gp247_file($category->getImage()),
                    'filter_sort' => $filter_sort,
                    'breadcrumbs' => [
                        ['url'    => gp247_route_front('category.all'), 'title' => gp247_language_render('front.categories')],
                        ['url'    => '', 'title' => $category->title],
                    ],
                )
            );
        } else {
            return $this->itemNotFound();
        }
    }
}
