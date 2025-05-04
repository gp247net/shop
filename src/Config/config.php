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

        //Product Tag config
        'product_tag' => env('GP247_PRODUCT_TAG', 'digital,physical,download'),

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
];
