<?php

/**
     * Initialize cart object
 */
if (!function_exists('gp247_cart') && !in_array('gp247_cart', config('gp247_functions_except', []))) {
    function gp247_cart($instance = null)
    {
        $cart = new \GP247\Shop\Services\CartService;
        
        if ($instance !== null) {
            $cart->instance($instance);
        }
        return $cart;
    }
}

// Get price of options
if (!function_exists('gp247_cart_options_price') && !in_array('gp247_cart_options_price', config('gp247_functions_except', []))) {
    function gp247_cart_options_price($options)
    {
        if (is_array($options)) {
            $price = 0;
            foreach ($options as $option) {
                $price += explode('__', $option)[1] ?? 0;
            }
            return $price;
        }
        return 0;
    }
}


// Process data cart
if (!function_exists('gp247_cart_process_data') && !in_array('gp247_cart_process_data', config('gp247_functions_except', []))) {
    function gp247_cart_process_data($cartItem)
    {
        $attributesGroup = \GP247\Shop\Models\ShopAttributeGroup::pluck('name', 'id')->all();
        $dataFinal = [];
        foreach ($cartItem as $item) {
            $product = (new \GP247\Shop\Models\ShopProduct)->start()->getDetail($item->id, null, $item->storeId);
            if(!$product) {
                continue;
            }
            $url = $product->getUrl();
            $priceItem = $product->getFinalPrice();
            $priceItem += gp247_cart_options_price($item->options);
            $priceTotal = $priceItem * $item->qty;
            $processOptions = [];
            foreach ($item->options as $groupAtt => $att) {
                $processOptions[] = [
                    'name' => $attributesGroup[$groupAtt],
                    'value' => gp247_render_option_price($att),
                ];
            }
            $dataFinal[] = [
                'process_product_name' => $product->name,
                'process_product_id' => $item->id,
                'process_product_sku' => $product->sku,
                'process_product_image' => $product->getImage(),
                'process_product_url' => $url,
                'process_product_show_price' => $product->showPrice(),
                'process_product_price_subtotal' => $priceTotal,
                'process_product_display_vendor' => $product->displayVendor(),
                'process_cart_id' => $item->rowId,
                'process_store_id' => $item->storeId,
                'process_qty' => $item->qty,
                'process_attributes' => $processOptions,
            ];
        }
        return $dataFinal;
    }
}




/*
    Return price with tax
*/
if (!function_exists('gp247_tax_price') && !in_array('gp247_tax_price', config('gp247_functions_except', []))) {
    function gp247_tax_price($price, $tax)
    {
        return round($price * (100 + $tax) /100, 2);
    }
}

/**
 * Render html option price
 *
 * @param   string $arrtribute  format: attribute-name__value-option-price
 * @param   string $currency    code currency
 * @param   string  $rate        rate exchange
 * @param   string               [ description]
 *
 * @return  [type]             [return description]
 */
if (!function_exists('gp247_render_option_price') && !in_array('gp247_render_option_price', config('gp247_functions_except', []))) {
    function gp247_render_option_price($arrtribute, $currency = null, $rate = null, $format = '%s<span class="option_price">%s</span>')
    {
        $html = '';
        $tmpAtt = explode('__', $arrtribute);
        $add_price = $tmpAtt[1] ?? 0;
        if ($add_price) {
            $html = sprintf($format, $tmpAtt[0], "(+".gp247_currency_render($add_price, $currency, $rate).")");
        } else {
            $html = sprintf($format, $tmpAtt[0], "");
        }
        return $html;
    }
}



/**
 * Get list store of product detail
 */
if (!function_exists('gp247_get_list_store_of_product_detail') && !in_array('gp247_get_list_store_of_product_detail', config('gp247_functions_except', []))) {
    function gp247_get_list_store_of_product_detail($pId):array
    {
        return \GP247\Shop\Models\ShopProductStore::where('product_id', $pId)
        ->pluck('store_id')
        ->toArray();
    }
}



/**
 * Get store list of supplier
 */
if (!function_exists('gp247_get_list_store_of_supplier') && !in_array('gp247_get_list_store_of_supplier', config('gp247_functions_except', []))) {
    function gp247_get_list_store_of_supplier(array $arrSupplierId)
    {
        $tableStore = (new \GP247\Core\Models\AdminStore)->getTable();
        $tableSupplier = (new \GP247\Shop\Models\ShopSupplier)->getTable();
        return \GP247\Shop\Models\ShopSupplier::selectRaw($tableStore.'.code,'.$tableStore.'.id,'.$tableSupplier.'.id as supplier_id')
            ->leftJoin($tableStore, $tableStore.'.id', $tableSupplier.'.store_id')
            ->whereIn($tableSupplier.'.id', $arrSupplierId)
            ->get()
            ->groupBy('supplier_id');
    }
}

