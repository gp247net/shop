<?php

namespace GP247\Shop\Support;

/**
 * Catalog of shop storefront page-types for the LayoutBlock "Page" scope.
 *
 * Single source of truth for shop: each case value is the `$layout_page` token
 * a shop controller emits at render time. Registered into
 * `config('gp247-config.front.layout_page')` by `ShopServiceProvider`. Mirrors
 * `GP247\Front\Support\FrontLayoutPage` (ADR front-admin_layout-page-enum-catalog).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-FADM-004
 * @aidlc-adr ADR-front-admin-layout-page-enum-catalog
 */
enum ShopLayoutPage: string
{
    case ItemList = 'shop_item_list';
    case ProductList = 'shop_product_list';
    case ProductDetail = 'shop_product_detail';
    case Cart = 'shop_cart';
    case Checkout = 'shop_checkout';
    case CheckoutConfirm = 'shop_checkout_confirm';
    case OrderSuccess = 'shop_order_success';
    case Wishlist = 'shop_wishlist';
    case Compare = 'shop_compare';
    case Profile = 'shop_profile';
    case Auth = 'shop_auth';
    case Search = 'shop_search';

    /**
     * i18n label code (seeded in DataShopInitializeSeeder, group admin.layout_block).
     *
     * WHY: shop's seeded codes match the token spelling
     * (`admin.layout_block_page.shop_cart`), so the code is derived from value.
     *
     * @return string
     */
    public function label(): string
    {
        return 'admin.layout_block_page.' . $this->value;
    }

    /**
     * Build the token => i18n label code map for registry registration.
     *
     * @return array<string, string>
     */
    public static function registry(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }
        return $out;
    }
}
