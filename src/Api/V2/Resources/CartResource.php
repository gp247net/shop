<?php

namespace GP247\Shop\Api\V2\Resources;

use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Services\CartService;

/**
 * Cart answer of API v2: lines grouped by store with server-side prices (final price +
 * attribute surcharges, in the display currency, as the order line would store them),
 * each line flagged when it can no longer be bought, and a subtotal of the buyable lines.
 * Products are read in one batch, so the number of queries does not grow with the lines.
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-cart-view
 */
final class CartResource
{
    /**
     * @param CartService $cart Cart instance to render.
     * @return array<string, mixed>
     */
    public static function make(CartService $cart): array
    {
        $lines = $cart->content();
        $products = ShopProduct::query()
            ->with(['descriptions' => fn ($q) => $q->where('lang', gp247_get_locale()), 'promotionPrice', 'attributes'])
            ->whereIn('id', $lines->pluck('id')->unique()->values())
            ->get()
            ->keyBy('id');

        $stores = [];
        foreach ($lines as $line) {
            $product = $products->get($line->id);
            if (!$product) {
                continue;
            }
            $row = self::line($line, $product);
            $storeId = (string) $line->storeId;
            $stores[$storeId] ??= ['store_id' => $storeId, 'lines' => [], 'subtotal' => 0];
            $stores[$storeId]['lines'][] = $row;
            if ($row['purchasable']) {
                $stores[$storeId]['subtotal'] += $row['line_total'];
            }
        }

        return [
            'stores'     => array_values($stores),
            'line_count' => $lines->count(),
            'item_count' => (float) $lines->sum('qty'),
            'currency'   => gp247_currency_code(),
        ];
    }

    /**
     * One cart line.
     *
     * @param \GP247\Shop\Services\CartItem $line
     * @param ShopProduct                   $product
     * @return array<string, mixed>
     */
    private static function line($line, ShopProduct $product): array
    {
        $options = is_array($line->options) ? $line->options : [];
        // WHY the same expression as the order line: the price shown must be the price filed.
        $unitPrice = gp247_currency_value($product->getFinalPrice() + gp247_cart_options_price($options));

        $reason = null;
        if (!$product->allowSale()) {
            $reason = 'not_for_sale';
        } elseif ($product->attributes->pluck('attribute_group_id')->unique()->diff(array_keys($options))->isNotEmpty()) {
            $reason = 'missing_options';
        }

        return [
            'id'          => (string) $line->rowId,
            'product_id'  => (string) $line->id,
            'sku'         => (string) $product->sku,
            'name'        => (string) ($product->descriptions->first()->name ?? ''),
            'image'       => gp247_file($product->getThumb()),
            'qty'         => (float) $line->qty,
            'options'     => self::options($options),
            'unit_price'  => $unitPrice,
            'line_total'  => $unitPrice * $line->qty,
            'purchasable' => $reason === null,
            'reason'      => $reason,
        ];
    }

    /**
     * Chosen options as [{group_id, name, add_price}] from the stored "name__price[__slug]" form.
     *
     * @param array $options
     * @return array<int, array<string, mixed>>
     */
    private static function options(array $options): array
    {
        $out = [];
        foreach ($options as $groupId => $encoded) {
            $parts = explode('__', (string) $encoded);
            $out[] = ['group_id' => (string) $groupId, 'name' => $parts[0], 'add_price' => (float) ($parts[1] ?? 0)];
        }

        return $out;
    }
}