/**
 * Get store list of banners
 */
if (!function_exists('gp247_get_list_store_of_banner') && !in_array('gp247_get_list_store_of_banner', config('gp247_functions_except', []))) {
    function gp247_get_list_store_of_banner(array $arrBannerId)
    {
        $tableStore = (new \GP247\Core\Models\AdminStore)->getTable();
        $tableBannerStore = (new \GP247\Front\Models\FrontBannerStore)->getTable();
        return \GP247\Front\Models\FrontBannerStore::select($tableStore.'.code', $tableStore.'.id', 'banner_id')
            ->leftJoin($tableStore, $tableStore.'.id', $tableBannerStore.'.store_id')
            ->whereIn('banner_id', $arrBannerId)
            ->get()
            ->groupBy('banner_id');
    }
}
/**
 * Get store list of pages
 */
if (!function_exists('gp247_get_list_store_of_page') && !in_array('gp247_get_list_store_of_page', config('gp247_functions_except', []))) {
    function gp247_get_list_store_of_page(array $arrPageId)
    {
        $tableStore = (new \GP247\Core\Models\AdminStore)->getTable();
        $tablePageStore = (new \GP247\Front\Models\FrontPageStore)->getTable();
        return \GP247\Front\Models\FrontPageStore::select($tableStore.'.code', $tableStore.'.id', 'page_id')
            ->leftJoin($tableStore, $tableStore.'.id', $tablePageStore.'.store_id')
            ->whereIn('page_id', $arrPageId)
            ->get()
            ->groupBy('page_id');
    }
}

/**
 * Get list store of banner detail
 */
if (!function_exists('gp247_get_list_store_of_banner_detail') && !in_array('gp247_get_list_store_of_banner_detail', config('gp247_functions_except', []))) {
    function gp247_get_list_store_of_banner_detail($bId):array
    {
        return \GP247\Front\Models\FrontBannerStore::where('banner_id', $bId)
            ->pluck('store_id')
            ->toArray();
    }
}

/**
 * Get store list of orders
 */
if (!function_exists('gp247_get_list_store_of_order') && !in_array('gp247_get_list_store_of_order', config('gp247_functions_except', []))) {
    function gp247_get_list_store_of_order(array $arrOrderId)
    {
        $tableStore = (new \GP247\Core\Models\AdminStore)->getTable();
        $tableOrder = (new \GP247\Shop\Models\ShopOrder)->getTable();
        return \GP247\Shop\Models\ShopOrder::select($tableStore.'.code', $tableOrder.'.id')
            ->leftJoin($tableStore, $tableStore.'.id', $tableOrder.'.store_id')
            ->whereIn($tableOrder.'.id', $arrOrderId)
            ->get()
            ->groupBy('id');
    }
}

/**
 * Get store list of categories
 */
if (!function_exists('gp247_get_list_store_of_category') && !in_array('gp247_get_list_store_of_category', config('gp247_functions_except', []))) {
    function gp247_get_list_store_of_category(array $arrCategoryId)
    {
        $tableStore = (new \GP247\Core\Models\AdminStore)->getTable();
        $tableCategoryStore = (new \GP247\Shop\Models\ShopCategoryStore)->getTable();
        return \GP247\Shop\Models\ShopCategoryStore::select($tableStore.'.code', $tableStore.'.id', 'category_id')
            ->leftJoin($tableStore, $tableStore.'.id', $tableCategoryStore.'.store_id')
            ->whereIn('category_id', $arrCategoryId)
            ->get()
            ->groupBy('category_id');
    }
}


/**
 * Get list store of category detail
 */
if (!function_exists('gp247_get_list_store_of_category_detail') && !in_array('gp247_get_list_store_of_category_detail', config('gp247_functions_except', []))) {
    function gp247_get_list_store_of_category_detail($cId):array
    {
        return \GP247\Shop\Models\ShopCategoryStore::where('category_id', $cId)
            ->pluck('store_id')
            ->toArray();
    }
}

/**
 * Path vendor
 */
if (!function_exists('gp247_path_vendor') && !in_array('gp247_path_vendor', config('gp247_functions_except', []))) {
    function gp247_path_vendor($code = null)
        {
            if ($code) {
                return gp247_route_front('MultiVendorPro.detail', ['code' => $code]);
            } else {
                return null;
            }
        }
}