<?php
namespace GP247\Shop\Controllers;

use GP247\Shop\Support\ShopLayoutPage;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductTag;
use GP247\Shop\Controllers\ShopProductController;

/**
 * Storefront listing of products carrying a keyword tag — the /tag/<alias> page
 * (US-SFRONT-product-tags). Mirrors ShopBrandController: resolve the tag by alias,
 * then render the shared product-list view filtered via ShopProduct::getProductToTag.
 * The feature is gated by the `product_tags` config; when off, the page 404s so a
 * disabled feature exposes no route surface.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-SFRONT-product-tags
 * @aidlc-adr shop-admin_product-tag-storage
 */
class ShopProductTagController extends RootFrontController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Front entry point for /tag/<alias>, resolving the optional SEO language segment.
     *
     * @param mixed ...$params Route segments ([lang, alias] when SEO_LANG, else [alias]).
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\Response
     */
    public function tagDetailProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            $alias = $params[1] ?? '';
            gp247_lang_switch($lang);
        } else {
            $alias = $params[0] ?? '';
        }

        return $this->_tagDetail($alias);
    }

    /**
     * Render the product list for a single keyword tag.
     *
     * @param string $alias Canonical tag alias from the URL.
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\Response
     */
    private function _tagDetail($alias)
    {
        // WHY: feature flag — when the keyword-tag feature is off, the listing must not
        // exist (parity with hiding the field/manager), so treat it as not-found.
        if (!self::tagFeatureEnabled()) {
            return $this->itemNotFound();
        }

        $tag = ShopProductTag::active()->where('alias', $alias)->first();
        if (!$tag) {
            return $this->itemNotFound();
        }

        $dataSearch = (new ShopProductController)->processFilter(['sort', 'price']);

        $products = (new ShopProduct);
        if (!empty($dataSearch['sort'])) {
            $products->setSort($dataSearch['sort']);
        }
        if (!empty($dataSearch['price'])) {
            $products->setRangePrice($dataSearch['price']);
        }
        $products = $products->getProductToTag($tag->alias)
            ->setPaginate()
            ->setLimit(gp247_config('product_list'))
            ->getData();

        $subPath = 'screen.shop_product_list';
        $view = gp247_shop_process_view($this->GP247TemplatePath, $subPath);
        gp247_check_view($view);

        return view(
            $view,
            array(
                'title'       => $tag->name,
                'description' => '',
                'keyword'     => $tag->name,
                'products'    => $products,
                'tag'         => $tag,
                'filter_sort' => gp247_clean(data: request('filter_sort'), hight: true),
                'layout_page' => ShopLayoutPage::ProductList->value,
                'breadcrumbs' => [
                    ['url' => '', 'title' => $tag->name],
                ],
            )
        );
    }

    /**
     * Whether the keyword-tag feature (`product_tags` config) is enabled. An absent
     * key is treated as enabled so default behaviour holds before first seed.
     *
     * @return bool
     */
    private static function tagFeatureEnabled(): bool
    {
        if (!function_exists('gp247_config')) {
            return true;
        }
        $value = gp247_config('product_tags');

        return $value !== '0' && $value !== 0;
    }
}
