<?php

namespace GP247\Shop\Services\Checkout;

use GP247\Shop\Front\Contracts\CheckoutTotalMethod;
use GP247\Shop\Models\ShopCustomer;
use GP247\Shop\Models\ShopCustomerAddress;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderTotal;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Services\CartItem;
use GP247\Shop\Services\CartService;
use GP247\Shop\Shipping\ShippingCheckout;

/**
 * Checkout rules of the storefront, shared by the web (ShopCartController, CheckoutWizard)
 * and, later, the REST API: default address, available methods, quantity and minimum
 * checks, totals + idempotency token, placing the order, completing and cancelling it.
 *
 * It reads and writes the checkout through a CheckoutState and never redirects: refusals
 * come back as a PlaceOrderResult. Totals, shipping and the plugins still read the legacy
 * session keys, so the web state is the session (ADR storefront_checkout-service-session-contract).
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-006
 * @aidlc-adr storefront_checkout-service-session-contract
 */
class CheckoutService
{
    public const ORDER_STATUS_NEW = 1;
    public const PAYMENT_UNPAID   = 1;
    public const SHIPPING_NOTSEND = 1;

    /** @var CheckoutState */
    private CheckoutState $state;

    /**
     * @param CheckoutState $state Checkout state to work on (the session on the web).
     */
    public function __construct(CheckoutState $state)
    {
        $this->state = $state;
    }

    /**
     * Checkout state this service works on.
     *
     * @return CheckoutState
     */
    public function state(): CheckoutState
    {
        return $this->state;
    }

    /**
     * Address the checkout form starts from: the customer's default address, else the
     * customer's own fields, else empty fields for a guest. Values are as stored (may be null).
     *
     * @param mixed $customer Signed-in customer, or null.
     * @return array<string, mixed>
     */
    public function defaultAddress($customer): array
    {
        if (!$customer) {
            return [
                'first_name' => '', 'last_name' => '', 'first_name_kana' => '', 'last_name_kana' => '',
                'postcode' => '', 'company' => '', 'email' => '', 'city' => '', 'district' => '', 'ward' => '',
                'address1' => '', 'address2' => '', 'address3' => '', 'country' => '', 'phone' => '', 'comment' => '',
            ];
        }

        // WHY email and company always come from the customer: an address row has neither.
        $source = $customer->getAddressDefault() ?: $customer;

        return [
            'first_name'      => $source->first_name,
            'last_name'       => $source->last_name,
            'first_name_kana' => $source->first_name_kana,
            'last_name_kana'  => $source->last_name_kana,
            'email'           => $customer->email,
            'city'            => $source->city,
            'district'        => $source->district,
            'ward'            => $source->ward,
            'address1'        => $source->address1,
            'address2'        => $source->address2,
            'address3'        => $source->address3,
            'postcode'        => $source->postcode,
            'company'         => $customer->company,
            'country'         => $source->country,
            'phone'           => $source->phone,
            'comment'         => '',
        ];
    }

    /**
     * Active shipping plugins, keyed by plugin key, with their getInfo().
     *
     * @return array<string, mixed>
     */
    public function availableShippingMethods(): array
    {
        $sources = gp247_extension_get_all_local(type: 'Plugins');
        $methods = [];
        foreach (gp247_extension_get_via_code(code: 'shipping') as $module) {
            if (array_key_exists($module['key'], $sources)) {
                $class = gp247_extension_get_namespace(type: 'Plugins', key: $module['key']) . '\AppConfig';
                if (class_exists($class) && method_exists($class, 'getInfo')) {
                    $methods[$module['key']] = (new $class)->getInfo();
                }
            }
        }

        return $methods;
    }

    /**
     * Active payment plugins, keyed by plugin key, with their getInfo().
     *
     * @return array<string, mixed>
     */
    public function availablePaymentMethods(): array
    {
        return $this->pluginInfos(gp247_extension_get_via_code(code: 'payment'));
    }

    /**
     * Active total-method plugins (Promotion ∪ legacy Total), keyed by plugin key, with their getInfo().
     *
     * @return array<string, mixed>
     */
    public function availableTotalMethods(): array
    {
        return $this->pluginInfos(gp247_shop_total_method_modules());
    }

