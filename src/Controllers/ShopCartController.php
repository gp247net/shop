<?php
namespace GP247\Shop\Controllers;

use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Core\Models\AdminCountry;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderTotal;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopCustomer;
use GP247\Shop\Models\ShopCustomerAddress;
use GP247\Shop\Services\CartService as Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopCartController extends RootFrontController
{
    const ORDER_STATUS_NEW = 1;
    const PAYMENT_UNPAID   = 1;
    const SHIPPING_NOTSEND = 1;

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
                'layout_page'     => 'shop_cart',
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

        //Check minimum
        $arrCheckQty = [];
        $cart = $cartGroup[$storeId];
        foreach ($cart as $key => $row) {
            //Qty get from input
            $qtyUpdate = (int)$data['qty-'.$row->rowId];
            //Update cart
            (new Cart)->update($row->rowId, $qtyUpdate);

            $arrCheckQty[$row->id] = ($arrCheckQty[$row->rowId] ?? 0) + ($data['qty-'.$row->rowId] ?? 0);
        }
        $arrProductMinimum = ShopProduct::whereIn('id', array_keys($arrCheckQty))->pluck('minimum', 'id')->all();
        $arrErrorQty = [];
        foreach ($arrProductMinimum as $pId => $min) {
            if ($arrCheckQty[$pId] < $min) {
                $arrErrorQty[$pId] = $min;
            }
        }
        if (count($arrErrorQty)) {
            return redirect(gp247_route_front('cart'))->with(['arrErrorQty' => $arrErrorQty, 'error'=> gp247_language_render('cart.have_error')]);
        }
        //End check minimum

        //Set session
        session(['storeCheckout' => $storeId]);

        return redirect(gp247_route_front('checkout'));
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
        $storeCheckout = session('storeCheckout') ?? '';
        if (!$storeCheckout) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'storeCheckout'])]);
        }
        $cartGroup = (new Cart)->getItemsGroupByStore();
        $dataCheckout = $cartGroup[$storeCheckout] ?? [];

        //If cart info empty
        if (!$dataCheckout) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.cart_empty')]);
        }
        //Set session dataCheckout
        session(['dataCheckout' => $dataCheckout]);

        //Shipping
        $moduleShipping = gp247_extension_get_via_code(code: 'shipping');
        $sourcesShipping = gp247_extension_get_all_local(type: 'Plugins');
        $shippingMethod = [];
        foreach ($moduleShipping as $module) {
            if (array_key_exists($module['key'], $sourcesShipping)) {
                $moduleClass = gp247_extension_get_namespace(type:'Plugins', key: $module['key']);
                $moduleClass = $moduleClass . '\AppConfig';
                if (class_exists($moduleClass)) {
                    // Check function processData   
                    if (method_exists($moduleClass, 'getInfo')) {
                        $shippingMethod[$module['key']] = (new $moduleClass)->getInfo();
                    }
                }
            }
        }

        //Payment
        $modulePayment = gp247_extension_get_via_code(code: 'payment');
        $sourcesPayment = gp247_extension_get_all_local(type: 'Plugins');
        $paymentMethod = array();
        foreach ($modulePayment as $module) {
            if (array_key_exists($module['key'], $sourcesPayment)) {
                $moduleClass = $sourcesPayment[$module['key']].'\AppConfig';
                $paymentMethod[$module['key']] = (new $moduleClass)->getInfo();
            }
        }

        //Total
        $moduleTotal = gp247_extension_get_via_code(code: 'total');
        $sourcesTotal = gp247_extension_get_all_local(type: 'Plugins');
        $totalMethod = array();
        foreach ($moduleTotal as $module) {
            if (array_key_exists($module['key'], $sourcesTotal)) {
                $moduleClass = $sourcesTotal[$module['key']].'\AppConfig';
                $totalMethod[$module['key']] = (new $moduleClass)->getInfo();
            }
        }

        // Shipping address
        $customer = customer()->user();
        if ($customer) {
            $address = $customer->getAddressDefault();
            if ($address) {
                $addressDefaul = [
                    'first_name'      => $address->first_name,
                    'last_name'       => $address->last_name,
                    'first_name_kana' => $address->first_name_kana,
                    'last_name_kana'  => $address->last_name_kana,
                    'email'           => $customer->email,
                    'address1'        => $address->address1,
                    'address2'        => $address->address2,
                    'address3'        => $address->address3,
                    'postcode'        => $address->postcode,
                    'company'         => $customer->company,
                    'country'         => $address->country,
                    'phone'           => $address->phone,
                    'comment'         => '',
                ];
            } else {
                $addressDefaul = [
                    'first_name'      => $customer->first_name,
                    'last_name'       => $customer->last_name,
                    'first_name_kana' => $customer->first_name_kana,
                    'last_name_kana'  => $customer->last_name_kana,
                    'email'           => $customer->email,
                    'address1'        => $customer->address1,
                    'address2'        => $customer->address2,
                    'address3'        => $customer->address3,
                    'postcode'        => $customer->postcode,
                    'company'         => $customer->company,
                    'country'         => $customer->country,
                    'phone'           => $customer->phone,
                    'comment'         => '',
                ];
            }
        } else {
            $addressDefaul = [
                'first_name'      => '',
                'last_name'       => '',
                'first_name_kana' => '',
                'last_name_kana'  => '',
                'postcode'        => '',
                'company'         => '',
                'email'           => '',
                'address1'        => '',
                'address2'        => '',
                'address3'        => '',
                'country'         => '',
                'phone'           => '',
                'comment'         => '',
            ];
        }
        $shippingAddress = session('shippingAddress') ?? $addressDefaul;
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
                'layout_page'     => 'shop_checkout',
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
        $dataCheckout  = session('dataCheckout') ?? '';
        $storeCheckout = session('storeCheckout') ?? '';
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
            session(['shippingMethod' => request('shippingMethod')]);
        }

        //Set session paymentMethod
        if (gp247_config('use_payment')) {
            session(['paymentMethod' => request('paymentMethod')]);
        }

        //Set session address process
        session(['address_process' => request('address_process')]);
        
        //Set session shippingAddressshippingAddress
        session(
            [
                'shippingAddress' => [
                    'first_name'      => request('first_name'),
                    'last_name'       => request('last_name'),
                    'first_name_kana' => request('first_name_kana'),
                    'last_name_kana'  => request('last_name_kana'),
                    'email'           => request('email'),
                    'country'         => request('country'),
                    'address1'        => request('address1'),
                    'address2'        => request('address2'),
                    'address3'        => request('address3'),
                    'phone'           => request('phone'),
                    'postcode'        => request('postcode'),
                    'company'         => request('company'),
                    'comment'         => request('comment'),
                ],
            ]
        );

        //Check minimum
        $arrCheckQty = [];
        $cart = $dataCheckout;
        foreach ($cart as $key => $row) {
            $arrCheckQty[$row->id] = ($arrCheckQty[$row->id] ?? 0) + $row->qty;
        }
        $arrProductMinimum = ShopProduct::whereIn('id', array_keys($arrCheckQty))->pluck('minimum', 'id')->all();
        $arrErrorQty = [];
        foreach ($arrProductMinimum as $pId => $min) {
            if ($arrCheckQty[$pId] < $min) {
                $arrErrorQty[$pId] = $min;
            }
        }
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
            !session('shippingAddress')
        ) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'shippingAddress'])]);
        }
        $shippingAddress = session('shippingAddress');


        //Shipping method
        $shippingMethodData  =  null;
        if (gp247_config('use_shipping')) {
            if (!session('shippingMethod')) {
                return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'shippingMethod'])]);
            }

            if (!gp247_config(session('shippingMethod'))) {
                return redirect(gp247_route_front('cart'))->with(['error' => 'Plugin shipping invalid!']);
            }

            $shippingMethod = session('shippingMethod');
            $classShippingMethod = gp247_extension_get_namespace(type: 'Plugins', key: $shippingMethod);
            $classShippingMethod = $classShippingMethod . '\AppConfig';
            if (class_exists($classShippingMethod)) {
                $shippingMethodData = (new $classShippingMethod)->getInfo();
            }
        }

        //Payment method
        $paymentMethodData = null;
        if (gp247_config('use_payment')) {
            if (!session('paymentMethod')) {
                return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_notfound', ['item' => 'paymentMethod'])]);
            }
            
            if (!gp247_config(session('paymentMethod'))) {
                return redirect(gp247_route_front('cart'))->with(['error' => 'Plugin payment invalid!']);
            }
            
            $paymentMethod = session('paymentMethod');
            $classPaymentMethod = gp247_extension_get_namespace(type: 'Plugins', key: $paymentMethod);
            $classPaymentMethod = $classPaymentMethod . '\AppConfig';
            if (class_exists($classPaymentMethod)) {
                $paymentMethodData = (new $classPaymentMethod)->getInfo();
            }
        }

        
        //Screen confirm only active if submit from screen checkout
        if (session('step', '') != 'checkout.confirm') {
            return redirect(gp247_route_front('checkout'));
        }

        $objects = ShopOrderTotal::getObjectOrderTotal();
        $dataTotal = ShopOrderTotal::processDataTotal($objects);

        //Set session dataTotal
        session(['dataTotal' => $dataTotal]);

        $subPath = 'screen.shop_checkout_confirm';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            [
                'title'              => gp247_language_render('checkout.page_title'),
                'cartItem'           => session('dataCheckout'),
                'dataTotal'          => $dataTotal,
                'paymentMethodData'  => $paymentMethodData,
                'shippingMethodData' => $shippingMethodData,
                'shippingAddress'    => $shippingAddress,
                'attributesGroup'    => ShopAttributeGroup::getListAll(),
                'layout_page'        => 'shop_checkout_confirm',
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
        $agent = new \Jenssegers\Agent\Agent();
        $customer = customer()->user();
        $uID = $customer->id ?? 0;

        //if cart empty
        if (count(session('dataCheckout', collect([]))) == 0) {
            return redirect()->route('front.home')->with(['error' => gp247_language_render('cart.item_empty', ['item' => 'dataCheckout'])]);
        }
        //Not allow for guest
        if (!gp247_config('shop_allow_guest') && !$customer) {
            return redirect(gp247_route_front('customer.login'));
        } //

        $data = request()->all();
        if (!$data) {
            return redirect(gp247_route_front('cart'))->with(['error' => gp247_language_render('cart.item_empty', ['item' => 'data'])]);
        } else {
            $dataTotal       = session('dataTotal') ?? [];
            $shippingAddress = session('shippingAddress') ?? [];
            $paymentMethod   = session('paymentMethod') ?? '';
            $shippingMethod  = session('shippingMethod') ?? '';
            $address_process = session('address_process') ?? '';
            $storeCheckout   = session('storeCheckout') ?? '';
            $dataCheckout    = session('dataCheckout') ?? '';
        }

        //Process total
        $subtotal = (new ShopOrderTotal)->sumValueTotal('subtotal', $dataTotal); //sum total
        $tax      = (new ShopOrderTotal)->sumValueTotal('tax', $dataTotal); //sum tax
        $shipping = (new ShopOrderTotal)->sumValueTotal('shipping', $dataTotal); //sum shipping
        $discount = (new ShopOrderTotal)->sumValueTotal('discount', $dataTotal); //sum discount
        $otherFee = (new ShopOrderTotal)->sumValueTotal('other_fee', $dataTotal); //sum other_fee
        $received = (new ShopOrderTotal)->sumValueTotal('received', $dataTotal); //sum received
        $total    = (new ShopOrderTotal)->sumValueTotal('total', $dataTotal);
        //end total

        $dataOrder['store_id']        = $storeCheckout;
        $dataOrder['customer_id']     = $uID;
        $dataOrder['subtotal']        = $subtotal;
        $dataOrder['shipping']        = $shipping;
        $dataOrder['discount']        = $discount;
        $dataOrder['other_fee']        = $otherFee;
        $dataOrder['received']        = $received;
        $dataOrder['tax']             = $tax;
        $dataOrder['payment_status']  = self::PAYMENT_UNPAID;
        $dataOrder['shipping_status'] = self::SHIPPING_NOTSEND;
        $dataOrder['status']          = self::ORDER_STATUS_NEW;
        $dataOrder['currency']        = gp247_currency_code();
        $dataOrder['exchange_rate']   = gp247_currency_rate();
        $dataOrder['total']           = $total;
        $dataOrder['balance']         = $total + $received;
        $dataOrder['email']           = $shippingAddress['email'];
        $dataOrder['first_name']      = $shippingAddress['first_name'];
        $dataOrder['payment_method']  = $paymentMethod;
        $dataOrder['shipping_method'] = $shippingMethod;
        $dataOrder['user_agent']      = $request->header('User-Agent');
        $dataOrder['device_type']      = $agent->deviceType();
        $dataOrder['ip']              = $request->ip();
        $dataOrder['created_at']      = gp247_time_now();

        if (!empty($shippingAddress['last_name'])) {
            $dataOrder['last_name']       = $shippingAddress['last_name'];
        }
        if (!empty($shippingAddress['first_name_kana'])) {
            $dataOrder['first_name_kana']       = $shippingAddress['first_name_kana'];
        }
        if (!empty($shippingAddress['last_name_kana'])) {
            $dataOrder['last_name_kana']       = $shippingAddress['last_name_kana'];
        }
        if (!empty($shippingAddress['address1'])) {
            $dataOrder['address1']       = $shippingAddress['address1'];
        }
        if (!empty($shippingAddress['address2'])) {
            $dataOrder['address2']       = $shippingAddress['address2'];
        }
        if (!empty($shippingAddress['address3'])) {
            $dataOrder['address3']       = $shippingAddress['address3'];
        }
        if (!empty($shippingAddress['country'])) {
            $dataOrder['country']       = $shippingAddress['country'];
        }
        if (!empty($shippingAddress['phone'])) {
            $dataOrder['phone']       = $shippingAddress['phone'];
        }
        if (!empty($shippingAddress['postcode'])) {
            $dataOrder['postcode']       = $shippingAddress['postcode'];
        }
        if (!empty($shippingAddress['company'])) {
            $dataOrder['company']       = $shippingAddress['company'];
        }
        if (!empty($shippingAddress['comment'])) {
            $dataOrder['comment']       = $shippingAddress['comment'];
        }

        $arrCartDetail = [];
        foreach ($dataCheckout as $cartItem) {
            $product = (new ShopProduct)->getDetail(key: $cartItem->id, type: 'id', storeId: $cartItem->storeId);
            if (!$product) {
                continue;
            }
            $arrDetail['product_id']  = $cartItem->id;
            $arrDetail['name']        = $cartItem->name;
            $arrDetail['price']       = gp247_currency_value($product->getFinalPrice());
            $arrDetail['qty']         = $cartItem->qty;
            $arrDetail['store_id']    = $cartItem->storeId;
            $arrDetail['attribute']   = ($cartItem->options) ? $cartItem->options : null;
            $arrDetail['total_price'] = gp247_currency_value($product->getFinalPrice()) * $cartItem->qty;
            $arrCartDetail[]          = $arrDetail;
        }
        //Create new order
        $newOrder = (new ShopOrder)->createOrder($dataOrder, $dataTotal, $arrCartDetail);

        if ($newOrder['error'] == 1) {
            gp247_report($newOrder['msg']);
            return redirect(gp247_route_front('cart'))->with(['error' => $newOrder['msg']]);
        }
        
        //Set session info order
        session(['dataOrder' => $dataOrder]);
        session(['arrCartDetail' => $arrCartDetail]);

        //Set session orderID
        session(['orderID' => $newOrder['orderID']]);

        //Create new address
        if ($address_process == 'new') {
            $addressNew = [
                'first_name'      => $shippingAddress['first_name'] ?? '',
                'last_name'       => $shippingAddress['last_name'] ?? '',
                'first_name_kana' => $shippingAddress['first_name_kana'] ?? '',
                'last_name_kana'  => $shippingAddress['last_name_kana'] ?? '',
                'postcode'        => $shippingAddress['postcode'] ?? '',
                'address1'        => $shippingAddress['address1'] ?? '',
                'address2'        => $shippingAddress['address2'] ?? '',
                'address3'        => $shippingAddress['address3'] ?? '',
                'country'         => $shippingAddress['country'] ?? '',
                'phone'           => $shippingAddress['phone'] ?? '',
            ];

            //Process escape
            $addressNew = gp247_clean($addressNew);

            ShopCustomer::find($uID)->addresses()->save(new ShopCustomerAddress($addressNew));
            session()->forget('address_process'); //destroy address_process
        }

        $paymentMethod = gp247_extension_get_namespace(type: 'Plugins', key: session('paymentMethod'));
        $paymentMethod = $paymentMethod . '\Controllers\FrontController';
        //Check class exist
        if (class_exists($paymentMethod) && method_exists($paymentMethod, 'processOrder')) {
            return (new $paymentMethod)->processOrder();
        }else{
            return (new ShopCartController)->completeOrder();
        }
    }

    /**
     * Add to cart by method post, always use in the product page detail
     *
     * @return [redirect]
     */
    public function addToCart()
    {
        $data      = request()->all();

        //Process escape
        $data      = gp247_clean($data);

        $productId = $data['product_id'];
        $qty       = $data['qty'] ?? 0;
        $storeId   = $data['storeId'] ?? config('app.storeId');

        $formAttr = $data['form_attr'] ?? [];

        $product = (new ShopProduct)->getDetail($productId, null, $storeId);

        if (!$product) {
            return redirect()->back()
                ->with(
                    ['error' => gp247_language_render('front.data_notfound')]
                );
        }
        

        if ($product->allowSale()) {
            $options = $formAttr;
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
     * Add product to cart
     * @param Request $request [description]
     * @return [json]
     */
    public function addToCartAjax(Request $request)
    {
        if (!$request->ajax()) {
            return redirect(gp247_route_front('cart'));
        }
        $data     = request()->all();
        $instance = $data['instance'] ?? 'default';
        $id       = $data['id'] ?? '';
        $storeId  = $data['storeId'] ?? config('app.storeId');
        $cart     = (new Cart)->instance($instance);

        $product = (new ShopProduct)->getDetail($id, null, $storeId);
        if (!$product) {
            return response()->json(
                [
                    'error' => 1,
                    'msg' => gp247_language_render('front.data_notfound'),
                ]
            );
        }
        switch ($instance) {
            case 'default':
                if ($product->attributes->count() || $product->kind == GP247_PRODUCT_GROUP) {
                    //Products have attributes or kind is group,
                    //need to select properties before adding to the cart
                    return response()->json(
                        [
                            'error' => 1,
                            'redirect' => $product->getUrl(),
                            'msg' => '',
                        ]
                    );
                }

                //Check product allow for sale
                if ($product->allowSale()) {
                    $cart->add(
                        array(
                            'id'      => $id,
                            'name'    => $product->name,
                            'qty'     => 1,
                            'storeId' => $storeId,
                        )
                    );
                } else {
                    return response()->json(
                        [
                            'error' => 1,
                            'msg' => gp247_language_render('product.dont_allow_sale', ['sku' => $product->sku]),
                        ]
                    );
                }
                break;

            default:
                //Wishlist or Compare...
                ${'arrID' . $instance} = array_keys($cart->content()->groupBy('id')->toArray());
                if (!in_array($id, ${'arrID' . $instance})) {
                    try {
                        $cart->add(
                            array(
                                'id'      => $id,
                                'name'    => $product->name,
                                'qty'     => 1,
                                'storeId' => $storeId,
                            )
                        );
                    } catch (\Throwable $e) {
                        return response()->json(
                            [
                                'error' => 1,
                                'msg' => $e->getMessage(),
                            ]
                        );
                    }
                } else {
                    return response()->json(
                        [
                            'error' => 1,
                            'msg' => gp247_language_render('cart.item_exist_in_cart', ['instance' => $instance]),
                        ]
                    );
                }
                break;
        }

        $carts = (new Cart)->getListCart($instance);
        return response()->json(
            [
                'error'      => 0,
                'count_cart' => $carts['count'],
                'instance'   => $instance,
                'msg'        => gp247_language_render('cart.add_to_cart_success', ['instance' => ($instance == 'default') ? 'cart' : $instance]),
            ]
        );
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
            (new Cart)->update($rowId, ($new_qty) ? $new_qty : 0);
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
                'layout_page' => 'shop_wishlist',
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
                'layout_page' => 'shop_compare',
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
        return redirect(gp247_route_front($instance));
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
            return redirect(gp247_route_front($instance));
        }
        if (array_key_exists($id, (new Cart)->instance($instance)->content()->toArray())) {
            (new Cart)->instance($instance)->remove($id);
        }
        return redirect(gp247_route_front($instance));
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
        //Clear cart store
        $this->clearCartStore();

        $orderID = session('orderID') ?? 0;
        $paymentMethod  = session('paymentMethod');
        $shippingMethod = session('shippingMethod');
        $totalMethod    = session('totalMethod', []);

        if ($orderID == 0) {
            return redirect()->route('front.home', ['error' => 'Error Order ID!']);
        }

        $classPaymentConfig = gp247_extension_get_namespace(type: 'Plugins', key: $paymentMethod);
        $classPaymentConfig = $classPaymentConfig . '\AppConfig';
        if (class_exists($classPaymentConfig)) {
            if (method_exists($classPaymentConfig, 'endApp')) {
                (new $classPaymentConfig)->endApp();
            }
        }

        $classShippingConfig = gp247_extension_get_namespace(type: 'Plugins', key: $shippingMethod);
        $classShippingConfig = $classShippingConfig . '\AppConfig';
        if (class_exists($classShippingConfig)) {
            if (method_exists($classShippingConfig, 'endApp')) {
                (new $classShippingConfig)->endApp();
            }
        }

        if ($totalMethod && is_array($totalMethod)) {
            foreach ($totalMethod as $keyMethod => $valueMethod) {
                $classTotalConfig = gp247_extension_get_namespace(type: 'Plugins', key: $keyMethod);
                $classTotalConfig = $classTotalConfig . '\AppConfig';
                if (class_exists($classTotalConfig)) {
                    if (method_exists($classTotalConfig, 'endApp')) {
                        (new $classTotalConfig)->endApp(['orderID' => $orderID, 'code' => $valueMethod]);
                    }
                }
            }
        }
        
        // Process event success
        gp247_event_order_success($order = ShopOrder::find($orderID));

        // Process after order compled: send mail, data response ...
        $this->processAfterOrderSuccess($orderID);

        return redirect(gp247_route_front('order.success'))->with(['orderID' => $orderID]);
    }

    /**
     * Cancel order
     */
    public function cancelOrder()
    {
        $orderID = session('orderID', null);
        \GP247\Shop\Models\ShopOrder::where('id', $orderID)->update(['status' => 4]);
        //Clear session
        $this->clearSession();
        return redirect()->route('front.home')->with('error', 'Payment cancelled');
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
        if (!session('orderID')) {
            return redirect()->route('front.home');
        }
        $orderInfo = ShopOrder::with('details')->find(session('orderID'))->toArray();
        $subPath = 'screen.shop_order_success';
        $view = gp247_shop_process_view($this->GP247TemplatePath,$subPath);
        gp247_check_view($view);
        return view(
            $view,
            [
                'title'       => gp247_language_render('checkout.success_title'),
                'orderInfo'   => $orderInfo,
                'layout_page' => 'shop_order_success',
                'breadcrumbs' => [
                    ['url'    => '', 'title' => gp247_language_render('checkout.success_title')],
                ],
            ]
        );
    }

    /**
     * Remove cart store ordered
     */
    private function clearCartStore()
    {
        $dataCheckout = session('dataCheckout') ?? '';
        if ($dataCheckout) {
            foreach ($dataCheckout as $key => $row) {
                (new Cart)->remove($row->rowId);
            }
        }
    }

    /**
     * Clear session
     */
    private function clearSession()
    {
        session()->forget('paymentMethod'); //destroy paymentMethod
        session()->forget('shippingMethod'); //destroy shippingMethod
        session()->forget('totalMethod'); //destroy totalMethod
        session()->forget('otherMethod'); //destroy otherMethod
        session()->forget('dataTotal'); //destroy dataTotal
        session()->forget('dataCheckout'); //destroy dataCheckout
        session()->forget('storeCheckout'); //destroy storeCheckout
        session()->forget('dataOrder'); //destroy dataOrder
        session()->forget('arrCartDetail'); //destroy arrCartDetail
        session()->forget('orderID'); //destroy orderID
    }

    /**
     * [processAfterOrderSuccess description]
     *
     * @param   string  $orderID  [$orderID description]
     *
     */
    private function processAfterOrderSuccess (string $orderID)
    {
        //Clear session
        $this->clearSession();
        gp247_order_process_after_success($orderID);
    }
}
