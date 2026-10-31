<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\Middleware\CheckoutSessionBridge;
use GP247\Shop\Api\Support\Commerce\ApiOrderPayment;
use GP247\Shop\Api\Support\Commerce\UnpaidApiOrders;
use GP247\Shop\Api\Support\Commerce\IdempotencyKey;
use GP247\Shop\Api\Support\Commerce\OrderAccessToken;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Services\CartService;
use GP247\Shop\Services\Checkout\CheckoutContext;
use GP247\Shop\Services\Checkout\CheckoutService;
use GP247\Shop\Services\Checkout\PlaceOrderResult;
use GP247\Shop\Services\Checkout\CheckoutState;
use GP247\Shop\Shipping\RateRequestFactory;
use GP247\Shop\Shipping\RateService;
use GP247\Shop\Shipping\ShippingCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Checkout steps of API v2 (api/v2/front/checkout*, cart/promotions): address, delivery
 * options quoted for that address, payment methods usable through the API, promotions and
 * the totals. Runs behind CheckoutSessionBridge, so the state is the owner's and every
 * rule is the website's (CheckoutService, the shipping layer, the plugins).
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-checkout-address
 * @aidlc-story US-api-commerce-checkout-shipping-methods
 * @aidlc-story US-api-commerce-checkout-payment-methods
 * @aidlc-story US-api-commerce-checkout-totals
 * @aidlc-story US-api-commerce-cart-promotion
 * @aidlc-story US-api-commerce-place-order
 * @aidlc-story US-api-commerce-multi-store-checkout
 * @aidlc-adr api-commerce_request-scoped-checkout-session
 * @aidlc-adr api-commerce_idempotency-key
 */