    /**
     * getInfo() of each module whose plugin source exists locally.
     *
     * @param iterable $modules Plugin rows with a 'key'.
     * @return array<string, mixed>
     */
    private function pluginInfos(iterable $modules): array
    {
        $sources = gp247_extension_get_all_local(type: 'Plugins');
        $infos = [];
        foreach ($modules as $module) {
            if (array_key_exists($module['key'], $sources)) {
                $class = $sources[$module['key']] . '\AppConfig';
                $infos[$module['key']] = (new $class)->getInfo();
            }
        }

        return $infos;
    }

    /**
     * Total-method plugin a client may apply or remove, or null.
     *
     * Security boundary for a client-supplied key: it must be an active total-method plugin
     * whose AppConfig implements CheckoutTotalMethod.
     *
     * @param string $key Plugin key.
     * @return CheckoutTotalMethod|null
     */
    public function totalPlugin(string $key): ?CheckoutTotalMethod
    {
        $key = (string) gp247_clean(data: $key, hight: true);
        if (!array_key_exists($key, gp247_shop_total_method_modules())) {
            return null;
        }

        $class = gp247_extension_get_namespace(type: 'Plugins', key: $key) . '\AppConfig';
        if (!class_exists($class) || !is_subclass_of($class, CheckoutTotalMethod::class)) {
            return null;
        }

        return new $class;
    }

