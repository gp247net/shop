<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductBuild;
use GP247\Shop\Shipping\Data\Money;
use GP247\Shop\Shipping\Data\ShippingItem;

/**
 * The single place a product becomes a shipping line — used by the checkout quote and
 * by the shipment booking alike, so every carrier gets the same profile:
 * grams and centimetres whatever unit the product was entered in, digital goods left
 * out, a bundle weighing what its parts weigh, and the identifying data GP247 already
 * holds. Plugins may enrich the line through `gp247-config.shop.shipping_item_resolvers`
 * (callables fn(ShippingItem, ShopProduct): ShippingItem) — same idiom as price_resolvers.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-product-profile
 * @aidlc-adr shop-shipping_product-shipping-profile
 */
final class ShippingItemFactory
{
    /** @var array<int, string> Product types that are never shipped. */
    private const NOT_SHIPPED = ['download', 'digital'];

    /**
     * @param ShopProduct          $product   Product.
     * @param int                  $qty       Quantity.
     * @param mixed                $options   Chosen options ({group_id: "name__add_price"}, array or JSON).
     * @param Money                $unitValue Value of one unit.
     * @param string               $storeId   Store (default weight).
     * @param string               $name      Line name (cart/order); empty = product name.
     * @return ShippingItem|null Null when the product is not shipped (digital goods).
     */
    public static function fromProduct(ShopProduct $product, int $qty, mixed $options, Money $unitValue, string $storeId, string $name = ''): ?ShippingItem
    {
        if (in_array(strtolower((string) $product->product_type), self::NOT_SHIPPED, true)) {
            return null;
        }

        $item = new ShippingItem(
            name: $name !== '' ? $name : (string) ($product->getName() ?: $product->sku),
            qty: max(1, $qty),
            weightGrams: self::unitWeight($product, $storeId),
            unitValue: $unitValue,
            sku: (string) $product->sku,
            lengthCm: LengthConverter::toCm($product->length, $product->length_class),
            widthCm: LengthConverter::toCm($product->width, $product->length_class),
            heightCm: LengthConverter::toCm($product->height, $product->length_class),
            productId: (string) $product->id,
            variant: self::variant($options),
            barcode: self::barcode($product),
            mpn: (string) ($product->mpn ?? ''),
            brand: (string) ($product->brand->name ?? ''),
            category: (string) ($product->categories()->first()?->getTitle() ?? ''),
            // WHY gp247_file, not getImage(): getImage() swaps in a placeholder when the file is not
            // on this disk; a carrier must get the product's own image or nothing.
            imageUrl: $product->image ? (string) gp247_file((string) $product->image) : '',
        );

        return self::resolve($item, $product);
    }

    /**
     * @param ShopProduct $product Product.
     * @param string      $storeId Store.
     * @return int Grams of one unit: own weight, else the bundle's parts, else the store default.
     */
    private static function unitWeight(ShopProduct $product, string $storeId): int
    {
        $grams = WeightConverter::toGrams($product->weight, $product->weight_class);
        if ($grams > 0) {
            return $grams;
        }
        if ((int) $product->kind === (defined('GP247_PRODUCT_BUILD') ? GP247_PRODUCT_BUILD : 1)) {
            $sum = 0;
            foreach (ShopProductBuild::where('build_id', $product->id)->get() as $part) {
                $child = ShopProduct::find($part->product_id);
                if ($child !== null) {
                    $sum += self::unitWeight($child, $storeId) * max(1, (int) round((float) $part->quantity));
                }
            }
            if ($sum > 0) {
                return $sum;
            }
        }

        return ShippingSettings::defaultWeightGrams($storeId);
    }

    /**
     * @param ShopProduct $product Product.
     * @return string First barcode present: EAN, UPC, JAN, ISBN.
     */
    private static function barcode(ShopProduct $product): string
    {
        foreach (['ean', 'upc', 'jan', 'isbn'] as $column) {
            $code = trim((string) ($product->{$column} ?? ''));
            if ($code !== '') {
                return $code;
            }
        }

        return '';
    }

    /**
     * @param mixed $options {group_id: "name__add_price"} as array or JSON string.
     * @return string "Group: Value, Group: Value".
     */
    private static function variant(mixed $options): string
    {
        if (is_string($options)) {
            $options = json_decode($options, true);
        }
        if (!is_array($options) || $options === []) {
            return '';
        }
        $groupNames = ShopAttributeGroup::whereIn('id', array_keys($options))->pluck('name', 'id')->all();
        $parts = [];
        foreach ($options as $groupId => $value) {
            $label = trim(explode('__', (string) $value)[0]);
            if ($label === '') {
                continue;
            }
            $group = (string) ($groupNames[$groupId] ?? '');
            $parts[] = $group !== '' ? $group . ': ' . $label : $label;
        }

        return implode(', ', $parts);
    }

    /**
     * Apply plugin resolvers; one that throws or returns something else is skipped.
     *
     * @param ShippingItem $item    Line built from core data.
     * @param ShopProduct  $product Product.
     * @return ShippingItem
     */
    private static function resolve(ShippingItem $item, ShopProduct $product): ShippingItem
    {
        foreach ((array) config('gp247-config.shop.shipping_item_resolvers', []) as $resolver) {
            if (!is_callable($resolver)) {
                continue;
            }
            try {
                $out = $resolver($item, $product);
            } catch (\Throwable $e) {
                gp247_report('[GP247 shipping] shipping item resolver failed: ' . $e->getMessage());
                continue;
            }
            if ($out instanceof ShippingItem) {
                $item = $out;
            } else {
                gp247_report('[GP247 shipping] shipping item resolver returned ' . get_debug_type($out) . ', expected ShippingItem — ignored.');
            }
        }

        return $item;
    }
}
