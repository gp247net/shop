<?php

namespace GP247\Shop\Api\Support\Commerce;

use GP247\Shop\Models\ShopProduct;

/**
 * What an app needs to sell a product, added to the v2 product: the attribute groups
 * with their options and the surcharge the cart charges, the main image in its sizes,
 * and whether it can be bought from stock — never the absolute stock.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-product-purchase-data
 */
final class ProductPurchaseData
{
    public const IN_STOCK = 'in_stock';
    public const OUT_OF_STOCK = 'out_of_stock';

    /** Relations to eager load so a product list reads attributes in one go. */
    public const RELATIONS = ['attributes.attGroup'];

    /**
     * Attribute groups as the storefront shows them: every group of the product must be
     * chosen to add it to the cart (gp247_cart_options_complete), so each is `required`.
     * `value` is what the cart takes in `options[group_id]`; `add_price` is in the same
     * unit as `price_final` (null where the store hides prices).
     *
     * @param ShopProduct $product
     * @param bool        $showPrice
     * @return array<int, array{group_id: string, name: string, required: bool, options: array<int, array{value: string, add_price: float|null}>}>
     */
    public static function attributes(ShopProduct $product, bool $showPrice): array
    {
        $groups = [];
        foreach ($product->attributes as $attribute) {
            $groupId = (string) $attribute->attribute_group_id;
            $groups[$groupId] ??= [
                'group_id' => $groupId,
                'name'     => (string) ($attribute->attGroup->name ?? ''),
                'required' => true,
                'options'  => [],
            ];
            $groups[$groupId]['options'][] = [
                'value'     => (string) $attribute->name,
                'add_price' => $showPrice ? (float) $attribute->add_price : null,
            ];
        }

        return array_values($groups);
    }

    /**
     * Same rule as ShopProduct::allowSale() for stock: a shop that does not track stock, or
     * that sells out of stock, always has the product in stock.
     *
     * @param ShopProduct $product
     * @return string IN_STOCK | OUT_OF_STOCK
     */
    public static function stockStatus(ShopProduct $product): string
    {
        $storeId = config('app.storeId');
        if (empty(gp247_config('product_stock', $storeId)) || gp247_config('product_buy_out_of_stock', $storeId)) {
            return self::IN_STOCK;
        }

        return (float) $product->stock > 0 ? self::IN_STOCK : self::OUT_OF_STOCK;
    }

    /**
     * @param string|null $path Image path as stored on the product.
     * @return array{original: string, thumb: string} Absolute URLs (the site's placeholder when there is no image).
     */
    public static function imageSizes(?string $path): array
    {
        return [
            'original' => gp247_file(gp247_image_get_path($path)),
            'thumb'    => gp247_file(gp247_image_get_path_thumb($path)),
        ];
    }
}
