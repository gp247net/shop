<?php

namespace GP247\Shop\Api\Support;

/**
 * Field allowlists of the v1 storefront/member REST API (ApiFieldAllowlist specs).
 * Internal columns — cost price, supplier, stock counters, approval flag, order
 * forensics, customer credentials — are left out on purpose; a new table column
 * only reaches the API when it is added here.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-public-field-allowlist
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
final class ShopApiFields
{
    /** Public product (no cost, supplier_id, stock, sold, approve, view, date_lastview). */
    public const PRODUCT = [
        'id', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'image', 'brand_id', 'price', 'minimum',
        'weight_class', 'weight', 'length_class', 'length', 'width', 'height', 'kind', 'product_type',
        'tax_id', 'status', 'sort', 'alias', 'date_available', 'store_id', 'created_at', 'updated_at',
        'descriptions' => '*', 'images' => '*', 'promotion_price' => '*', 'attributes' => '*',
        'brand' => '*', 'builds' => '*', 'groups' => '*',
    ];

    /** Public brand. */
    public const BRAND = ['id', 'name', 'alias', 'image', 'url', 'status', 'sort', 'store_id', 'created_at', 'updated_at'];

    /** Public category; nested products use the public product spec. */
    public const CATEGORY = [
        'id', 'image', 'alias', 'parent', 'top', 'status', 'sort', 'store_id', 'created_at', 'updated_at',
        'descriptions' => '*', 'products' => self::PRODUCT,
    ];

    /** Order seen by its customer (no ip, user_agent, device_type, checkout_token, transaction, domain). */
    public const MEMBER_ORDER = [
        'id', 'customer_id', 'subtotal', 'shipping', 'discount', 'payment_status', 'shipping_status', 'status',
        'tax', 'other_fee', 'total', 'currency', 'exchange_rate', 'received', 'balance',
        'first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'city', 'district', 'ward',
        'address1', 'address2', 'address3', 'country', 'company', 'postcode', 'phone', 'email', 'comment',
        'payment_method', 'shipping_method', 'shipping_service', 'finish_date', 'store_id', 'created_at', 'updated_at',
        'details' => '*', 'order_total' => '*',
    ];

    /** Customer profile returned by `info` (no password, remember_token, store_id). */
    public const MEMBER_INFO = [
        'id', 'first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'name', 'email', 'sex', 'birthday',
        'address_id', 'postcode', 'city', 'district', 'ward', 'address1', 'address2', 'address3', 'company',
        'country', 'phone', 'status', 'group', 'email_verified_at', 'created_at', 'updated_at',
    ];
}
