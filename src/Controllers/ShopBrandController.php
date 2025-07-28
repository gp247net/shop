<?php
namespace GP247\Shop\Controllers;

use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Controllers\ShopProductController;
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
        $itemsList = (new ShopBrand)
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
        $brand = (new ShopBrand)->getDetail($alias, $type = 'alias');
        if ($brand) {
            $dataSearch = (new ShopProductController)->processFilter(['sort', 'price']);
            
            $products = (new ShopProduct);
            if (!empty($dataSearch['sort'])) {
                $products->setSort($dataSearch['sort']);
            }
            if (!empty($dataSearch['price'])) {
                $products->setRangePrice($dataSearch['price']);
            }
            $products = $products->getProductToBrand($brand->id)
            ->setPaginate()
            ->setLimit(gp247_config('product_list'))
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
                    'filter_sort' => gp247_clean(data: request('filter_sort'), hight: true),
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
