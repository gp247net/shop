<?php
use GP247\Shop\Events\OrderSuccess;
use GP247\Shop\Events\OrderCreated;
use GP247\Shop\Events\CustomerCreated;
use GP247\Shop\Events\OrderUpdateStatus;
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
        OrderSuccess::dispatch($order);
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
        OrderCreated::dispatch($order);
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
        OrderUpdateStatus::dispatch($order);
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
        CustomerCreated::dispatch($customer);
    }
}

