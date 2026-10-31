<?php

namespace GP247\Shop\Services\Checkout;

/**
 * Checkout state of the web storefront, in the Laravel session under the historical keys
 * (`storeCheckout`, `dataCheckout`, `shippingAddress`, `address_process`, `shippingMethod`,
 * `paymentMethod`, `totalMethod`, `dataTotal`, `checkoutToken`, `step`, `orderID`,
 * `dataOrder`, `arrCartDetail`). Plugins and ShopOrderTotal / ShippingCheckout /
 * ShopCurrency / gp247_plugin_store_id() read these keys directly, so they must not change.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-006
 * @aidlc-adr storefront_checkout-service-session-contract
 */
class SessionCheckoutState implements CheckoutState
{
    /**
     * {@inheritDoc}
     */
    public function storeId()
    {
        return session('storeCheckout');
    }

    /**
     * {@inheritDoc}
     */
    public function setStoreId($storeId): void
    {
        session(['storeCheckout' => $storeId]);
    }

    /**
     * {@inheritDoc}
     */
    public function lines()
    {
        return session('dataCheckout');
    }

    /**
     * {@inheritDoc}
     */
    public function setLines($lines): void
    {
        session(['dataCheckout' => $lines]);
    }

    /**
     * {@inheritDoc}
     */
    public function shippingAddress()
    {
        return session('shippingAddress');
    }

    /**
     * {@inheritDoc}
     */
    public function setShippingAddress(array $address): void
    {
        session(['shippingAddress' => $address]);
    }

    /**
     * {@inheritDoc}
     */
    public function addressProcess()
    {
        return session('address_process');
    }

    /**
     * {@inheritDoc}
     */
    public function setAddressProcess($value): void
    {
        session(['address_process' => $value]);
    }

    /**
     * {@inheritDoc}
     */
    public function forgetAddressProcess(): void
    {
        session()->forget('address_process');
    }

    /**
     * {@inheritDoc}
     */
    public function shippingMethod()
    {
        return session('shippingMethod');
    }

    /**
     * {@inheritDoc}
     */
    public function setShippingMethod($key): void
    {
        session(['shippingMethod' => $key]);
    }

    /**
     * {@inheritDoc}
     */
    public function paymentMethod()
    {
        return session('paymentMethod');
    }

    /**
     * {@inheritDoc}
     */
    public function setPaymentMethod($key): void
    {
        session(['paymentMethod' => $key]);
    }

    /**
     * {@inheritDoc}
     */
    public function totalMethods(): array
    {
        return (array) session('totalMethod', []);
    }

    /**
     * {@inheritDoc}
     */
    public function totals()
    {
        return session('dataTotal');
    }

    /**
     * {@inheritDoc}
     */
    public function setTotals($totals): void
    {
        session(['dataTotal' => $totals]);
    }

    /**
     * {@inheritDoc}
     */
    public function token()
    {
        return session('checkoutToken');
    }

    /**
     * {@inheritDoc}
     */
    public function ensureToken(): string
    {
        if (!session('checkoutToken')) {
            session(['checkoutToken' => gp247_token(32)]);
        }
        return (string) session('checkoutToken');
    }

    /**
     * {@inheritDoc}
     */
    public function setToken(string $token): void
    {
        session(['checkoutToken' => $token]);
    }

    /**
     * {@inheritDoc}
     */
    public function forgetToken(): void
    {
        session()->forget('checkoutToken');
    }

    /**
     * {@inheritDoc}
     */
    public function step(): string
    {
        return (string) session('step', '');
    }

    /**
     * {@inheritDoc}
     */
    public function markConfirmStep(): void
    {
        session(['step' => 'checkout.confirm']);
    }

    /**
     * {@inheritDoc}
     */
    public function orderId()
    {
        return session('orderID');
    }

    /**
     * {@inheritDoc}
     */
    public function setOrderId($orderId): void
    {
        session(['orderID' => $orderId]);
    }

    /**
     * {@inheritDoc}
     */
    public function setOrderSnapshot(array $dataOrder, array $cartDetail): void
    {
        session(['dataOrder' => $dataOrder]);
        session(['arrCartDetail' => $cartDetail]);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        foreach (['paymentMethod', 'shippingMethod', 'totalMethod', 'otherMethod', 'dataTotal', 'dataCheckout',
            'storeCheckout', 'dataOrder', 'arrCartDetail', 'orderID', 'checkoutToken'] as $key) {
            session()->forget($key);
        }
    }
}
