<?php

namespace GP247\Shop\Services\Checkout;

/**
 * State of one checkout in progress: the store being checked out, its cart lines, the
 * address, the chosen shipping/payment/total methods, the totals shown, the idempotency
 * token and the order placed. The web keeps it in the session under the keys plugins and
 * the totals/shipping classes already read (SessionCheckoutState); the REST API can load
 * the same state into a request-scoped session before calling the same checkout code.
 *
 * Getters return null when nothing is stored, so callers keep their own defaults.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-006
 * @aidlc-adr storefront_checkout-service-session-contract
 */
interface CheckoutState
{
    /** @return mixed Store id being checked out, or null. */
    public function storeId();

    /** @param mixed $storeId */
    public function setStoreId($storeId): void;

    /** @return mixed Cart lines of the store being checked out (CartItem list), or null. */
    public function lines();

    /** @param mixed $lines */
    public function setLines($lines): void;

    /** @return array|null Shipping/billing address fields, or null. */
    public function shippingAddress();

    /** @param array $address */
    public function setShippingAddress(array $address): void;

    /** @return mixed '' / 'new' / an address id, or null. */
    public function addressProcess();

    /** @param mixed $value */
    public function setAddressProcess($value): void;

    /** @return void */
    public function forgetAddressProcess(): void;

    /** @return mixed Shipping plugin key, or null. */
    public function shippingMethod();

    /** @param mixed $key */
    public function setShippingMethod($key): void;

    /** @return mixed Payment plugin key, or null. */
    public function paymentMethod();

    /** @param mixed $key */
    public function setPaymentMethod($key): void;

    /** @return array Total-method plugins applied, [pluginKey => code] (empty when none). */
    public function totalMethods(): array;

    /** @return array|null Totals shown to the customer, or null. */
    public function totals();

    /** @param array $totals Totals as built by ShopOrderTotal::processDataTotal(). */
    public function setTotals($totals): void;

    /** @return mixed Idempotency token, or null. */
    public function token();

    /**
     * Stamp an idempotency token when none is set yet.
     *
     * @return string The token in use.
     */
    public function ensureToken(): string;

    /** @return void */
    public function forgetToken(): void;

    /** @return string Checkout step marker ('' when none). */
    public function step(): string;

    /** Mark that the customer reached the confirm step. */
    public function markConfirmStep(): void;

    /** @return mixed Id of the order placed, or null. */
    public function orderId();

    /** @param mixed $orderId */
    public function setOrderId($orderId): void;

    /**
     * Keep the order data and lines that were sent to the order (read by plugins).
     *
     * @param array $dataOrder
     * @param array $cartDetail
     * @return void
     */
    public function setOrderSnapshot(array $dataOrder, array $cartDetail): void;

    /**
     * End the checkout: forget methods, totals, lines, store, order data and the token.
     * The address, the step marker and address_process are kept (pre-fill of the next checkout).
     *
     * @return void
     */
    public function clear(): void;
}
