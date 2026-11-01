<?php

namespace GP247\Shop\Api\V2\Resources;

use GP247\Core\Api\V2\ApiShape;
use GP247\Shop\Api\Support\ShopApiVisibility;
use GP247\Shop\Models\ShopCategory;

/**
 * Declared shapes (fields[<type>] and include) of the shop's REST API v2 resources. Field
 * names follow the resources exactly; a field mapped to relations lets an endpoint skip
 * loading them when the field is not chosen. Includes are declared per caller: the
 * storefront only sees enabled brands and visible categories; payments and history of an
 * order are for admins only.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-sparse-fieldsets, US-CMP-api-include-relations
 * @aidlc-adr compat-foundation_api-http-caching-and-shaping
 */
final class ShopShapes
{
    public const BRAND = ['id', 'name', 'alias', 'image', 'url', 'sort', 'status', 'store_id', 'created_at', 'updated_at'];

    public const CATEGORY = ['id', 'alias', 'name', 'keyword', 'description', 'image', 'parent', 'top', 'sort', 'status', 'store_id', 'created_at', 'updated_at'];

    public const SUPPLIER = ['id', 'name', 'alias', 'email', 'phone', 'image', 'address', 'url', 'sort', 'status', 'store_id', 'created_at', 'updated_at'];

    private const CUSTOMER = ['id', 'first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'email', 'phone', 'sex', 'birthday',
        'company', 'country', 'postcode', 'city', 'district', 'ward', 'address1', 'address2', 'address3', 'status', 'group', 'created_at', 'updated_at'];

    private const ORDER = ['id', 'customer_id', 'status', 'payment_status', 'shipping_status', 'currency', 'exchange_rate', 'subtotal', 'tax',
        'shipping', 'discount', 'other_fee', 'total', 'received', 'balance', 'first_name', 'last_name', 'email', 'phone', 'company', 'country',
        'postcode', 'city', 'district', 'ward', 'address1', 'address2', 'address3', 'comment', 'payment_method', 'shipping_method', 'store_id',
        'details' => ['details'], 'totals' => ['orderTotal'], 'created_at', 'updated_at'];

    private const TRANSACTION = ['id', 'type', 'amount', 'currency', 'method', 'gateway_transaction_id', 'paid_at', 'created_at'];

    private const HISTORY = ['id', 'content', 'order_status_id', 'admin_id', 'customer_id', 'created_at'];

    /**
     * Shape of a product.
     *
     * @param bool   $admin  Admin view (inventory fields; every brand / category).
     * @param bool   $detail Detail view (HTML body).
     * @param string $lang   Language of included categories.
     * @return ApiShape
     */
    public static function product(bool $admin, bool $detail, string $lang): ApiShape
    {
        $text = ['descriptions'];
        $fields = ['sku', 'alias', 'name' => $text, 'keyword' => $text, 'description' => $text];
        if ($detail) {
            $fields['content'] = $text;
        }
        $fields = array_merge($fields, ['image', 'images' => ['images'], 'price', 'price_final' => ['promotionPrice'], 'tax_id', 'kind',
            'product_type', 'brand_id', 'category_ids' => ['categories'], 'minimum', 'weight', 'weight_class', 'length', 'width', 'height',
            'length_class', 'date_available', 'store_id', 'created_at', 'updated_at', 'image_sizes', 'stock_status', 'attributes' => ['attributes']]);
        if ($admin) {
            $fields = array_merge($fields, ['cost', 'stock', 'sold', 'status', 'approve', 'supplier_id', 'view']);
        }

        $table = (new ShopCategory)->getTable();
        $categories = $admin
            ? fn ($q) => $q->with('descriptions')
            : fn ($q) => $q->whereIn($table . '.id', ShopApiVisibility::categories()->reorder()->select($table . '.id')->toBase())->with('descriptions');

        return new ApiShape('product', $fields, [
            'images' => [
                'with'   => ['images'],
                'render' => fn ($p) => $p->images->pluck('image')->values()->all(),
            ],
            'brand' => [
                'with'   => ['brand' => $admin ? fn ($q) => $q : fn ($q) => $q->where('status', 1)],
                'render' => fn ($p) => $p->brand ? ShopResources::brand($p->brand) : null,
                'type'   => 'brand',
                'fields' => self::BRAND,
            ],
            'categories' => [
                'with'   => ['categories' => $categories],
                'render' => fn ($p) => $p->categories->map(fn ($c) => ShopResources::category($c, $lang))->values()->all(),
                'type'   => 'category',
                'fields' => self::CATEGORY,
            ],
        ]);
    }

    /**
     * Shape of an order: lines and totals for everyone; payments and history only for admins.
     *
     * @param bool $admin
     * @return ApiShape
     */
    public static function order(bool $admin): ApiShape
    {
        if (!$admin) {
            return new ApiShape('order', self::ORDER);
        }

        return new ApiShape('order', array_merge(self::ORDER, ['ip', 'user_agent', 'device_type', 'domain', 'transaction']), [
            'transactions' => [
                'with'   => ['transactions'],
                'render' => fn ($o) => $o->transactions->sortBy('created_at')->map(fn ($t) => [
                    'id' => $t->id, 'type' => $t->type, 'amount' => (float) $t->amount, 'currency' => $t->currency, 'method' => $t->method,
                    'gateway_transaction_id' => $t->gateway_transaction_id,
                    'paid_at' => $t->paid_at ? \Illuminate\Support\Carbon::parse($t->paid_at)->toIso8601String() : null,
                    'created_at' => optional($t->created_at)->toIso8601String(),
                ])->values()->all(),
                'type'   => 'transaction',
                'fields' => self::TRANSACTION,
            ],
            'histories' => [
                'with'   => ['history'],
                // WHY plain text: the admin screen renders history as HTML; an API client must
                // never receive markup it might render unescaped.
                'render' => fn ($o) => $o->history->sortBy('add_date')->map(fn ($h) => [
                    'id' => $h->id, 'content' => trim(html_entity_decode(strip_tags((string) $h->content), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                    'order_status_id' => (int) $h->order_status_id, 'admin_id' => $h->admin_id, 'customer_id' => $h->customer_id,
                    'created_at' => $h->add_date ? \Illuminate\Support\Carbon::parse($h->add_date)->toIso8601String() : null,
                ])->values()->all(),
                'type'   => 'history',
                'fields' => self::HISTORY,
            ],
        ]);
    }

    /**
     * @return ApiShape
     */
    public static function category(): ApiShape
    {
        return new ApiShape('category', self::CATEGORY);
    }

    /**
     * @return ApiShape
     */
    public static function brand(): ApiShape
    {
        return new ApiShape('brand', self::BRAND);
    }

    /**
     * @return ApiShape
     */
    public static function supplier(): ApiShape
    {
        return new ApiShape('supplier', self::SUPPLIER);
    }

    /**
     * @param bool $admin Admin view (store and verification fields).
     * @return ApiShape
     */
    public static function customer(bool $admin): ApiShape
    {
        return new ApiShape('customer', $admin ? array_merge(self::CUSTOMER, ['store_id', 'email_verified_at']) : self::CUSTOMER);
    }
}
