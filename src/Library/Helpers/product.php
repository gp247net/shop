<?php

use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductDescription;

if (!function_exists('gp247_product_admin_select_list') && !in_array('gp247_product_admin_select_list', config('gp247_functions_except', []))) {
    /**
     * Get list product select use in admin page
     *
     * @param   [string]  $domain
     *
     * @return  [string]         [$domain]
     */
    function gp247_product_admin_select_list(array $dataFilter = [], $storeId = null)
    {
        $keyword          = $dataFilter['keyword'] ?? '';
        $limit            = $dataFilter['limit'] ?? '';
        $kind             = $dataFilter['kind'] ?? [];
        $tableDescription = (new ShopProductDescription)->getTable();
        $tableProduct     = (new ShopProduct)->getTable();
        $colSelect = [
            $tableProduct.'.id',
            $tableProduct.'.sku',
            $tableProduct.'.price',
            $tableDescription . '.name'
        ];
        $productList = (new ShopProduct)->select($colSelect)
            ->leftJoin($tableDescription, $tableDescription . '.product_id', $tableProduct . '.id')
            ->where($tableDescription . '.lang', gp247_get_locale());

        if ($storeId) {
            // WHY: 1-1 ownership — filter by the product's own store_id column.
            // Only get products of store if store <> root or store is specified.
            $productList = $productList->where($tableProduct . '.store_id', $storeId);
        }

        if (is_array($kind) && $kind) {
            $productList = $productList->whereIn('kind', $kind);
        }
        if ($keyword) {
            $productList = $productList->where(function ($sql) use ($tableDescription, $tableProduct, $keyword) {
                $sql->where($tableDescription . '.name', 'like', '%' . $keyword . '%')
                    ->orWhere($tableProduct . '.sku', 'like', '%' . $keyword . '%');
            });
        }

        if ($limit) {
            $productList = $productList->limit($limit);
        }
        $productList->groupBy('id','name','sku','price');
        $dataTmp = $productList->get()->keyBy('id');
        $data = [];
        foreach ($dataTmp as $key => $row) {
            $data[$key] = [
                'id' => $row['id'],
                'sku' => $row['sku'],
                'name' => addslashes($row['name']),
                'price' => (float) $row['price'],
            ];
        }
        return $data;
    }
}