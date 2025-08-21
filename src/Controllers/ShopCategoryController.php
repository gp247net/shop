<?php
namespace GP247\Shop\Controllers;

use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Controllers\ShopProductController;

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
        $itemsList = (new ShopCategory)
            ->getCategoryRoot()
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

        $category = (new ShopCategory)->getDetail($alias, $type = 'alias');

        if ($category) {
            $dataSearch = (new ShopProductController)->processFilter(['sort', 'price', 'brand']);
            $products = (new ShopProduct);

            //Filter category
            $arrCate = (new ShopCategory)->getListSub($category->id);
            $products = $products->getProductToCategory($arrCate);

            if (!empty($dataSearch['sort'])) {
                $products->setSort($dataSearch['sort']);
            }
            if (!empty($dataSearch['price'])) {
                $products->setRangePrice($dataSearch['price']);
            }
            if (!empty($dataSearch['brand'])) {
                $products->getProductToBrand($dataSearch['brand']);
            }

            $products = $products
            ->setLimit(gp247_config('product_list'))
            ->setPaginate()
            ->getData();

            $subCategory = (new ShopCategory)
                ->setParent($category->id)
                ->setLimit(gp247_config('item_list'))
                ->setPaginate()
                ->getData();

            // Get parent category
            $parentCategory = (new ShopCategory)
                ->getDetail($category->parent, 'id');
            $breadcrumbs = [];
            $breadcrumbs[] = ['url'    => gp247_route_front('category.all'), 'title' => gp247_language_render('front.categories')];
            if ($parentCategory) {
                $breadcrumbs[] = [
                    'url'    => $parentCategory->getUrl(),
                    'title' => $parentCategory->title,
                ];
            }
            $breadcrumbs[] = ['url'    => '', 'title' => $category->title];
            /** End get parent category */

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
                    'filter_sort' => gp247_clean(data: request('filter_sort'), hight: true),
                    'breadcrumbs' => $breadcrumbs,
                )
            );
        } else {
            return $this->itemNotFound();
        }
    }
}
