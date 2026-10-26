<?php

namespace GP247\Shop\Api\Support;

use GP247\Core\Api\Support\ApiVisibility;
use GP247\Core\Models\AdminStore;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopProduct;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rows the public catalog API (v1 and v2) may serve: exactly what the storefront of
 * the calling domain shows. One place for both API versions.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-public-field-allowlist, US-CMP-api-v2-contract
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
final class ShopApiVisibility
{
    /**
     * Products the storefront shows — ShopProduct::buildQuery() is the single source.
     *
     * @return Builder
     */
    public static function products(): Builder
    {
        return ApiVisibility::visible((new ShopProduct)->buildQuery());
    }

    /**
     * Active categories of this domain's store (every level), by the store rule of
     * ShopCategory's storefront queries.
     *
     * @return Builder
     */
    public static function categories(): Builder
    {
        $table = (new ShopCategory)->getTable();
        $query = ShopCategory::query()->select($table . '.*')->where($table . '.status', 1);
        if (gp247_store_check_multi_store_installed()) {
            $tableStore = (new AdminStore)->getTable();
            $query->join($tableStore, $tableStore . '.id', $table . '.store_id')
                ->where($tableStore . '.status', '1')
                ->where($table . '.store_id', config('app.storeId'));
        }
        return $query;
    }
}
