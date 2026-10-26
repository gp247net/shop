<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\V2\ApiQuery;
use GP247\Shop\Models\ShopProduct;
use Illuminate\Database\Eloquent\Builder;

/**
 * Declared filters, sorts and search of product lists in REST API v2 (public and
 * admin share them; the admin list adds status/approve filters).
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-v2-query-parameters
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
final class ProductQuery
{
    /**
     * @param bool $admin Add moderation filters.
     * @return array<string, callable>
     */
    public static function filters(bool $admin = false): array
    {
        $t = (new ShopProduct)->getTable();
        $filters = [
            'category_id'  => fn (Builder $q, string $v) => $q->whereHas('categories', fn ($c) => $c->where($c->getModel()->getTable() . '.id', $v)),
            'brand_id'     => fn (Builder $q, string $v) => $q->where($t . '.brand_id', $v),
            'kind'         => fn (Builder $q, string $v) => $q->where($t . '.kind', (int) $v),
            'product_type' => fn (Builder $q, string $v) => $q->where($t . '.product_type', $v),
            'price_min'    => fn (Builder $q, string $v) => $q->where($t . '.price', '>=', (float) $v),
            'price_max'    => fn (Builder $q, string $v) => $q->where($t . '.price', '<=', (float) $v),
        ];
        if ($admin) {
            $filters['status'] = fn (Builder $q, string $v) => $q->where($t . '.status', (int) $v);
            $filters['approve'] = fn (Builder $q, string $v) => $q->where($t . '.approve', (int) $v);
            $filters['supplier_id'] = fn (Builder $q, string $v) => $q->where($t . '.supplier_id', $v);
        }
        return $filters;
    }

    /**
     * @return array<string, string>
     */
    public static function sorts(): array
    {
        $t = (new ShopProduct)->getTable();

        return ['price' => $t . '.price', 'created_at' => $t . '.created_at', 'sort' => $t . '.sort', 'sku' => $t . '.sku'];
    }

    /**
     * Search by SKU or name (any language).
     *
     * @return callable
     */
    public static function search(): callable
    {
        $t = (new ShopProduct)->getTable();

        return fn (Builder $q, string $term) => $q->where(function (Builder $w) use ($t, $term) {
            $w->where($t . '.sku', 'like', ApiQuery::like($term))
              ->orWhereHas('descriptions', fn ($d) => $d->where('name', 'like', ApiQuery::like($term)));
        });
    }
}