    /**
     * Tell whether any line carries a quantity the cart quantity rule refuses.
     *
     * @param iterable $lines Cart lines (CartItem, or plain arrays from a JSON session).
     * @return bool
     *
     * @aidlc-story US-LW-cart-qty-positive
     * @aidlc-adr storefront_cart-quantity-positive-invariant
     */
    public function hasInvalidQuantity(iterable $lines): bool
    {
        foreach ($lines as $line) {
            if (!CartItem::isValidQuantity(CartItem::hydrate($line)->qty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Quantity per product id: lines of one product with different attributes are summed,
     * because the minimum applies per product.
     *
     * @param iterable $lines Cart lines (CartItem, or plain arrays from a JSON session).
     * @return array<string, int|float>
     */
    public function qtyByProduct(iterable $lines): array
    {
        $qty = [];
        foreach ($lines as $line) {
            $line = CartItem::hydrate($line);
            $qty[$line->id] = ($qty[$line->id] ?? 0) + $line->qty;
        }

        return $qty;
    }

    /**
     * Products ordered below their minimum quantity.
     *
     * @param array<string, int|float> $qtyByProduct Quantity per product id.
     * @return array<string, mixed> [productId => minimum] for each product short of its minimum.
     */
    public function minimumShortfall(array $qtyByProduct): array
    {
        if ($qtyByProduct === []) {
            return [];
        }
        $short = [];
        foreach (ShopProduct::whereIn('id', array_keys($qtyByProduct))->pluck('minimum', 'id')->all() as $pId => $min) {
            if ($qtyByProduct[$pId] < $min) {
                $short[$pId] = $min;
            }
        }

        return $short;
    }

    /**
     * Recompute the totals shown to the customer, keep them, and stamp the idempotency
     * token of this checkout when it has none (placeOrder() maps a token to at most one order).
     *
     * @return array Totals as built by ShopOrderTotal::processDataTotal().
     *
     * @aidlc-story US-LW-checkout-idempotency
     * @aidlc-adr storefront_checkout-idempotency
     */
    public function recomputeTotals()
    {
        $totals = ShopOrderTotal::processDataTotal(ShopOrderTotal::getObjectOrderTotal());
        $this->state->setTotals($totals);
        $this->state->ensureToken();

        return $totals;
    }

    /**
     * Place the order of the checkout in progress.
     *
     * Order of checks is part of the contract: lines → guest rule → token → replay of an
     * order already placed with this token → input → line quantities → shipping quote →
     * total freshness. Then the order is written, the chosen shipping rate stored on it,
     * the order kept in the state and a new address saved when asked. The payment step is
     * the caller's.
     *
     * @param CheckoutContext $context Customer, client and input of this submit.
     * @return PlaceOrderResult
     *
     * @aidlc-story US-LW-checkout-idempotency
     * @aidlc-story US-LW-checkout-total-freshness
     */
    public function placeOrder(CheckoutContext $context): PlaceOrderResult
    {
        $state = $this->state;
        if (count($state->lines() ?? collect([])) == 0) {
            return PlaceOrderResult::refused(PlaceOrderResult::CART_EMPTY);
        }
        if (!gp247_config('shop_allow_guest') && !$context->customer) {
            return PlaceOrderResult::refused(PlaceOrderResult::LOGIN_REQUIRED);
        }

        // Idempotency: the confirm step stamped a token; a token that already maps to an
        // order is a Back / refresh / double-click — show that order again, place none.
        $checkoutToken = $state->token();
        if (empty($checkoutToken)) {
            return PlaceOrderResult::refused(PlaceOrderResult::TOKEN_MISSING);
        }
        $existingOrder = ShopOrder::where('checkout_token', $checkoutToken)->first();
        if ($existingOrder) {
            $state->setOrderId($existingOrder->id);
            return PlaceOrderResult::replayed($existingOrder->id);
        }
        if (!$context->hasInput) {
            return PlaceOrderResult::refused(PlaceOrderResult::INPUT_EMPTY);
        }

        // Read once, before the checks, like the web flow always did.
        $read = [
            'totals'         => $state->totals() ?? [],
            'address'        => $state->shippingAddress() ?? [],
            'payment'        => $state->paymentMethod() ?? '',
            'shipping'       => $state->shippingMethod() ?? '',
            'addressProcess' => $state->addressProcess() ?? '',
            'store'          => $state->storeId() ?? '',
            'lines'          => $state->lines() ?? '',
        ];
        $dataTotal    = $read['totals'];
        $dataCheckout = $read['lines'];

        // WHY again at submit: lines staged before the positive-quantity rule shipped can
        // outlive the deploy, and the freshness check cannot see a bad line because both
        // totals derive from it (US-LW-cart-qty-positive).
        if ($this->hasInvalidQuantity($dataCheckout)) {
            return PlaceOrderResult::refused(PlaceOrderResult::INVALID_QUANTITY);
        }
        // A carrier that cannot price this parcel refuses the order instead of billing nothing.
        if (gp247_config('use_shipping') && ShippingCheckout::isUnquotable((string) $read['shipping'])) {
            return PlaceOrderResult::refused(PlaceOrderResult::SHIPPING_UNQUOTABLE);
        }

        // Re-price at submit: if a price / promotion / tax moved since confirm, the customer
        // re-confirms the new figure instead of being charged one they never saw
        // (RISK-BIZ-price-change-mid-checkout).
        $freshDataTotal = ShopOrderTotal::processDataTotal(ShopOrderTotal::getObjectOrderTotal());
        $displayedTotal = (new ShopOrderTotal)->sumValueTotal('total', $dataTotal);
        $freshTotal     = (new ShopOrderTotal)->sumValueTotal('total', $freshDataTotal);
        if (abs((float) $freshTotal - (float) $displayedTotal) > 0.01) {
            $state->setTotals($freshDataTotal);
            $state->markConfirmStep();
            return PlaceOrderResult::refused(PlaceOrderResult::PRICE_CHANGED);
        }
        $state->setTotals($freshDataTotal);

        return $this->writeOrder($context, $checkoutToken, $freshDataTotal, $read);
    }

    /**
     * Write the order and what follows it: rate snapshot, state, new address.
     *
     * @param CheckoutContext $context
     * @param mixed           $checkoutToken
     * @param array           $dataTotal     Fresh totals.
     * @param array           $read          State read at the start of placeOrder().
     * @return PlaceOrderResult
     */
    private function writeOrder(CheckoutContext $context, $checkoutToken, $dataTotal, array $read): PlaceOrderResult
    {
        $state         = $this->state;
        $dataOrder     = $this->orderHeader($context, $checkoutToken, $dataTotal, $read);
        $arrCartDetail = $this->orderLines($read['lines']);

        $newOrder = (new ShopOrder)->createOrder($dataOrder, $dataTotal, $arrCartDetail);
        if ($newOrder['error'] == 1) {
            gp247_report($newOrder['msg']);
            return PlaceOrderResult::refused(PlaceOrderResult::CREATE_FAILED, (string) $newOrder['msg']);
        }

        // Concurrent race resolved by the unique index: createOrder() replayed the order that
        // won. The payment step must not run again (no re-charge).
        if (!empty($newOrder['replayed'])) {
            $state->setOrderId($newOrder['orderID']);
            return PlaceOrderResult::replayed($newOrder['orderID']);
        }

        // WHY after createOrder(): it html-escapes every string column, which would break the JSON
        // (US-shop-shipping-rate-snapshot, ADR shop-shipping_rate-snapshot-and-currency).
        $chosenRate = ShippingCheckout::snapshot();
        if ($chosenRate !== null && $chosenRate->pluginKey === $read['shipping']) {
            ShopOrder::where('id', $newOrder['orderID'])->update([
                'shipping_service' => $chosenRate->serviceCode !== '' ? $chosenRate->serviceCode : null,
                'shipping_rate'    => json_encode($chosenRate->toArray(), JSON_UNESCAPED_UNICODE),
            ]);
        }

        $state->setOrderSnapshot($dataOrder, $arrCartDetail);
        $state->setOrderId($newOrder['orderID']);

        if ($read['addressProcess'] == 'new') {
            $this->saveNewAddress($context->customerId, $read['address']);
            $state->forgetAddressProcess();
        }

        return PlaceOrderResult::placed($newOrder['orderID']);
    }

    /**
     * Order header (shop_order row) from the totals, address and context.
     *
     * @param CheckoutContext $context
     * @param mixed           $checkoutToken
     * @param array           $dataTotal
     * @param array           $read          State read at the start of placeOrder().
     * @return array<string, mixed>
     */
    private function orderHeader(CheckoutContext $context, $checkoutToken, $dataTotal, array $read): array
    {
        $shippingAddress = $read['address'];
        $sum = fn (string $code) => (new ShopOrderTotal)->sumValueTotal($code, $dataTotal);
        // WHY the sums are stored as-is: every total row carries a non-negative magnitude and the
        // sign lives in ShopOrderTotal::SIGN_MAP; `received` is 0 at checkout and kept only so a
        // stale session still resolves (ADR shop-admin_money-sign-convention D1/D3).
        $total    = $sum('total');
        $received = $sum('received');

        $dataOrder = [
            'store_id'        => $read['store'],
            'customer_id'     => $context->customerId,
            'subtotal'        => $sum('subtotal'),
            'shipping'        => $sum('shipping'),
            'discount'        => $sum('discount'),
            'other_fee'       => $sum('other_fee'),
            'received'        => $received,
            'tax'             => $sum('tax'),
            'payment_status'  => self::PAYMENT_UNPAID,
            'shipping_status' => self::SHIPPING_NOTSEND,
            'status'          => self::ORDER_STATUS_NEW,
            'currency'        => gp247_currency_code(),
            'exchange_rate'   => gp247_currency_rate(),
            'total'           => $total,
            'balance'         => $total - $received,
            'email'           => $shippingAddress['email'],
            'first_name'      => $shippingAddress['first_name'],
            'payment_method'  => $read['payment'],
            'shipping_method' => $read['shipping'],
            'user_agent'      => $context->userAgent,
            'device_type'     => $context->deviceType,
            'ip'              => $context->ip,
            'created_at'      => gp247_time_now(),
            // Unique index: the DB-level guarantee that one checkout yields one order.
            'checkout_token'  => $checkoutToken,
        ];
        // WHY guarded: a site updated by composer but not yet by gp247:update lacks the column,
        // and writing it would break every checkout (US-api-commerce-place-order).
        if (ShopOrder::supportsChannel()) {
            $dataOrder['channel'] = $context->channel;
        }
        foreach (['last_name', 'first_name_kana', 'last_name_kana', 'city', 'district', 'ward', 'address1', 'address2',
            'address3', 'country', 'phone', 'postcode', 'company', 'comment'] as $field) {
            if (!empty($shippingAddress[$field])) {
                $dataOrder[$field] = $shippingAddress[$field];
            }
        }

        return $dataOrder;
    }

    /**
     * Order lines re-priced live from the catalogue; a product no longer found is skipped.
     *
     * @param iterable $dataCheckout Cart lines (CartItem, or plain arrays from a JSON session).
     * @return array<int, array<string, mixed>>
     *
     * @aidlc-story US-LW-order-line-effective-price
     * @aidlc-adr storefront_order-line-effective-price
     */
    private function orderLines(iterable $dataCheckout): array
    {
        $lines = [];
        foreach ($dataCheckout as $cartItem) {
            $cartItem = CartItem::hydrate($cartItem);
            $product = (new ShopProduct)->getDetail(key: $cartItem->id, type: 'id', storeId: $cartItem->storeId);
            if (!$product) {
                continue;
            }
            // WHY: the line stores the EFFECTIVE unit price — final price plus canonical attribute
            // surcharges — converted in one pass (same rounding as ShopCurrency::sumCartCheckout),
            // so sum(total_price) equals shop_order.subtotal to the cent (NFR-MAINT-order-line-truth).
            $effectivePrice = gp247_currency_value($product->getFinalPrice() + gp247_cart_options_price($cartItem->options));
            $lines[] = [
                'product_id'  => $cartItem->id,
                'name'        => $cartItem->name,
                'price'       => $effectivePrice,
                'qty'         => $cartItem->qty,
                'store_id'    => $cartItem->storeId,
                'attribute'   => ($cartItem->options) ? $cartItem->options : null,
                'total_price' => $effectivePrice * $cartItem->qty,
            ];
        }

        return $lines;
    }

    /**
     * Save the shipping address as a new address of the customer.
     *
     * @param mixed $customerId
     * @param array $shippingAddress
     * @return void
     */
    private function saveNewAddress($customerId, array $shippingAddress): void
    {
        $address = [];
        foreach (['first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'postcode', 'city', 'district', 'ward',
            'address1', 'address2', 'address3', 'country', 'phone'] as $field) {
            $address[$field] = $shippingAddress[$field] ?? '';
        }

        ShopCustomer::find($customerId)->addresses()->save(new ShopCustomerAddress(gp247_clean($address)));
    }

    /**
     * Complete the placed order: remove its lines from the cart, let the payment, shipping
     * and total plugins finish (endApp), fire the order-success event, end the checkout and
     * run the after-success processing (mail, …).
     *
     * @return mixed The order id, or null when the checkout holds no order.
     */
    public function complete()
    {
        // WHY first, before the order-id check: the historical order of the web flow.
        $this->removeOrderedLines();

        $orderID = $this->state->orderId() ?? 0;
        if ($orderID == 0) {
            return null;
        }

        $this->endApp($this->state->paymentMethod());
        $this->endApp($this->state->shippingMethod());
        $totalMethod = $this->state->totalMethods();
        if ($totalMethod && is_array($totalMethod)) {
            foreach ($totalMethod as $keyMethod => $valueMethod) {
                $this->endApp($keyMethod, ['orderID' => $orderID, 'code' => $valueMethod]);
            }
        }

        gp247_event_order_success(ShopOrder::find($orderID));

        $this->clear();
        gp247_order_process_after_success((string) $orderID);

        return $orderID;
    }

    /**
     * Call a plugin's AppConfig::endApp() when it has one.
     *
     * @param mixed      $key  Plugin key.
     * @param array|null $args Arguments of total plugins; payment/shipping plugins get none.
     * @return void
     */
    private function endApp($key, ?array $args = null): void
    {
        $class = gp247_extension_get_namespace(type: 'Plugins', key: $key) . '\AppConfig';
        if (class_exists($class) && method_exists($class, 'endApp')) {
            $args === null ? (new $class)->endApp() : (new $class)->endApp($args);
        }
    }

    /**
     * Remove the ordered lines from the cart.
     *
     * @return void
     */
    private function removeOrderedLines(): void
    {
        $dataCheckout = $this->state->lines() ?? '';
        if ($dataCheckout) {
            foreach ($dataCheckout as $row) {
                (new CartService)->remove(CartItem::hydrate($row)->rowId);
            }
        }
    }

    /**
     * Cancel the order in flight after the customer aborted at the gateway, then end the checkout.
     *
     * WHY changeStatus() and not a raw update: a raw status write skipped restock, history,
     * events and hooks (RISK-BIZ-cancel-path-bypass-restock). Entering Canceled is never refused.
     *
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-cancel-restock
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public function cancel(): void
    {
        $orderID = $this->state->orderId();
        $order = $orderID ? ShopOrder::find($orderID) : null;
        if ($order) {
            $order->changeStatus(\GP247\Shop\Models\ShopOrderStatus::CANCELED, [
                'content' => 'Payment cancelled',
                'customer_id' => $order->customer_id ?: 0,
            ]);
        }
        $this->clear();
    }

    /**
     * End the checkout: forget methods, totals, lines, store, order data and the token, and
     * the shipping rate snapshot + quotes. The address, step marker and address_process stay.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->state->clear();
        ShippingCheckout::forget();
    }
}
