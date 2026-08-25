<?php

use GP247\Shop\Controllers\ShopProductTagController;

// Storefront keyword-tag listing (US-SFRONT-product-tags): /tag/<alias> lists every
// active product carrying the tag. Route name `shop.tag` is what ShopProductTag::getUrl
// resolves. Prefix overridable via config, defaulting to `tag`.
$langUrl = GP247_SEO_LANG ? '{lang?}/' : '';
$suffix = GP247_SUFFIX_URL;

$prefixTag = config('gp247-config.shop.route.GP247_PREFIX_TAG') ?? 'tag';
$tagController = gp247_namespace(ShopProductTagController::class);

Route::group(
    [
        'prefix' => $langUrl.$prefixTag,
    ],
    function ($router) use ($suffix, $tagController) {
        $router->get('/{alias}'.$suffix, $tagController.'@tagDetailProcessFront')
            ->name('shop.tag');
    }
);
