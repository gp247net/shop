<?php
return [        
    // Config for front
    'shop' => [
        'middleware' => [
            1 => 'customer.auth',
        ],
        // Route for front
        'route' => [
            'GP247_PREFIX_MEMBER' => env('GP247_PREFIX_MEMBER', 'customer'), 
            'GP247_PREFIX_BRAND' => env('GP247_PREFIX_BRAND', 'brand'), 
            'GP247_PREFIX_CATEGORY' => env('GP247_PREFIX_CATEGORY', 'category'),
            'GP247_PREFIX_PRODUCT' => env('GP247_PREFIX_PRODUCT', 'product'),
            'GP247_PREFIX_CART_WISHLIST' => env('GP247_PREFIX_CART_WISHLIST', 'wishlist'),
            'GP247_PREFIX_CART_COMPARE' => env('GP247_PREFIX_CART_COMPARE', 'compare'),
            'GP247_PREFIX_CART_DEFAULT' => env('GP247_PREFIX_CART_DEFAULT', 'cart'),
            'GP247_PREFIX_CART_CHECKOUT' => env('GP247_PREFIX_CART_CHECKOUT', 'checkout'),
            'GP247_PREFIX_CART_CHECKOUT_CONFIRM' => env('GP247_PREFIX_CART_CHECKOUT_CONFIRM', 'checkout-confirm'),
            'GP247_PREFIX_ORDER_SUCCESS' => env('GP247_PREFIX_ORDER_SUCCESS', 'order-success'),
            'GP247_PREFIX_ORDER_DETAIL' => env('GP247_PREFIX_ORDER_DETAIL', 'order-detail'),
            'GP247_PREFIX_ORDER_LIST' => env('GP247_PREFIX_ORDER_LIST', 'order-list'),
            'GP247_PREFIX_ORDER_TRACKING' => env('GP247_PREFIX_ORDER_TRACKING', 'order-tracking'),
            'GP247_PREFIX_ORDER_TRACKING_DETAIL' => env('GP247_PREFIX_ORDER_TRACKING_DETAIL', 'order-tracking-detail'),
            'GP247_PREFIX_ORDER_TRACKING_LIST' => env('GP247_PREFIX_ORDER_TRACKING_LIST', 'order-tracking-list'),
            'GP247_PREFIX_ORDER_TRACKING_STATUS' => env('GP247_PREFIX_ORDER_TRACKING_STATUS', 'order-tracking-status'),
            'GP247_PREFIX_MEMBER_ORDER_LIST' => env('GP247_PREFIX_MEMBER_ORDER_LIST', 'order-list'),
            'GP247_PREFIX_MEMBER_ORDER_DETAIL' => env('GP247_PREFIX_MEMBER_ORDER_DETAIL', 'order-detail'),
            'GP247_PREFIX_MEMBER_ADDRESS_LIST' => env('GP247_PREFIX_MEMBER_ADDRESS_LIST', 'address-list'),
            'GP247_PREFIX_MEMBER_UPDATE_ADDRESS' => env('GP247_PREFIX_MEMBER_UPDATE_ADDRESS', 'update-address'),
            'GP247_PREFIX_MEMBER_DELETE_ADDRESS' => env('GP247_PREFIX_MEMBER_DELETE_ADDRESS', 'delete-address'),
            'GP247_PREFIX_MEMBER_CHANGE_PWD' => env('GP247_PREFIX_MEMBER_CHANGE_PWD', 'change-password'),
            'GP247_PREFIX_MEMBER_CHANGE_INFO' => env('GP247_PREFIX_MEMBER_CHANGE_INFO', 'change-infomation'),
        ],

        // Shipping provider registry (mod 20261017T185906, ADR shop-shipping_provider-capability-contract).
        // A shipping plugin adds itself in its Provider.php:
        //   config(['gp247-config.shop.shipping_providers.<PluginKey>' => ['driver' => Class, 'capabilities' => [...]]]);
        'shipping_providers' => [],
        // Payment plugins whose orders are paid in cash on delivery (COD amount of a shipment).
        'cod_payment_methods' => ['CashPayment'],
        'shipping' => [
            // Carrier webhook: {prefix}/shipping/webhook/{pluginKey} (outside the storefront group).
            'webhook_prefix' => env('GP247_SHIPPING_WEBHOOK_PREFIX', 'shipping/webhook'),
            'webhook_throttle' => env('GP247_SHIPPING_WEBHOOK_THROTTLE', '120,1'),
        ],

        // Product delivery-type valid values (formerly 'product_tag'). Renamed to
        // 'product_type' alongside the shop_product.tag -> product_type column rename
        // (compat-foundation_product-type-rename). Distinct from the new keyword-tag feature.
        'product_type' => env('GP247_PRODUCT_TYPE', env('GP247_PRODUCT_TAG', 'digital,physical,download')),

        //Product weight unit
        'product_weight_unit' => env('GP247_PRODUCT_WEIGHT_UNIT', 'g,kg,lb,oz'),

        //Product length unit
        'product_length_unit' => env('GP247_PRODUCT_LENGTH_UNIT', 'mm,cm,m,in,ft'),

        //Cart expire
        'cart_expire' => [
            'cart' => env('GP247_CART_EXPIRE_CART', 7), //days
            'wishlist' => env('GP247_CART_EXPIRE_WISHLIST', 30), //days
            'compare' => env('GP247_CART_EXPIRE_COMPARE', 30), //days
            'lastview' => env('GP247_CART_EXPIRE_PRODUCT_LASTVIEW', 30), //days
        ],
    ],

    // Payment requests: addresses the pay page may send a payer back to after paying or
    // cancelling at the gateway (an app's deep link or web page, kept on the request when it
    // was started from an app). Comma separated, matched on scheme + host (+ port) and path
    // prefix; empty (default) = the payer stays on the site's own pages.
    'payment' => [
        'return_urls' => env('GP247_PAYMENT_RETURN_URLS', ''),
        // Orders placed through the API and still unpaid at the gateway after this many minutes
        // are cancelled (stock back) when the next API order is placed — no cron. 0 = never.
        'api_unpaid_order_minutes' => env('GP247_API_UNPAID_ORDER_MINUTES', 1440),
    ],
];
