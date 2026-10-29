<?php
namespace GP247\Shop\Controllers;


use GP247\Shop\Support\ShopLayoutPage;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Core\Models\AdminCountry;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderTotal;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Services\CartService as Cart;
use GP247\Shop\Services\Checkout\CheckoutContext;
use GP247\Shop\Services\Checkout\CheckoutService;
use GP247\Shop\Services\Checkout\PlaceOrderResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopCartController extends RootFrontController
{
    const ORDER_STATUS_NEW = CheckoutService::ORDER_STATUS_NEW;
    const PAYMENT_UNPAID   = CheckoutService::PAYMENT_UNPAID;
    const SHIPPING_NOTSEND = CheckoutService::SHIPPING_NOTSEND;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process front get cart
     *
     * Step 01.1
     *
     * @param [type] ...$params
     * @return void
     */
    public function getCartFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_getCart();
    }

    /**
     * Get list cart: screen get cart
     * Step 01.2
     * @return [type] [description]
     */
    private function _getCart()
    {
        //Clear session
        $this->clearSession();
        
        $cart = (new Cart)->content();

        $subPath = 'screen.shop_cart';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            [
                'title'           => gp247_language_render('cart.cart_title'),
                'description'     => '',
                'keyword'         => '',
                'cart'            => $cart,
                'attributesGroup' => ShopAttributeGroup::pluck('name', 'id')->all(),
                'layout_page'     => ShopLayoutPage::Cart->value,
                'breadcrumbs'  => [
                    ['url' => '', 'title' => gp247_language_render('cart.cart_title')],
                ],
            ]
        );
    }


    /**
     * Prepare data checkout, receive data from page cart
     * Step 02
     */
    public function prepareCheckout()
    {
        $customer = customer()->user();

        //Not allow for guest
        if (!gp247_config('shop_allow_guest') && !$customer) {
            return redirect(gp247_route_front('customer.login'));
        }

        // Starting a checkout afresh from the cart drops any previous idempotency
        // token, so a genuinely new order gets a new token (the confirm step stamps
        // it) — US-LW-checkout-idempotency.
        $this->checkoutState()->forgetToken();
        // A new checkout starts without a previously quoted shipping rate (US-shop-shipping-rate-snapshot).
        \GP247\Shop\Shipping\ShippingCheckout::forget();

        $data = request()->all();

        $storeId = $data['store_id'] ?? 0;

        //If not exist store Id
        if (!$storeId) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'storeId'])]);
        }

        $cartGroup = (new Cart)->getItemsGroupByStore();

        //Check cart store empty
        if (empty($cartGroup[$storeId])) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_empty', ['item' => 'Cart Store'])]);
        }

        $cart = $cartGroup[$storeId];

        // WHY: validate every qty-* before touching the cart, so a tampered/bot
        // request neither 500s nor gets its junk read as "qty 0 = remove line"
        // (RISK-TECH-checkout-prepare-raw-qty).
        if ($this->hasMalformedCheckoutQty($cart, $data)) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.have_error')]);
        }

        //Check minimum
        $arrCheckQty = [];
        foreach ($cart as $key => $row) {
            //Qty get from input
            $qtyUpdate = round((float)($data['qty-'.$row->rowId] ?? 0), 2);
            //Update cart
            try {
                (new Cart)->update($row->rowId, $qtyUpdate);
            } catch (\InvalidArgumentException $e) {
                // WHY: CartService::update() now enforces the whole-number qty
                // rule (ADR-016) via CartItem::setQuantity() — surface it as a
                // friendly redirect instead of a 500.
                return redirect(gp247_route_front('cart'))
                    ->with(['error' => gp247_language_render('cart.qty_must_be_whole_number')]);
            }

            // WHY: the minimum applies per product, so lines of the same product
            // with different attributes are summed under the product id.
            $arrCheckQty[$row->id] = ($arrCheckQty[$row->id] ?? 0) + $qtyUpdate;
        }
        $arrErrorQty = $this->checkoutService()->minimumShortfall($arrCheckQty);
        if (count($arrErrorQty)) {
            return redirect(gp247_route_front('cart'))->with(['arrErrorQty' => $arrErrorQty, 'error'=> gp247_language_render('cart.have_error')]);
        }
        //End check minimum

        //Set session
        $this->checkoutState()->setStoreId($storeId);

        return redirect(gp247_route_front('checkout'));
    }


    /**
     * Tell whether any submitted qty-<rowId> field of the store's cart lines is not a plain number.
     *
     * A field that is absent keeps the historical meaning (qty 0); a field that is present must be a
     * numeric scalar. An empty input reaches here as null (ConvertEmptyStringsToNull) and is malformed.
     *
     * @param iterable $cart Cart lines of the store being checked out.
     * @param array    $data Request payload.
     * @return bool True when at least one present qty field is malformed.
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    private function hasMalformedCheckoutQty(iterable $cart, array $data): bool
    {
        foreach ($cart as $row) {
            $field = 'qty-'.$row->rowId;
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $qty = $data[$field];
            if (!is_scalar($qty) || !is_numeric($qty)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Process front checkout screen
     *
     * Step 03.1
     *
     * @param [type] ...$params
     * @return void
     */
    public function getCheckoutFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_getCheckout();
    }


    /**
     * Screen checkout
     *
     * Step 03.2
     *
     * @return [type] [description]
     */
    private function _getCheckout()
    {
        $storeCheckout = $this->checkoutState()->storeId() ?? '';
        if (!$storeCheckout) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'storeCheckout'])]);
        }
        $cartGroup = (new Cart)->getItemsGroupByStore();
        $dataCheckout = $cartGroup[$storeCheckout] ?? [];

        //If cart info empty
        if (!$dataCheckout) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.cart_empty')]);
        }
        // Lines are checked before the totals are: totals computed from a negative
        // line still "agree" with each other (US-LW-cart-qty-positive).
        $service = $this->checkoutService();
        if ($service->hasInvalidQuantity($dataCheckout)) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.have_error')]);
        }
        //Set session dataCheckout
        $this->checkoutState()->setLines($dataCheckout);

        $shippingMethod = $service->availableShippingMethods();
        $paymentMethod  = $service->availablePaymentMethods();
        //Total — dual-read Promotion ∪ legacy Total (mod 20260904T225634), shared seam.
        $totalMethod    = $service->availableTotalMethods();

        // Shipping address
        $customer = customer()->user();
        $shippingAddress = $this->checkoutState()->shippingAddress() ?? $service->defaultAddress($customer);
        $objects = ShopOrderTotal::getObjectOrderTotal();

        //Process captcha
        $viewCaptcha = gp247_captcha_processview('checkout', gp247_language_render('cart.checkout'));

        //Check view
        $subPath = 'screen.shop_checkout';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            [
                'title'           => gp247_language_render('cart.checkout'),
                'description'     => '',
                'keyword'         => '',
                'cartItem'        => $dataCheckout,
                'storeCheckout'   => $storeCheckout,
                'shippingMethod'  => $shippingMethod,
                'paymentMethod'   => $paymentMethod,
                'totalMethod'     => $totalMethod,
                'addressList'     => $customer ? $customer->addresses : [],
                'dataTotal'       => ShopOrderTotal::processDataTotal($objects),
                'shippingAddress' => $shippingAddress,
                'countries'       => AdminCountry::getCodeAll(),
                'attributesGroup' => ShopAttributeGroup::pluck('name', 'id')->all(),
                'viewCaptcha'     => $viewCaptcha,
                'layout_page'     => ShopLayoutPage::Checkout->value,
                'breadcrumbs'     => [
                    ['url'        => '', 'title' => gp247_language_render('cart.checkout')],
                ],
            ]
        );
    }


    /**
     * Checkout process, from screen checkout to checkout confirm
     *
     * Step 04
     *
     */
    public function processCheckout()
    {
        $dataCheckout  = $this->checkoutState()->lines() ?? '';
        $storeCheckout = $this->checkoutState()->storeId() ?? '';
        //If cart info empty
        if (!$dataCheckout || !$storeCheckout) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.cart_empty')]);
        }

        $customer = customer()->user();

        //Not allow for guest
        if (!gp247_config('shop_allow_guest') && !$customer) {
            return redirect(gp247_route_front('customer.login'));
        }

        $data = request()->all();

        $dataMap = gp247_order_mapping_validate();
        $validate = $dataMap['validate'];
        $messages = $dataMap['messages'];

        //Process captcha
        if (gp247_captcha_method() && in_array('checkout', gp247_captcha_page())) {
            $data['captcha_field'] = $data[gp247_captcha_method()->getField()] ?? '';
            $validate['captcha_field'] = ['required', 'string', new \GP247\Core\Rules\CaptchaRule];
        }

        $v = Validator::make(
            $data,
            $validate,
            $messages
        );

        if ($v->fails()) {
            return redirect()->back()
                ->withInput()
                ->withErrors($v->errors());
        }

        //Set session shippingMethod
        if (gp247_config('use_shipping')) {
            $this->checkoutState()->setShippingMethod(request('shippingMethod'));
        }

        //Set session paymentMethod
        if (gp247_config('use_payment')) {
            $this->checkoutState()->setPaymentMethod(request('paymentMethod'));
        }

        //Set session address process
        $this->checkoutState()->setAddressProcess(request('address_process'));
        
        //Set session shippingAddress
        $this->checkoutState()->setShippingAddress([
            'first_name'      => request('first_name'),
            'last_name'       => request('last_name'),
            'first_name_kana' => request('first_name_kana'),
            'last_name_kana'  => request('last_name_kana'),
            'email'           => request('email'),
            'country'         => request('country'),
            'city'            => request('city'),
            'district'        => request('district'),
            'ward'            => request('ward'),
            'address1'        => request('address1'),
            'address2'        => request('address2'),
            'address3'        => request('address3'),
            'phone'           => request('phone'),
            'postcode'        => request('postcode'),
            'company'         => request('company'),
            'comment'         => request('comment'),
        ]);

        //Check minimum
        $service = $this->checkoutService();
        $arrErrorQty = $service->minimumShortfall($service->qtyByProduct($dataCheckout));
        if (count($arrErrorQty)) {
            return redirect(gp247_route_front('cart'))->with(['arrErrorQty' => $arrErrorQty, 'error'=> gp247_language_render('cart.have_error')]);
        }
        //End check minimum

        return redirect(gp247_route_front('checkout.confirm'))->with('step', 'checkout.confirm');
    }



    /**
     * Process front checkout confirm screen
     *
     * Step 05.1
     *
     * @param [type] ...$params
     * @return void
     */
    public function getCheckoutConfirmFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_getCheckoutConfirm();
    }

    /**
     * Checkout screen
     *
     * Step 05.2
     *
     * @return [view]
     */
    private function _getCheckoutConfirm()
    {
        //Check shipping address
        if (
            !$this->checkoutState()->shippingAddress()
        ) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'shippingAddress'])]);
        }
        $shippingAddress = $this->checkoutState()->shippingAddress();


        //Shipping method
        $shippingMethodData  =  null;
        if (gp247_config('use_shipping')) {
            if (!$this->checkoutState()->shippingMethod()) {
                return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'shippingMethod'])]);
            }

            if (!gp247_config($this->checkoutState()->shippingMethod())) {
                return redirect(gp247_route_front('cart'))->with(['error' => 'Plugin shipping invalid!']);
            }

            $shippingMethod = $this->checkoutState()->shippingMethod();
            $classShippingMethod = gp247_extension_get_namespace(type: 'Plugins', key: $shippingMethod);
            $classShippingMethod = $classShippingMethod . '\AppConfig';
            if (class_exists($classShippingMethod)) {
                $shippingMethodData = (new $classShippingMethod)->getInfo();
            }
        }

        //Payment method
        $paymentMethodData = null;
        if (gp247_config('use_payment')) {
            if (!$this->checkoutState()->paymentMethod()) {
                return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'paymentMethod'])]);
            }
            
            if (!gp247_config($this->checkoutState()->paymentMethod())) {
                return redirect(gp247_route_front('cart'))->with(['error' => 'Plugin payment invalid!']);
            }
            
            $paymentMethod = $this->checkoutState()->paymentMethod();
            $classPaymentMethod = gp247_extension_get_namespace(type: 'Plugins', key: $paymentMethod);
            $classPaymentMethod = $classPaymentMethod . '\AppConfig';
            if (class_exists($classPaymentMethod)) {
                $paymentMethodData = (new $classPaymentMethod)->getInfo();
            }
        }

        
        //Screen confirm only active if submit from screen checkout
        if ($this->checkoutState()->step() != 'checkout.confirm') {
            return redirect(gp247_route_front('checkout'));
        }

        // Totals kept for addOrder() + one idempotency token per checkout session, so a
        // Back / refresh / double-click cannot create a second order. Rotated whenever the
        // cart or checkout is redone (see prepareCheckout / clearSession)
        // (US-LW-checkout-idempotency, ADR storefront_checkout-idempotency).
        $dataTotal = $this->checkoutService()->recomputeTotals();

        $subPath = 'screen.shop_checkout_confirm';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);

        // A template does not have to own a separate confirm SCREEN: GP247Front
        // renders confirm as step 4 of the CheckoutWizard Livewire component on the
        // checkout screen, so it ships no screen.shop_checkout_confirm at all
        // (the 1.x blade went away with the core 2.0 port, commit 398bb4d).
        // Without this branch gp247_check_view() below echoes a bare "view not
        // found" and exit()s, which is what a shopper actually hit: addOrder()'s
        // price-changed guard redirects here to re-confirm, so a price moving
        // mid-checkout ended the checkout on a blank page with no order placed
        // (RISK-BIZ-price-change-mid-checkout was meant to protect them).
        // Send them to the wizard instead; the step marker stays set (and is
        // re-persisted here because processCheckout() only FLASHES it) so
        // CheckoutWizard::mount() resumes on confirm with their answers intact.
        // reflash() carries the 'price changed, please review' notice across.
        if (!view()->exists($view)) {
            $this->checkoutState()->markConfirmStep();
            session()->reflash();

            return redirect(gp247_route_front('checkout'));
        }

        gp247_check_view($view);
        return view(
            $view,
            [
                'title'              => gp247_language_render('checkout.page_title'),
                'cartItem'           => $this->checkoutState()->lines(),
                'dataTotal'          => $dataTotal,
                'paymentMethodData'  => $paymentMethodData,
                'shippingMethodData' => $shippingMethodData,
                'shippingAddress'    => $shippingAddress,
                'attributesGroup'    => ShopAttributeGroup::getListAll(),
                'layout_page'        => ShopLayoutPage::CheckoutConfirm->value,
                'breadcrumbs'        => [
                    ['url'           => '', 'title' => gp247_language_render('checkout.page_title')],
                ],
            ]
        );
    }

    /**
     * Create new order
     *
     * Step 06
     *
     * @return [redirect]
     */
    public function addOrder(Request $request)
    {
        $result = $this->checkoutService()->placeOrder(CheckoutContext::fromRequest($request));
        if (!$result->isPlaced()) {
            return $this->placeOrderRedirect($result);
        }

        $paymentMethod = gp247_extension_get_namespace(type: 'Plugins', key: $this->checkoutState()->paymentMethod());
        $paymentMethod = $paymentMethod . '\Controllers\FrontController';
        //Check class exist
        if (class_exists($paymentMethod) && method_exists($paymentMethod, 'processOrder')) {
            return (new $paymentMethod)->processOrder();
        }else{
            return $this->completeOrder();
        }
    }

    /**
     * Redirect of a submit that placed no new order: the order already placed for this
     * checkout (replay — no second order, no second charge), or the page and message of
     * the refusal.
     *
     * @param PlaceOrderResult $result
     * @return \Illuminate\Http\RedirectResponse
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-checkout-idempotency
     */
    private function placeOrderRedirect(PlaceOrderResult $result)
    {
        return match ($result->status) {
            PlaceOrderResult::REPLAYED            => redirect(gp247_route_front('order.success'))->with(['orderID' => $result->orderId]),
            PlaceOrderResult::CART_EMPTY          => redirect(gp247_route_front('front.home'))->with(['error' => gp247_language_render('cart.item_empty', ['item' => 'dataCheckout'])]),
            PlaceOrderResult::LOGIN_REQUIRED      => redirect(gp247_route_front('customer.login')),
            PlaceOrderResult::TOKEN_MISSING       => redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'checkout'])]),
            PlaceOrderResult::INPUT_EMPTY         => redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_empty', ['item' => 'data'])]),
            PlaceOrderResult::INVALID_QUANTITY    => redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.have_error')]),
            PlaceOrderResult::SHIPPING_UNQUOTABLE => redirect(gp247_route_front('checkout'))->with(['error' => gp247_language_render('cart.shipping_rate_none')]),
            PlaceOrderResult::PRICE_CHANGED       => redirect(gp247_route_front('checkout.confirm'))->with(['warning' => gp247_language_render('cart.price_changed_review')]),
            default                               => redirect(gp247_route_front('cart'))->with(['error' => $result->message]),
        };
    }

    /**
     * Add to cart by method post, always use in the product page detail
     *
     * @return [redirect]
     */
    public function addToCart()
    {
        // WHY: qty was previously completely unvalidated here (relied on
        // CartItem::setQuantity()'s is_numeric check downstream) — now also
        // enforces product_qty_decimal (modification 20260705T093328, ADR-016)
        // with a friendly redirect instead of an uncaught exception.
        request()->validate(['qty' => 'required|' . gp247_qty_rule()]);

        $data      = request()->all();
        $data      = gp247_clean($data);

        $productId = $data['product_id'];
        $qty       = $data['qty'] ?? 0;

        $formAttr = $data['form_attr'] ?? [];

        // WHY the partner helper: the marketplace ships as MultiVendor (Free) or
        // MultiVendorPro (Pro unlock); either edition splits the cart per vendor
        // (ADR multi-vendor_free-pro-split). The helper already knows both keys.
        if (gp247_store_check_multi_partner_installed() && (config('app.storeId') == GP247_STORE_ID_ROOT)) {

            $product = (new ShopProduct)->getDetail($productId);
            if (!$product) {
                return redirect()->back()
                    ->with(
                        ['error' => gp247_language_render('front.data_notfound')]
                    );
            }
            // WHY: 1-1 ownership — the product's owning store is its scalar store_id.
            $storeId = $product->store_id;
        } else {
            $storeId = config('app.storeId');
            $product = (new ShopProduct)->getDetail($productId, null, $storeId);
            if (!$product) {
                return redirect()->back()
                    ->with(
                        ['error' => gp247_language_render('front.data_notfound')]
                    );
            }
        }


        if ($product->allowSale()) {

            if ($product->attributes->count() && !$formAttr) {
                // Product have attributes but not select attribute
                return redirect()->back()
                    ->with(
                        ['error' => gp247_language_render('product.please_select_attribute')]
                    );
            }

            // WHY: never trust the add_price the client puts in form_attr — rebuild each
            // option's surcharge from shop_product_attribute so the cart/checkout/order
            // pipeline is server-authoritative (RISK-TECH-attribute-price-tampering,
            // ADR storefront_attribute-price-integrity). A false result means the client
            // sent an attribute that does not belong to this product → reject the add.
            $options = gp247_cart_options_canonicalize($productId, $formAttr);
            if ($options === false) {
                return redirect()->back()
                    ->with(
                        ['error' => gp247_language_render('product.please_select_attribute')]
                    );
            }

            $dataCart = array(
                'id'      => $productId,
                'name'    => $product->name,
                'qty'     => $qty,
                'storeId' => $storeId,
            );
            $dataCart['options'] = $options;
            (new Cart)->add($dataCart);
            return redirect(gp247_route_front('cart'))
                ->with(
                    ['success' => gp247_language_render('cart.add_to_cart_success', ['instance' => 'cart'])]
                );
        } else {
            return redirect(gp247_route_front('cart'))
                ->with(
                    ['error' => gp247_language_render('product.dont_allow_sale', ['sku' => $product->sku])]
                );
        }
    }


    /**
     * Update product to cart
     * @param  Request $request [description]
     * @return [json]
     */
    public function updateToCart(Request $request)
    {
        if (!$request->ajax()) {
            return redirect(gp247_route_front('cart'));
        }
        $data    = request()->all();
        $id      = $data['id'] ?? '';
        $rowId   = $data['rowId'] ?? '';
        $new_qty = $data['new_qty'] ?? 0;
        $storeId = $data['storeId'] ?? config('app.storeId');
        $product = (new ShopProduct)->getDetail($id, null, $storeId);
        
        if (!$product) {
            return response()->json(
                [
                    'error' => 1,
                    'msg' => gp247_language_render('front.data_notfound'),
                ]
            );
        }
        
        if ($product->stock < $new_qty && !gp247_config('product_buy_out_of_stock', $product->store_id)) {
            return response()->json(
                [
                    'error' => 1,
                    'msg' => gp247_language_render('cart.item_over_qty', ['sku' => $product->sku, 'qty' => $new_qty]),
                ]
            );
        } else {
            try {
                (new Cart)->update($rowId, ($new_qty) ? $new_qty : 0);
            } catch (\InvalidArgumentException $e) {
                // WHY: CartService::update() now enforces the whole-number qty
                // rule (ADR-016) via CartItem::setQuantity() — surface it as a
                // friendly JSON error instead of a 500.
                return response()->json([
                    'error' => 1,
                    'msg' => gp247_language_render('cart.qty_must_be_whole_number'),
                ]);
            }
            return response()->json(
                [
                    'error' => 0,
                ]
            );
        }
    }

    /**
     * Process front wishlist
     *
     * @param [type] ...$params
     * @return void
     */
    public function wishlistProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_wishlist();
    }

    /**
     * Get product in wishlist
     * @return [view]
     */
    private function _wishlist()
    {
        $wishlist = (new Cart)->instance('wishlist')->content();
        $subPath = 'screen.shop_wishlist';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            array(
                'title'       => gp247_language_render('cart.page_wishlist_title'),
                'description' => '',
                'keyword'     => '',
                'wishlist'    => $wishlist,
                'layout_page' => ShopLayoutPage::Wishlist->value,
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('cart.page_wishlist_title')],
                ],
            )
        );
    }

    /**
     * Process front compare
     *
     * @param [type] ...$params
     * @return void
     */
    public function compareProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_compare();
    }

    /**
     * Get product in compare
     * @return [view]
     */
    private function _compare()
    {
        $compare = (new Cart)->instance('compare')->content();

        $subPath = 'screen.shop_compare';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            array(
                'title'       => gp247_language_render('cart.page_compare_title'),
                'description' => '',
                'keyword'     => '',
                'compare'     => $compare,
                'layout_page' => ShopLayoutPage::Compare->value,
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('cart.page_compare_title')],
                ],
            )
        );
    }


    /**
     * Process front compare
     *
     * @param [type] ...$params
     * @return void
     */
    public function clearCartProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            $instance = $params[1] ?? 'cart';
            gp247_lang_switch($lang);
        } else {
            $instance = $params[0] ?? 'cart';
        }
        return $this->_clearCart($instance);
    }


    /**
     * Clear all cart
     * @return [redirect]
     */
    private function _clearCart($instance = 'cart')
    {
        (new Cart)->instance($instance)->destroy();
        return redirect($this->listPageUrl($instance));
    }

    /**
     * URL of the page that lists a cart instance — where remove/clear send the shopper back.
     *
     * WHY a map and not gp247_route_front($instance): only the cart page is named after its
     * instance; wishlist/compare are cart.wishlist / cart.compare. An unknown name made the
     * helper fall back to the current URL — the remove/clear link itself — so the browser
     * looped until "too many redirects".
     *
     * @param string $instance 'cart'|'default'|'wishlist'|'compare'
     * @return string
     *
     * @aidlc-unit storefront
     * @aidlc-story US-LW-004
     */
    private function listPageUrl($instance): string
    {
        $pages = ['wishlist' => 'cart.wishlist', 'compare' => 'cart.compare'];

        return gp247_route_front($pages[$instance] ?? 'cart');
    }

    /**
     * Process front remove item cart
     *
     * @param [type] ...$params
     * @return void
     */
    public function removeItemProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            $instance = $params[1] ?? 'cart';
            $id = $params[2] ?? '';
            gp247_lang_switch($lang);
        } else {
            $instance = $params[0] ?? 'cart';
            $id = $params[1] ?? '';
        }
        return $this->_removeItem($instance, $id);
    }


    /**
     * Remove item from cart
     * @return [redirect]
     */
    private function _removeItem($instance = 'cart', $id = null)
    {
        if ($id === null) {
            return redirect($this->listPageUrl($instance));
        }
        if (array_key_exists($id, (new Cart)->instance($instance)->content()->toArray())) {
            (new Cart)->instance($instance)->remove($id);
        }
        return redirect($this->listPageUrl($instance));
    }

    
    /**
     * Complete order
     *
     * Step 07
     *
     * @return [redirect]
     */
    public function completeOrder()
    {
        $orderID = $this->checkoutService()->complete();
        if ($orderID === null) {
            return redirect(gp247_route_front('front.home', ['error' => 'Error Order ID!']));
        }

        return redirect(gp247_route_front('order.success'))->with(['orderID' => $orderID]);
    }

    /**
     * Cancel the in-flight order after the customer aborts at the gateway.
     *
     * WHY changeStatus() and not a raw update: the raw `status = 4` write this
     * used to do skipped restock, history, events and hooks entirely — the
     * goods of a gateway-cancelled order never came back to stock
     * (RISK-BIZ-cancel-path-bypass-restock). Entering Canceled is never
     * refused, so this path needs no failure branch.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-cancel-restock
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public function cancelOrder()
    {
        $this->checkoutService()->cancel();
        return redirect(gp247_route_front('front.home'))->with('error', 'Payment cancelled');
    }


    /**
     * Process front page order success
     *
     * Step 08.1
     *
     * @param [type] ...$params
     * @return void
     */
    public function orderSuccessProcessFront(...$params)
    {
        if (GP247_SEO_LANG) {
            $lang = $params[0] ?? '';
            gp247_lang_switch($lang);
        }
        return $this->_orderSuccess();
    }

    /**
     * Page order success
     *
     * Step 08.2
     *
     * @return  [view]
     */
    private function _orderSuccess()
    {
        if (!$this->checkoutState()->orderId()) {
            return redirect(gp247_route_front('front.home'));
        }
        $orderInfo = ShopOrder::with('details')->find($this->checkoutState()->orderId())->toArray();
        $subPath = 'screen.shop_order_success';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            [
                'title'       => gp247_language_render('checkout.success_title'),
                'orderInfo'   => $orderInfo,
                'layout_page' => ShopLayoutPage::OrderSuccess->value,
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('checkout.success_title')],
                ],
            ]
        );
    }

    /**
     * Checkout state of this request (the session on the web).
     *
     * @return \GP247\Shop\Services\Checkout\CheckoutState
     */
    private function checkoutState(): \GP247\Shop\Services\Checkout\CheckoutState
    {
        return app(\GP247\Shop\Services\Checkout\CheckoutState::class);
    }

    /**
     * Checkout rules working on this request's checkout state.
     *
     * @return CheckoutService
     */
    private function checkoutService(): CheckoutService
    {
        return app(CheckoutService::class);
    }

    /**
     * Clear session
     */
    private function clearSession()
    {
        // Methods, totals, lines, store, order data, the idempotency token (rotated — a new
        // checkout gets a fresh one) and the shipping rate snapshot + quotes; the address,
        // step and address_process stay.
        $this->checkoutService()->clear();
    }
}
