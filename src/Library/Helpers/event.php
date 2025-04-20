<?php
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopCustomer;

if (!function_exists('gp247_event_order_success') && !in_array('gp247_event_order_success', config('gp247_functions_except', []))) {
    /**
     * Process order event
     *
     * @return  [type]          [return description]
     */
    function gp247_event_order_success(ShopOrder $order)
    {
        if(function_exists('gp247_listen_order_success')){
            gp247_listen_order_success($order);
        }
    }
}

if (!function_exists('gp247_event_order_created') && !in_array('gp247_event_order_created', config('gp247_functions_except', []))) {
    /**
     * Process order event
     *
     * @return  [type]          [return description]
     */
    function gp247_event_order_created(ShopOrder $order)
    {
        if(function_exists('gp247_listen_order_created')){
            gp247_listen_order_created($order);
        }

    }
}

if (!function_exists('gp247_event_order_update_status') && !in_array('gp247_event_order_update_status', config('gp247_functions_except', []))) {
    /**
     * Process event order update status
     *
     * @return  [type]          [return description]
     */
    function gp247_event_order_update_status(ShopOrder $order)
    {
        if(function_exists('gp247_listen_order_update_status')){
            gp247_listen_order_update_status($order);
        }
    }
}

if (!function_exists('gp247_event_customer_created') && !in_array('gp247_event_customer_created', config('gp247_functions_except', []))) {
    /**
     * Process customer event
     *
     * @return  [type]          [return description]
     */
    function gp247_event_customer_created(ShopCustomer $customer)
    {
        if(function_exists('gp247_listen_customer_created')){
            gp247_listen_customer_created($customer);
        }
    }
}

