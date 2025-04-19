<?php
namespace GP247\Shop\Controllers;

use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopProduct;

class ShopBrandController extends RootFrontController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Process front get all brand
     *
     * @param [type] ...$params
     * @return void
     */
    public function allBrandsProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_allBrands();
    }

    /**
     * Get all brand
     * @return [view]
     */
    private function _allBrands()
    {
        $sortBy = 'sort';
        $sortOrder = 'asc';
        $filter_sort = request('filter_sort');
        $filterArr = [
            'name_desc' => ['name', 'desc'],
            'name_asc'  => ['name', 'asc'],
            'sort_desc' => ['sort', 'desc'],
            'sort_asc'  => ['sort', 'asc'],
            'id_desc'   => ['id', 'desc'],
            'id_asc'    => ['id', 'asc'],
        ];
        if (array_key_exists($filter_sort, $filterArr)) {
            $sortBy = $filterArr[$filter_sort][0];
            $sortOrder = $filterArr[$filter_sort][1];
        }

        $itemsList = (new ShopBrand)
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
                'title'       => gp247_language_render('front.brands'),
                'itemsList'   => $itemsList,
                'keyword'     => '',
                'description' => '',
                'layout_page' => 'shop_item_list',
                'filter_sort' => $filter_sort,
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('front.brands')],
                ],
            )
        );
    }

    /**
     * Process front get brand detail
     *
     * @param [type] ...$params
     * @return void
     */
    public function brandDetailProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            $alias = $params[1] ?? '';
            gp247_lang_switch($lang);
        } else {
            $alias = $params[0] ?? '';
        }
        return $this->_brandDetail($alias);
    }

    /**
     * brand detail
     * @param  [string] $alias
     * @return [view]
     */
    private function _brandDetail($alias)
    {
        $sortBy = 'sort';
        $sortOrder = 'asc';
        $filter_sort = request('filter_sort');
        $filterArr = [
            'price_desc' => ['price', 'desc'],
            'price_asc'  => ['price', 'asc'],
            'sort_desc'  => ['sort', 'desc'],
            'sort_asc'   => ['sort', 'asc'],
            'id_desc'    => ['id', 'desc'],
            'id_asc'     => ['id', 'asc'],
        ];
        if (array_key_exists($filter_sort, $filterArr)) {
            $sortBy = $filterArr[$filter_sort][0];
            $sortOrder = $filterArr[$filter_sort][1];
        }

        $brand = (new ShopBrand)->getDetail($alias, $type = 'alias');
        if ($brand) {
            $products = (new ShopProduct)
            ->getProductToBrand($brand->id)
            ->setPaginate()
            ->setLimit(gp247_config('product_list'))
            ->setSort([$sortBy, $sortOrder])
            ->getData();

            $subPath = 'screen.shop_product_list';
            $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
            gp247_check_view($view);
            return view(
                $view,
                array(
                    'title'       => $brand->name,
                    'description' => $brand->description,
                    'keyword'     => $brand->keyword,
                    'brandId'     => $brand->id,
                    'products'    => $products,
                    'brand'       => $brand,
                    'og_image'    => gp247_file($brand->getImage()),
                    'filter_sort' => $filter_sort,
                    'layout_page' => 'shop_product_list',
                    'breadcrumbs' => [
                        ['url'    => gp247_route_front('brand.all'), 'title' => gp247_language_render('front.brands')],
                        ['url'    => '', 'title' => $brand->name],
                    ],
                )
            );
        } else {
            return $this->itemNotFound();
        }
    }
}