class CheckoutController extends RootFrontController
{
    private const ADDRESS_FIELDS = ['first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'email', 'phone', 'city', 'district',
        'ward', 'address1', 'address2', 'address3', 'postcode', 'country', 'company', 'comment'];

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request)
    {
        return $this->prepare($request) ?? $this->summary();
    }

    /**
     * Set the delivery address: typed fields, or one of the customer's saved addresses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function address(Request $request)
    {
        if ($error = $this->prepare($request)) {
            return $error;
        }
        $customer = customer()->user();
        $fields = $request->only(self::ADDRESS_FIELDS);
        $addressId = (string) $request->input('address_id', '');
        if ($addressId !== '') {
            $saved = $customer ? $customer->addresses()->find($addressId) : null;
            if (!$saved) {
                return ApiErrorResponder::notFound();
            }
            $fields = array_merge($this->savedAddress($saved, $customer), array_filter($fields, fn ($v) => $v !== null));
        }

        $map = gp247_order_mapping_validate();
        $rules = array_diff_key($map['validate'], array_flip(['shippingMethod', 'paymentMethod']));
        Validator::make($fields, $rules, $map['messages'])->validate();

        $address = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $address[$field] = (string) gp247_clean(data: (string) ($fields[$field] ?? ''), hight: true);
        }
        $state = $this->state();
        if ($state->shippingAddress() !== $address) {
            // WHY: a delivery price quoted for another address must be chosen again.
            ShippingCheckout::forget();
            $state->setShippingMethod(null);
        }
        $state->setShippingAddress($address);
        $state->setAddressProcess($customer && $request->boolean('save_address') ? 'new' : $addressId);

        return $this->summary();
    }

    /**
     * Delivery options quoted for the checkout address and cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function shippingMethods(Request $request)
    {
        if ($error = $this->prepare($request)) {
            return $error;
        }
        if (!gp247_config('use_shipping')) {
            return response()->json(['data' => ['required' => false, 'rates' => [], 'unquotable' => [], 'selected' => null]]);
        }
        if (!$this->state()->shippingAddress()) {
            return ApiErrorResponder::respond(422, 'address_required', 'Set the delivery address first');
        }
        $rateRequest = $this->rateRequest();
        $answer = RateService::instance()->quote($rateRequest);
        $chosen = ShippingCheckout::snapshot();

        return response()->json(['data' => [
            'required'   => true,
            'rates'      => array_values(array_map(fn ($rate) => $this->rate($rate->toArray()), $answer['rates'])),
            'unquotable' => array_keys((array) $answer['errors']),
            'selected'   => $chosen !== null && $chosen->matches($rateRequest) ? $chosen->toArray()['rate_id'] : null,
        ]]);
    }

    /**
     * Choose a delivery option among the quoted ones; the price is the quoted one, kept for the order.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function selectShipping(Request $request)
    {
        if ($error = $this->prepare($request)) {
            return $error;
        }
        $request->validate(['rate_id' => 'required|string|max:120']);
        if (!gp247_config('use_shipping') || !$this->state()->shippingAddress()) {
            return $this->unavailable();
        }
        $rateRequest = $this->rateRequest();
        $snapshot = RateService::instance()->find($rateRequest, (string) $request->input('rate_id'));
        if ($snapshot === null) {
            return $this->unavailable();
        }
        ShippingCheckout::remember($snapshot, $rateRequest);
        $this->state()->setShippingMethod($snapshot->toArray()['plugin_key']);

        return $this->summary();
    }

    /**
     * Payment methods usable through the API: those without a payment step (cash on
     * delivery, transfer…) and gateways that can hand out a hosted payment page. A method
     * that only has a website payment step is left out.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentMethods(Request $request)
    {
        if ($error = $this->prepare($request)) {
            return $error;
        }
        $methods = gp247_config('use_payment') ? array_values($this->apiPaymentMethods()) : [];

        return response()->json(['data' => [
            'required' => (bool) gp247_config('use_payment'),
            'methods'  => $methods,
            'selected' => $this->state()->paymentMethod(),
        ]]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function selectPayment(Request $request)
    {
        if ($error = $this->prepare($request)) {
            return $error;
        }
        $request->validate(['key' => 'required|string|max:100']);
        $key = (string) $request->input('key');
        if (!gp247_config('use_payment') || !array_key_exists($key, $this->apiPaymentMethods())) {
            return $this->unavailable();
        }
        $this->state()->setPaymentMethod($key);

        return $this->summary();
    }

    /**
     * Totals the customer will be charged; kept as the figures shown (compared again when ordering).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function totals(Request $request)
    {
        return $this->prepare($request) ?? response()->json(['data' => $this->totalsData()]);
    }

    /**
     * Apply a promotion (coupon, points…) through the shared promotion contract.
     *
     * @param Request $request
     * @param string  $pluginKey
     * @return JsonResponse
     */
    public function applyPromotion(Request $request, $pluginKey)
    {
        if ($error = $this->prepare($request)) {
            return $error;
        }
        $plugin = $this->service()->totalPlugin((string) $pluginKey);
        if ($plugin === null) {
            return ApiErrorResponder::notFound();
        }
        $result = $plugin->checkoutApply((array) gp247_clean(data: (array) $request->input('payload', [])));
        if (!empty($result['error'])) {
            return ApiErrorResponder::respond(422, 'promotion_refused', (string) ($result['msg'] ?? 'Promotion refused'));
        }

        return response()->json(['data' => ['result' => ['msg' => (string) ($result['msg'] ?? '')], 'totals' => $this->totalsData()]]);
    }

    /**
     * @param Request $request
     * @param string  $pluginKey
     * @return JsonResponse
     */
    public function removePromotion(Request $request, $pluginKey)
    {
        if ($error = $this->prepare($request)) {
            return $error;
        }
        $plugin = $this->service()->totalPlugin((string) $pluginKey);
        if ($plugin === null) {
            return ApiErrorResponder::notFound();
        }
        $plugin->checkoutRemove();

        return response()->json(['data' => ['totals' => $this->totalsData()]]);
    }

    /**
     * Place the order of the store being checked out, once per Idempotency-Key: a resend gives
     * back the order already placed. The order goes through the website's checkout code
     * (CheckoutService) and is completed right away — the ordered lines leave the cart, the
     * order-success event and mail run (ADR api-commerce_hosted-payment-via-payment-request).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function place(Request $request)
    {
        $key = (string) $request->header(IdempotencyKey::HEADER, '');
        if (!IdempotencyKey::isWellFormed($key)) {
            return ApiErrorResponder::respond(400, 'idempotency_key_required', 'Send an Idempotency-Key header (8 to 255 printable characters)');
        }
        $owner = $request->attributes->get(CheckoutSessionBridge::OWNER_ATTRIBUTE);
        $token = $owner ? IdempotencyKey::checkoutToken($owner, $key) : null;
        if ($token !== null && ($placed = ShopOrder::where('checkout_token', $token)->first())) {
            return $this->replay($placed, $request);
        }
        if ($error = $this->prepare($request)) {
            return $error;
        }
        if ($error = $this->readyToPlace()) {
            return $error;
        }

        $state = $this->state();
        $state->setToken((string) $token);
        $customer = customer()->user();
        $result = $this->service()->placeOrder(new CheckoutContext(
            customer: $customer,
            customerId: $customer->id ?? 0,
            ip: $request->ip(),
            userAgent: $request->header('User-Agent'),
            deviceType: (string) (new \Jenssegers\Agent\Agent())->deviceType(),
            hasInput: true,
            channel: 'api',
        ));

        return match ($result->status) {
            PlaceOrderResult::PLACED => $this->placed($result->orderId, $request),
            PlaceOrderResult::REPLAYED => $this->replay(ShopOrder::findOrFail($result->orderId), $request),
            PlaceOrderResult::PRICE_CHANGED => response()->json(['error' => 1, 'code' => 'price_changed',
                'msg' => 'The price changed since the totals were shown; show the new totals and place the order again',
                'totals' => $this->totalsPayload((array) $state->totals())], 409),
            PlaceOrderResult::LOGIN_REQUIRED => ApiErrorResponder::respond(401, 'login_required', 'Sign in to order'),
            PlaceOrderResult::CREATE_FAILED => ApiErrorResponder::respond(422, 'create_failed', $result->message),
            default => ApiErrorResponder::respond(422, $result->status, 'The order cannot be placed'),
        };
    }

    /**
     * Refusal when a step the shop needs is missing, null when the order can be placed.
     *
     * @return JsonResponse|null
     */
    private function readyToPlace(): ?JsonResponse
    {
        $state = $this->state();
        if (!$state->shippingAddress()) {
            return ApiErrorResponder::respond(422, 'address_required', 'Set the delivery address first');
        }
        if (gp247_config('use_shipping') && !$state->shippingMethod()) {
            return ApiErrorResponder::respond(422, 'shipping_method_required', 'Choose a delivery option first');
        }
        if (gp247_config('use_payment')) {
            if (!$state->paymentMethod()) {
                return ApiErrorResponder::respond(422, 'payment_method_required', 'Choose a payment method first');
            }
            if (!array_key_exists((string) $state->paymentMethod(), $this->apiPaymentMethods())) {
                return $this->unavailable();
            }
        }
        $short = $this->service()->minimumShortfall($this->service()->qtyByProduct($state->lines() ?? []));
        if ($short !== []) {
            return response()->json(['error' => 1, 'code' => 'minimum_not_met', 'msg' => 'Some products are below their minimum quantity',
                'minimums' => array_map(fn ($min) => (float) $min, $short)], 422);
        }

        return null;
    }

    /**
     * Complete a newly placed order and answer with it.
     *
     * @param mixed   $orderId
     * @param Request $request
     * @return JsonResponse
     */
    private function placed($orderId, Request $request)
    {
        $this->service()->complete();
        $response = response()->json(['data' => $this->orderPayload(ShopOrder::findOrFail($orderId), $request)], 201);
        // WHY here: placing is when stock is taken, so it is when stale unpaid holds are let go (no cron).
        app(UnpaidApiOrders::class)->expireLazily();

        return $response;
    }

    /**
     * Answer a resend with the order already placed for this key — unless the request names another store.
     *
     * @param ShopOrder $order
     * @param Request   $request
     * @return JsonResponse
     */
    private function replay(ShopOrder $order, Request $request)
    {
        if ($request->filled('store_id') && (string) $request->input('store_id') !== (string) $order->store_id) {
            return ApiErrorResponder::respond(409, 'idempotency_key_reused', 'This Idempotency-Key was used for an order of another store');
        }

        return response()->json(['data' => $this->orderPayload($order, $request)]);
    }

    /**
     * The order, how to pay it, and — for a guest — the token that reads its payment status.
     *
     * @param ShopOrder $order
     * @param Request   $request
     * @return array<string, mixed>
     */
    private function orderPayload(ShopOrder $order, Request $request): array
    {
        $returnUrl = $request->input('return_url');
        $payload = [
            'order' => [
                'id'             => (string) $order->id,
                'store_id'       => (string) $order->store_id,
                'status'         => (int) $order->status,
                'payment_status' => (int) $order->payment_status,
                'total'          => (float) $order->total,
                'balance'        => (float) $order->balance,
                'currency'       => (string) $order->currency,
            ],
        ];
        $owner = $request->attributes->get(CheckoutSessionBridge::OWNER_ATTRIBUTE);
        if ($owner !== null && $owner->isGuest()) {
            $payload['access_token'] = OrderAccessToken::issue((string) $order->id);
        }
        $payload['payment'] = app(ApiOrderPayment::class)->forOrder($order, is_string($returnUrl) ? $returnUrl : null);

        return $payload;
    }

    /**
     * Put the store being checked out and its cart lines in the checkout state; null when
     * ready, else the refusal. The store is `store_id`, else the one already chosen, else
     * the only store in the cart. Choosing another store starts its choices afresh.
     *
     * @param Request $request
     * @return JsonResponse|null
     */
    private function prepare(Request $request): ?JsonResponse
    {
        $groups = (new CartService())->instance(CartService::DEFAULT_INSTANCE)->getItemsGroupByStore();
        $state = $this->state();
        $storeId = (string) ($request->input('store_id') ?? $state->storeId() ?? '');
        if ($storeId === '' && $groups->count() === 1) {
            $storeId = (string) $groups->keys()->first();
        }
        if ($groups->isEmpty() || ($storeId !== '' && empty($groups[$storeId]))) {
            return ApiErrorResponder::respond(422, 'cart_empty', 'There is nothing to check out in the cart');
        }
        if ($storeId === '') {
            return ApiErrorResponder::respond(422, 'store_required', 'The cart holds several stores; send store_id');
        }
        if ($state->storeId() !== null && (string) $state->storeId() !== $storeId) {
            $this->service()->clear();
        }
        $state->setStoreId($storeId);
        // WHY a plain array: code that reads the lines back casts them with (array).
        $state->setLines($groups[$storeId]->all());
        if ($this->service()->hasInvalidQuantity($groups[$storeId])) {
            return ApiErrorResponder::respond(422, 'invalid_quantity', 'A line of the cart has a quantity that cannot be ordered');
        }

        return null;
    }

    /**
     * @return JsonResponse
     */
    private function summary()
    {
        $state = $this->state();
        $snapshot = ShippingCheckout::snapshot();
        $shipping = $state->shippingMethod() === null ? null
            : ($snapshot !== null ? $this->rate($snapshot->toArray()) : ['plugin_key' => (string) $state->shippingMethod()]);

        return response()->json(['data' => [
            'store_id'        => (string) $state->storeId(),
            'address'         => $state->shippingAddress(),
            'save_address'    => $state->addressProcess() === 'new',
            'shipping_method' => $shipping,
            'payment_method'  => $state->paymentMethod(),
            'promotions'      => array_keys($state->totalMethods()),
        ]]);
    }

    /**
     * Totals rows, recomputed and kept as the figures shown.
     *
     * @return array<string, mixed>
     */
    private function totalsData(): array
    {
        return $this->totalsPayload((array) $this->service()->recomputeTotals());
    }

    /**
     * Public shape of totals rows.
     *
     * @param array $rows Rows as built by ShopOrderTotal::processDataTotal().
     * @return array<string, mixed>
     */
    private function totalsPayload(array $rows): array
    {
        $lines = array_map(fn ($row) => [
            'code'  => (string) ($row['code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'value' => (float) ($row['value'] ?? 0),
            'text'  => (string) ($row['text'] ?? ''),
        ], array_values($rows));

        return ['store_id' => (string) $this->state()->storeId(), 'lines' => $lines, 'currency' => gp247_currency_code()];
    }

    /**
     * Payment methods usable through the API, keyed by plugin key.
     *
     * @return array<string, array<string, mixed>>
     */
    private function apiPaymentMethods(): array
    {
        $gateways = (array) config('gp247-config.payment.gateways', []);
        $methods = [];
        foreach (array_keys(gp247_extension_get_via_code(code: 'payment')) as $key) {
            $namespace = gp247_extension_get_namespace(type: 'Plugins', key: $key);
            if (in_array('collect', (array) ($gateways[$key]['capabilities'] ?? []), true)) {
                $type = 'hosted';
            } elseif (method_exists($namespace . '\Controllers\FrontController', 'processOrder')) {
                continue;
            } else {
                $type = 'none';
            }
            $class = $namespace . '\AppConfig';
            $info = class_exists($class) ? (array) (new $class)->getInfo() : [];
            $methods[$key] = [
                'key'   => $key,
                'title' => (string) ($info['title'] ?? $key),
                'note'  => is_string($info['note'] ?? null) ? $info['note'] : '',
                'type'  => $type,
            ];
        }

        return $methods;
    }

    /**
     * @return \GP247\Shop\Shipping\Data\RateRequest The parcel of the checkout address and lines.
     */
    private function rateRequest()
    {
        $state = $this->state();

        return RateRequestFactory::fromCheckout((array) $state->shippingAddress(), collect($state->lines() ?? [])->all(),
            ShippingCheckout::storeId(), (string) ($state->paymentMethod() ?? ''));
    }

    /**
     * Public shape of a quoted rate.
     *
     * @param array<string, mixed> $rate RateSnapshot::toArray()
     * @return array<string, mixed>
     */
    private function rate(array $rate): array
    {
        return [
            'id'            => (string) $rate['rate_id'],
            'plugin_key'    => (string) $rate['plugin_key'],
            'service_code'  => (string) $rate['service_code'],
            'title'         => (string) $rate['title'],
            'amount'        => (float) $rate['display_amount'],
            'currency'      => (string) $rate['display_currency'],
            'eta_min_days'  => $rate['eta_min_days'],
            'eta_max_days'  => $rate['eta_max_days'],
        ];
    }

    /**
     * Fields of a saved address of the customer (email and company come from the customer).
     *
     * @param mixed $saved
     * @param mixed $customer
     * @return array<string, mixed>
     */
    private function savedAddress($saved, $customer): array
    {
        $fields = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $fields[$field] = $saved->{$field} ?? '';
        }
        $fields['email'] = $customer->email;
        $fields['company'] = $customer->company;

        return $fields;
    }

    /**
     * @return JsonResponse
     */
    private function unavailable()
    {
        return ApiErrorResponder::respond(422, 'method_unavailable', 'This method is not available for this checkout');
    }

    /**
     * @return CheckoutState
     */
    private function state(): CheckoutState
    {
        return app(CheckoutState::class);
    }

    /**
     * @return CheckoutService
     */
    private function service(): CheckoutService
    {
        return app(CheckoutService::class);
    }
}
