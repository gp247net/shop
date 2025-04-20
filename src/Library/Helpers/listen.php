<?php
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopCustomer;

if (!function_exists('gp247_listen_order_success') && !in_array('gp247_listen_order_success', config('gp247_functions_except', []))) {
    /**
     * Process order event
     *
     * @return  [type]          [return description]
     */
    function gp247_listen_order_success(ShopOrder $order)
    {
        gp247_notice_add(type: 'Order', typeId: $order->id, content:'admin_notice.gp247_new_order_success::name__'.$order->id);
    }
}

if (!function_exists('gp247_listen_order_created') && !in_array('gp247_listen_order_created', config('gp247_functions_except', []))) {
    /**
     * Process order event
     *
     * @return  [type]          [return description]
     */
    function gp247_listen_order_created(ShopOrder $order)
    {
        //Process event here

    }
}

if (!function_exists('gp247_listen_order_update_status') && !in_array('gp247_listen_order_update_status', config('gp247_functions_except', []))) {
    /**
     * Process event order update status
     *
     * @return  [type]          [return description]
     */
    function gp247_listen_order_update_status(ShopOrder $order)
    {
        //Process event here
    }
}

if (!function_exists('gp247_listen_customer_created') && !in_array('gp247_listen_customer_created', config('gp247_functions_except', []))) {
    /**
     * Process customer event
     *
     * @return  [type]          [return description]
     */
    function gp247_listen_customer_created(ShopCustomer $customer)
    {
        //Process event here
    }
}

