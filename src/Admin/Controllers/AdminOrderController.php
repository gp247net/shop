<?php
namespace GP247\Shop\Admin\Controllers;

use GP247\Core\Controllers\RootAdminController;
use GP247\Core\Models\AdminStore;
use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Core\Models\AdminCountry;
use GP247\Shop\Models\ShopCurrency;
use GP247\Shop\Models\ShopOrderDetail;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductAttribute;
use GP247\Shop\Models\ShopPaymentStatus;
use GP247\Shop\Models\ShopShippingStatus;
use GP247\Shop\Admin\Models\AdminCustomer;
use GP247\Shop\Admin\Models\AdminOrder;
use GP247\Shop\Admin\Models\AdminProduct;
use GP247\Shop\Models\ShopOrderTotal;
use Validator;

class AdminOrderController extends RootAdminController
{
    public $statusPayment;
    public $statusOrder;
    public $statusShipping;
    public $statusOrderMap;
    public $statusShippingMap;
    public $statusPaymentMap;
    public $currency;
    public $country;
    public $countryMap;

    public function __construct()
    {
        parent::__construct();
        $this->statusOrder    = ShopOrderStatus::getIdAll();
        $this->currency       = ShopCurrency::getListActive();
        $this->country        = AdminCountry::getCodeAll();
        $this->statusPayment  = ShopPaymentStatus::getIdAll();
        $this->statusShipping = ShopShippingStatus::getIdAll();
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        $data = [
            'title'         => gp247_language_render('admin.order.list'),
            'subTitle'      => '',
            'icon'          => 'fa fa-indent',
            'urlDeleteItem' => gp247_route_admin('admin_order.delete'),
            'removeList'    => 1, // 1 - Enable function delete list item
            'buttonRefresh' => 1, // 1 - Enable button refresh
            'css'           => '',
            'js'            => '',
        ];
        //Process add content
        $data['menuRight']    = gp247_config_group('menuRight', \Request::route()->getName());
        $data['menuLeft']     = gp247_config_group('menuLeft', \Request::route()->getName());
        $data['topMenuRight'] = gp247_config_group('topMenuRight', \Request::route()->getName());
        $data['topMenuLeft']  = gp247_config_group('topMenuLeft', \Request::route()->getName());
        $data['blockBottom']  = gp247_config_group('blockBottom', \Request::route()->getName());

        $listTh = [
            'email'          => gp247_language_render('order.email'),
            'first_name'     => gp247_language_render('order.first_name'),
            'subtotal'       => gp247_language_render('order.subtotal'),
            'shipping'       => gp247_language_render('order.shipping'),
            'discount'       => gp247_language_render('order.discount'),
            'tax'            => gp247_language_render('order.tax'),
            'total'          => gp247_language_render('order.total'),
            'payment_method' => gp247_language_render('admin.order.payment_method_short'),
            'payment_status' => gp247_language_render('order.payment_status'),
            'shipping_status'=> gp247_language_render('order.shipping_status'),
            'status'         => gp247_language_render('order.status'),
        ];
        if ((gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) && session('adminStoreId') == GP247_STORE_ID_ROOT) {
            // Only show store info if store is root
            $listTh['shop_store'] = '<i class="fab fa-shopify" aria-hidden="true" title="'.gp247_language_render('front.store_list').'"></i>';
        }
        $listTh['created_at'] = gp247_language_render('admin.created_at');
        $listTh['action'] = gp247_language_render('action.title');

        $keyword      = gp247_clean(request('keyword') ?? '');
        $from_to      = gp247_clean(request('from_to') ?? '');
        $end_to       = gp247_clean(request('end_to') ?? '');
        $order_status = gp247_clean(request('order_status') ?? '');
        $dataSearch = [
            'keyword'      => $keyword,
            'from_to'      => $from_to,
            'end_to'       => $end_to,
            'order_status' => $order_status,
        ];
        $dataTmp = (new AdminOrder)->getOrderListAdmin($dataSearch);
        if ((gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) && session('adminStoreId') == GP247_STORE_ID_ROOT) {
            $arrId = $dataTmp->pluck('id')->toArray();
            // Only show store info if store is root
            if (function_exists('gp247_get_list_store_of_order')) {
                $dataStores = gp247_get_list_store_of_order($arrId);
            } else {
                $dataStores = [];
            }
        }

        $styleStatus = $this->statusOrder;
        array_walk($styleStatus, function (&$v, $k) {
            $v = '<span class="badge badge-' . (AdminOrder::$mapStyleStatus[$k] ?? 'light') . '">' . $v . '</span>';
        });
        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $dataMap = [
                'email'          => $row['email'] ?? 'N/A',
                'first_name'     => $row['first_name'] ?? 'N/A',
                'subtotal'       => gp247_currency_render_symbol($row['subtotal'] ?? 0, $row['currency']),
                'shipping'       => gp247_currency_render_symbol($row['shipping'] ?? 0, $row['currency']),
                'discount'       => gp247_currency_render_symbol($row['discount'] ?? 0, $row['currency']),
                'tax'            => gp247_currency_render_symbol($row['tax'] ?? 0, $row['currency']),
                'total'          => gp247_currency_render_symbol($row['total'] ?? 0, $row['currency']),
                'payment_method' => ($row['payment_method'] ?? 'N/A').'('.$row['currency'] . '/' . $row['exchange_rate'].')',
                'payment_status' => $this->statusPayment[$row['payment_status']] ?? $row['payment_status'],
                'shipping_status'=> $this->statusShipping[$row['shipping_status']] ?? $row['shipping_status'],
                'status'         => $styleStatus[$row['status']] ?? $row['status'],
            ];
            if ((gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) && session('adminStoreId') == GP247_STORE_ID_ROOT) {
                // Only show store info if store is root
                if (!empty($dataStores[$row['id']])) {
                    $storeTmp = $dataStores[$row['id']]->pluck('code', 'id')->toArray();
                    $storeTmp = array_map(function ($code) {
                        if (is_null($code)) {
                            return ;
                        }
                        $domain = gp247_store_get_domain_from_code($code);
                        return '<a target=_new href="'.$domain.'">'.$code.'</a>';
                    }, $storeTmp);
                    $dataMap['shop_store'] = '<i class="nav-icon fab fa-shopify"></i> '.implode('<br><i class="nav-icon fab fa-shopify"></i> ', $storeTmp);
                } else {
                    $dataMap['shop_store'] = '';
                }
            }
            $dataMap['created_at'] = $row['created_at'];

            $arrAction = [
                '<a href="' . gp247_route_admin('admin_order.detail', ['id' => $row['id'] ? $row['id'] : 'not-found-id']) . '"  class="dropdown-item"><i class="fa fa-edit"></i> '.gp247_language_render('action.edit').'</a>',
                ];
            $arrAction[] = '<a href="#" onclick="deleteItem(\'' . $row['id'] . '\');"  title="' . gp247_language_render('action.delete') . '" class="dropdown-item"><i class="fas fa-trash-alt"></i> '.gp247_language_render('action.remove').'</a>';
            $action = $this->procesListAction($arrAction);
            $dataMap['action'] = $action;
            $dataTr[$row['id']] = $dataMap;
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token']))->links('gp247-admin::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);


        //menuRight
        $data['menuRight'][] = '<a href="' . gp247_route_admin('admin_order.create') . '" class="btn  btn-success  btn-flat" title="New" id="button_create_new">
                           <i class="fa fa-plus" title="'.gp247_language_render('action.add').'"></i>
                           </a>';
        //=menuRight

        //menuSearch
        $optionStatus = '';
        foreach ($this->statusOrder as $key => $status) {
            $optionStatus .= '<option  ' . (($order_status == $key) ? "selected" : "") . ' value="' . $key . '">' . $status . '</option>';
        }
        $data['topMenuRight'][] = '
                <form action="' . gp247_route_admin('admin_order.index') . '" id="button_search">
                    <div class="input-group float-left">

                        <div style="width:130px">
                            <div class="form-group">
                                <label>'.gp247_language_render('action.from').':</label>
                                <div class="input-group">
                                <input type="text" name="from_to" id="from_to" class="form-control input-sm date_time rounded-0" data-date-format="yyyy-mm-dd" placeholder="yyyy-mm-dd" /> 
                                </div>
                            </div>
                        </div> &nbsp;
                        <div style="width:130px">
                            <div class="form-group">
                                <label>'.gp247_language_render('action.to').':</label>
                                <div class="input-group">
                                <input type="text" name="end_to" id="end_to" class="form-control input-sm date_time rounded-0" data-date-format="yyyy-mm-dd" placeholder="yyyy-mm-dd" /> 
                                </div>
                            </div>
                        </div> &nbsp;
                        <div style="width:150px">
                            <div class="form-group">
                                <label>'.gp247_language_render('admin.order.status').':</label>
                                <div class="input-group">
                                <select class="form-control rounded-0" name="order_status">
                                <option value="">'.gp247_language_render('admin.order.search_order_status').'</option>
                                ' . $optionStatus . '
                                </select>
                                </div>
                            </div>
                        </div> &nbsp;
                        <div style="width:200px">
                            <div class="form-group">
                                <label>'.gp247_language_render('search.placeholder').':</label>
                                <div class="input-group">
                                    <input type="text" name="keyword" class="form-control rounded-0 float-right" placeholder="' . gp247_language_render('search.placeholder') . '" value="' . $keyword . '">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-primary  btn-flat"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>';
        //=menuSearch


        return view('gp247-admin::screen.list')
            ->with($data);
    }

    /**
     * Form create new item in admin
     * @return [type] [description]
     */
    public function create()
    {
        $data = [
            'title'             => gp247_language_render('admin.order.add_new_title'),
            'subTitle'          => '',
            'title_description' => gp247_language_render('admin.order.add_new_des'),
            'icon'              => 'fa fa-plus',
        ];
        $paymentMethod = [];
        $shippingMethod = [];
        $paymentMethodTmp = gp247_extension_get_via_code(code: 'payment', active: false);
        foreach ($paymentMethodTmp as $key => $value) {
            $paymentMethod[$key] = gp247_language_render($value->detail);
        }
        $shippingMethodTmp = gp247_extension_get_via_code(code: 'shipping', active: false);
        foreach ($shippingMethodTmp as $key => $value) {
            $shippingMethod[$key] = gp247_language_render($value->detail);
        }
        $orderStatus            = $this->statusOrder;
        $currencies             = $this->currency;
        $countries              = $this->country;
        $currenciesRate         = json_encode(ShopCurrency::getListRate());
        $users                  = AdminCustomer::getListAll();
        // WHY: products are now fetched on demand via the searchable picker
        // (admin_order.product_search), so the full catalog is no longer dumped
        // into the page — keeps the create screen light on large catalogs.
        $multiStore             = gp247_store_check_multi_partner_installed() || gp247_store_check_multi_store_installed();
        $data['users']          = $users;
        $data['currencies']     = $currencies;
        $data['countries']      = $countries;
        $data['orderStatus']    = $orderStatus;
        $data['currenciesRate'] = $currenciesRate;
        $data['paymentMethod']  = $paymentMethod;
        $data['shippingMethod'] = $shippingMethod;
        $data['multiStore']     = $multiStore;
        $data['storeList']      = $multiStore ? AdminStore::getListTitle() : [];

        return view('gp247-shop-admin::order-create')
            ->with($data);
    }

    /**
     * Post create new item in admin
     * @return [type] [description]
     */
    public function postCreate()
    {
        $data = request()->all();
        $validate = [
            'first_name'      => config('validation.customer.first_name', 'required|string|max:100'),
            'email'           => gp247_config_admin('customer_email')
                ? (gp247_config_admin('customer_email_required') ? 'required|string|email|max:255' : 'nullable|string|email|max:255')
                : 'sometimes',
            'exchange_rate'   => 'required',
            'currency'        => 'required',
            'status'          => 'required',
        ];
        if (gp247_config('use_payment')) {
            $validate['payment_method'] = 'required';
        }
        if (gp247_config('use_shipping')) {
            $validate['shipping_method'] = 'required';
        }
        if (gp247_store_check_multi_partner_installed() || gp247_store_check_multi_store_installed()) {
            $validate['store_id'] = 'required|string';
        }
        if (gp247_config_admin('customer_lastname')) {
            if (gp247_config_admin('customer_lastname_required')) {
                $validate['last_name'] = config('validation.customer.last_name_required', 'required|string|max:100');
            } else {
                $validate['last_name'] = config('validation.customer.last_name_null', 'nullable|string|max:100');
            }
        }
        // City / district precede address1 (canonical order); both default OFF.
        if (gp247_config_admin('customer_city')) {
            if (gp247_config_admin('customer_city_required')) {
                $validate['city'] = config('validation.customer.city_required', 'required|string|max:100');
            } else {
                $validate['city'] = config('validation.customer.city_null', 'nullable|string|max:100');
            }
        }
        if (gp247_config_admin('customer_district')) {
            if (gp247_config_admin('customer_district_required')) {
                $validate['district'] = config('validation.customer.district_required', 'required|string|max:100');
            } else {
                $validate['district'] = config('validation.customer.district_null', 'nullable|string|max:100');
            }
        }
        if (gp247_config_admin('customer_address1')) {
            if (gp247_config_admin('customer_address1_required')) {
                $validate['address1'] = config('validation.customer.address1_required', 'required|string|max:100');
            } else {
                $validate['address1'] = config('validation.customer.address1_null', 'nullable|string|max:100');
            }
        }
        if (gp247_config_admin('customer_address2')) {
            if (gp247_config_admin('customer_address2_required')) {
                $validate['address2'] = config('validation.customer.address2_required', 'required|string|max:100');
            } else {
                $validate['address2'] = config('validation.customer.address2_null', 'nullable|string|max:100');
            }
        }
        if (gp247_config_admin('customer_address3')) {
            if (gp247_config_admin('customer_address3_required')) {
                $validate['address3'] = config('validation.customer.address3_required', 'required|string|max:100');
            } else {
                $validate['address3'] = config('validation.customer.address3_null', 'nullable|string|max:100');
            }
        }
        if (gp247_config_admin('customer_phone')) {
            if (gp247_config_admin('customer_phone_required')) {
                $validate['phone'] = config('validation.customer.phone_required', 'required|regex:/^0[^0][0-9\-]{6,12}$/');
            } else {
                $validate['phone'] = config('validation.customer.phone_null', 'nullable|regex:/^0[^0][0-9\-]{6,12}$/');
            }
        }
        if (gp247_config_admin('customer_country')) {
            if (gp247_config_admin('customer_country_required')) {
                $validate['country'] = config('validation.customer.country_required', 'required|string|min:2');
            } else {
                $validate['country'] = config('validation.customer.country_null', 'nullable|string|min:2');
            }
        }
        if (gp247_config_admin('customer_postcode')) {
            if (gp247_config_admin('customer_postcode_required')) {
                $validate['postcode'] = config('validation.customer.postcode_required', 'required|min:5');
            } else {
                $validate['postcode'] = config('validation.customer.postcode_null', 'nullable|min:5');
            }
        }
        if (gp247_config_admin('customer_company')) {
            if (gp247_config_admin('customer_company_required')) {
                $validate['company'] = config('validation.customer.company_required', 'required|string|max:100');
            } else {
                $validate['company'] = config('validation.customer.company_null', 'nullable|string|max:100');
            }
        }
        $messages = [
            'last_name.required'       => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.last_name')]),
            'first_name.required'      => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.first_name')]),
            'email.required'           => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.email')]),
            'city.required'            => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.city')]),
            'district.required'        => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.district')]),
            'address1.required'        => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.address1')]),
            'address2.required'        => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.address2')]),
            'address3.required'        => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.address3')]),
            'phone.required'           => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.phone')]),
            'country.required'         => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.country')]),
            'postcode.required'        => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.postcode')]),
            'company.required'         => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.company')]),
            'sex.required'             => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.sex')]),
            'birthday.required'        => gp247_language_render('validation.required', ['attribute'=> gp247_language_render('cart.birthday')]),
            'email.email'              => gp247_language_render('validation.email', ['attribute'=> gp247_language_render('cart.email')]),
            'phone.regex'              => gp247_language_render('customer.phone_regex'),
            'postcode.min'             => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('cart.postcode')]),
            'country.min'              => gp247_language_render('validation.min', ['attribute'=> gp247_language_render('cart.country')]),
            'first_name.max'           => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.first_name')]),
            'email.max'                => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.email')]),
            'city.max'                 => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.city')]),
            'district.max'             => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.district')]),
            'address1.max'             => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.address1')]),
            'address2.max'             => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.address2')]),
            'address3.max'             => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.address3')]),
            'last_name.max'            => gp247_language_render('validation.max', ['attribute'=> gp247_language_render('cart.last_name')]),
            'birthday.date'            => gp247_language_render('validation.date', ['attribute'=> gp247_language_render('cart.birthday')]),
            'birthday.date_format'     => gp247_language_render('validation.date_format', ['attribute'=> gp247_language_render('cart.birthday')]),
            'shipping_method.required' => gp247_language_render('cart.validation.shippingMethod_required'),
            'payment_method.required'  => gp247_language_render('cart.validation.paymentMethod_required'),
            'store_id.required'        => gp247_language_render('validation.required', ['attribute' => gp247_language_render('admin.store')]),
        ];


        $validator = Validator::make($data, $validate, $messages);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        //Create new order
        $dataCreate = [
            'customer_id'     => $data['customer_id'] ?? "",
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'] ?? '',
            'status'          => $data['status'],
            'currency'        => $data['currency'],
            'city'            => $data['city'] ?? '',
            'district'        => $data['district'] ?? '',
            'address1'        => $data['address1'] ?? '',
            'address2'        => $data['address2'] ?? '',
            'address3'        => $data['address3'] ?? '',
            'country'         => $data['country'] ?? '',
            'company'         => $data['company'] ?? '',
            'postcode'        => $data['postcode'] ?? '',
            'phone'           => $data['phone'] ?? '',
            'payment_method'  => $data['payment_method'] ?? null,
            'shipping_method' => $data['shipping_method'] ?? null,
            'exchange_rate'   => $data['exchange_rate'],
            'email'           => $data['email'] ?? '',
            'comment'         => $data['comment'] ?? '',
            // WHY: admin is always root and manages all stores, so store_id must be chosen
            // explicitly on the create form (validated required when multi-store is active).
            // Fallback to GP247_STORE_ID_ROOT for single-store installs where the field is absent.
            // store_id is immutable after creation (ADR shop-admin_admin-order-store-assignment,
            // modification 20260827T142602).
            'store_id'        => (string) ($data['store_id'] ?? GP247_STORE_ID_ROOT),
        ];
        $dataCreate = gp247_clean($dataCreate, [], true);
        // WHY: create order + line items + totals and decrement stock atomically,
        // parity with the storefront ShopOrder::createOrder (US-SADM-order-stock-parity,
        // ADR shop-admin_order-stock-parity). A partial failure must not leave an
        // order/detail/stock in an inconsistent half-written state.
        try {
            \DB::connection(GP247_DB_CONNECTION)->beginTransaction();
            $order = AdminOrder::create($dataCreate);

            // Process products if any
            $subtotal = 0;
            $taxTotal = 0;
            if (!empty($data['products']) && is_array($data['products'])) {
                foreach ($data['products'] as $item) {
                    if (!empty($item['product_id'])) {
                        $product = (new AdminProduct)->getDetail($item['product_id']);
                        if ($product) {
                            // WHY: rounding precision follows product_qty_decimal (modification
                            // 20260705T093328, ADR-016) — whole units by default, 2dp when enabled.
                            $qty = round((float)($item['qty'] ?? 1), gp247_qty_decimal_enabled() ? 2 : 0);
                            $price = (float)($item['price'] ?? 0);
                            $tax = (float)($item['tax'] ?? 0);

                            // WHY: the form field is a tax RATE (%) for convenient entry,
                            // but the persisted line must follow the shared contract
                            // (domain model shop_order-line-item / NFR-MAINT-order-line-truth):
                            // detail.tax is an AMOUNT and total_price excludes tax —
                            // otherwise AdminOrder::updateSubTotal (which sums total_price
                            // as subtotal and tax as amount) double-counts tax and reads
                            // the percent as money on the first later edit.
                            // @aidlc-unit shop-admin
                            // @aidlc-story US-SADM-order-create-line-normalization
                            // @aidlc-adr storefront_order-line-effective-price
                            $itemSubtotal = $qty * $price;
                            $itemTax = $itemSubtotal * $tax / 100;

                            $subtotal += $itemSubtotal;
                            $taxTotal += $itemTax;

                            // WHY: unified hard block across front + admin (ADR
                            // shop-admin_order-stock-parity, revised 2026-08-16 /
                            // modification 20260816T175134). Reject over-stock BEFORE
                            // writing the line; the throw rolls back the whole
                            // transaction so no partial order is left behind. To sell
                            // beyond stock, enable product_buy_out_of_stock.
                            if (!$product->hasStockForOrder($qty)) {
                                throw new \Exception(gp247_language_render('cart.item_over_qty', ['sku' => $product->sku, 'qty' => $qty]));
                            }

                            // WHY: rebuild the attribute selection server-side (add_price
                            // from the DB, never client input) and require a complete
                            // selection when the product has attributes — a missing/partial
                            // pick throws and rolls back the whole order, so no line lands
                            // without its variant (US-SADM-order-item-attribute-select,
                            // NFR-SEC-attribute-price-integrity). A product with no
                            // attributes yields [] and stores null (unchanged behaviour).
                            $options = gp247_cart_options_complete($item['product_id'], $item['attributes'] ?? []);
                            if ($options === false) {
                                throw new \Exception(gp247_language_render('product.please_select_attribute'));
                            }
                            $attribute = $options === [] ? null : json_encode($options);

                            // WHY: addNewDetail inserts AND decrements stock/sold (parity with
                            // storefront createOrder + postAddItem). The previous
                            // ShopOrderDetail::create() left stock untouched → oversell
                            // (RISK-BIZ-admin-oversell).
                            (new ShopOrderDetail)->addNewDetail([[
                                'id' => gp247_uuid(),
                                'order_id' => $order->id,
                                'product_id' => $item['product_id'],
                                'name' => $item['name'] ?? $product->name,
                                'sku' => $product->sku,
                                'price' => $price,
                                'qty' => $qty,
                                'total_price' => $itemSubtotal,
                                'tax' => $itemTax,
                                'attribute' => $attribute,
                                'currency' => $data['currency'],
                                'exchange_rate' => $data['exchange_rate'],
                                'store_id' => $dataCreate['store_id'],
                                'created_at' => gp247_time_now(),
                            ]]);
                        }
                    }
                }
            }

            // Get shipping, discount and received from form
            $shipping = (float)($data['shipping'] ?? 0);
            $discount = (float)($data['discount'] ?? 0);
            $received = (float)($data['received'] ?? 0);
            $total = $subtotal + $taxTotal + $shipping - $discount;
            $balance = $total + $received;

            // Insert order totals with calculated values
            AdminOrder::insertOrderTotal([
                ['id' => gp247_uuid(),'code' => 'subtotal', 'value' => $subtotal, 'title' => gp247_language_render('order.totals.sub_total'), 'sort' => ShopOrderTotal::POSITION_SUBTOTAL, 'order_id' => $order->id],
                ['id' => gp247_uuid(),'code' => 'tax', 'value' => $taxTotal, 'title' => gp247_language_render('order.totals.tax'), 'sort' => ShopOrderTotal::POSITION_TAX, 'order_id' => $order->id],
                ['id' => gp247_uuid(),'code' => 'shipping', 'value' => $shipping, 'title' => gp247_language_render('order.totals.shipping'), 'sort' => ShopOrderTotal::POSITION_SHIPPING_METHOD, 'order_id' => $order->id],
                ['id' => gp247_uuid(),'code' => 'discount', 'value' => $discount, 'title' => gp247_language_render('order.totals.discount'), 'sort' => ShopOrderTotal::POSITION_TOTAL_METHOD, 'order_id' => $order->id],
                ['id' => gp247_uuid(),'code' => 'other_fee', 'value' => 0, 'title' => gp247_language_render('order.totals.other_fee'), 'sort' => ShopOrderTotal::POSITION_OTHER_FEE, 'order_id' => $order->id],
                ['id' => gp247_uuid(),'code' => 'total', 'value' => $total, 'title' => gp247_language_render('order.totals.total'), 'sort' => ShopOrderTotal::POSITION_TOTAL, 'order_id' => $order->id],
                ['id' => gp247_uuid(),'code' => 'received', 'value' => $received, 'title' => gp247_language_render('order.totals.received'), 'sort' => ShopOrderTotal::POSITION_RECEIVED, 'order_id' => $order->id],
            ]);

            // Update order total
            $order->update([
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'discount' => $discount,
                'tax' => $taxTotal,
                'total' => $total,
                'received' => $received,
                'balance' => $balance,
            ]);

            \DB::connection(GP247_DB_CONNECTION)->commit();
        } catch (\Throwable $e) {
            \DB::connection(GP247_DB_CONNECTION)->rollBack();

            return redirect()->back()->withInput()
                ->with('error', gp247_language_render('admin.toast_error') . ' ' . $e->getMessage());
        }

        return redirect(gp247_route_admin('admin_order.index'))
            ->with('success', gp247_language_render('action.create_success'));
    }

    /**
     * [getInfoUser description]
     * @param   [description]
     * @return [type]           [description]
     */
    public function getInfoUser()
    {
        $id = request('id');
        return AdminCustomer::getCustomerAdminJson($id);
    }

    /**
     * Build the attribute groups + options a product offers, for the create-order
     * picker JSON (one <select> per group on the Alpine screen).
     *
     * @param int|string $productId
     * @param \Illuminate\Support\Collection<int|string, string> $groupNames Attribute-group id => name map.
     * @return array<int, array{group_id: int|string, group_name: string, options: array<int, array{name: string, add_price: float}>}>
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-item-attribute-select
     */
    private function productAttributeGroups($productId, $groupNames): array
    {
        $rows = ShopProductAttribute::where('product_id', $productId)->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $groups = [];
        foreach ($rows->groupBy('attribute_group_id') as $groupId => $options) {
            $groups[] = [
                'group_id' => $groupId,
                'group_name' => (string) ($groupNames[$groupId] ?? ('#' . $groupId)),
                'options' => $options->map(static fn ($o): array => [
                    'name' => (string) $o->name,
                    'add_price' => (float) $o->add_price,
                ])->values()->all(),
            ];
        }

        return $groups;
    }

    /**
     * Product picker search for the create-order screen (Alpine fetch). Returns
     * sellable products matching sku / alias / name (current locale) as JSON,
     * mirroring the edit-order (Livewire) picker via the shared query.
     *
     * @return \Illuminate\Http\JsonResponse List of {id, sku, name, price, stock, stock_raw, attributes}.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-003
     */
    public function getSearchProduct()
    {
        $term = (string) request('term', '');
        $products = ShopProduct::searchForAdminOrderPicker($term);

        // WHY: preload attribute group names once so building each product's
        // attribute groups stays a single query (not N per result).
        $groupNames = ShopAttributeGroup::pluck('name', 'id');

        return response()->json(
            $products->map(function ($product) use ($groupNames) {
                return [
                    'id' => (string) $product->id,
                    'sku' => (string) $product->sku,
                    // WHY: name lives on the description relation (getName()), so use
                    // it — $product->name is usually null — falling back to sku.
                    'name' => (string) ($product->getName() ?: $product->sku),
                    'price' => (float) $product->price,
                    // WHY: display-only on-hand stock beside the name — pre-formatted
                    // (gp247_qty_format) to match the edit-order picker exactly.
                    'stock' => gp247_qty_format((float) $product->stock),
                    // WHY: raw numeric stock so the create screen can warn client-side
                    // when qty > stock before submit (US-SADM-order-create-stock-feedback).
                    // The formatted 'stock' above may carry a thousands separator and is
                    // not safe to compare numerically.
                    'stock_raw' => (float) $product->stock,
                    // WHY: ship the attribute groups + options so the Alpine create
                    // screen can render one <select> per group and suggest the
                    // effective price (US-SADM-order-item-attribute-select). add_price
                    // is authoritative here but rebuilt from the DB again on save.
                    'attributes' => $this->productAttributeGroups($product->id, $groupNames),
                ];
            })->all()
        );
    }

    /*
    Delete list order ID
    Need mothod destroy to boot deleting in model
    */
    public function deleteList()
    {
        if (!request()->ajax()) {
            return response()->json(['error' => 1, 'msg' => gp247_language_render('admin.method_not_allow')]);
        } else {
            $ids = request('ids');
            $arrID = explode(',', $ids);
            $arrDontPermission = [];
            foreach ($arrID as $key => $id) {
                if (!$this->checkPermisisonItem($id)) {
                    $arrDontPermission[] = $id;
                }
            }
            if (count($arrDontPermission)) {
                return response()->json(['error' => 1, 'msg' => gp247_language_render('admin.remove_dont_permisison') . ': ' . json_encode($arrDontPermission)]);
            } else {
                AdminOrder::destroy($arrID);
                return response()->json(['error' => 0, 'msg' => gp247_language_render('action.update_success')]);
            }
        }
    }

    /**
     * Process invoice
     */
    public function invoice()
    {
        $orderId = request('order_id') ?? null;
        $order = AdminOrder::getOrderAdmin($orderId);
        if ($order) {
            $data                    = array();
            $data['name']            = $order['first_name'] . ' ' . $order['last_name'];
            // Canonical order (city -> district -> address1..3 -> country); drop
            // empty segments so old/optional fields don't leave dangling commas.
            $data['address']         = implode(', ', array_filter([
                $order['city'], $order['district'], $order['address1'],
                $order['address2'], $order['address3'], $order['country'],
            ]));
            $data['phone']           = $order['phone'];
            $data['email']           = $order['email'];
            $data['comment']         = $order['comment'];
            $data['payment_method']  = $order['payment_method'];
            $data['shipping_method'] = $order['shipping_method'];
            $data['created_at']      = $order['created_at'];
            $data['currency']        = $order['currency'];
            $data['exchange_rate']   = $order['exchange_rate'];
            $data['subtotal']        = $order['subtotal'];
            $data['tax']             = $order['tax'];
            $data['shipping']        = $order['shipping'];
            $data['discount']        = $order['discount'];
            $data['total']           = $order['total'];
            $data['received']        = $order['received'];
            $data['balance']         = $order['balance'];
            $data['other_fee']       = $order['other_fee'] ?? 0;
            $data['comment']         = $order['comment'];
            $data['country']         = $order['country'];
            $data['id']              = $order->id;
            $data['details'] = [];

            $attributesGroup =  ShopAttributeGroup::pluck('name', 'id')->all();

            if ($order->details) {
                foreach ($order->details as $key => $detail) {
                    $arrAtt = json_decode($detail->attribute, true);
                    if ($arrAtt) {
                        $htmlAtt = '';
                        foreach ($arrAtt as $groupAtt => $att) {
                            $htmlAtt .= $attributesGroup[$groupAtt] .':'.gp247_render_option_price($att, $order['currency'], $order['exchange_rate']);
                            // Append the snapshot slug (3rd '__' segment) when present so the
                            // invoice line carries it too (US-SADM-order-attribute-slug, mod
                            // 20260825T135923); empty-safe for legacy 2-segment orders.
                            $slug = explode('__', (string) $att)[2] ?? '';
                            if ($slug !== '') {
                                $htmlAtt .= ' ('.$slug.')';
                            }
                        }
                        $name = $detail->name.'('.strip_tags($htmlAtt).')';
                    } else {
                        $name = $detail->name;
                    }
                    $data['details'][] = [
                        'no' => $key + 1, 
                        'sku' => $detail->sku, 
                        'name' => $name, 
                        'qty' => $detail->qty, 
                        'price' => $detail->price, 
                        'total_price' => $detail->total_price,
                    ];
                }
            }

            return view('gp247-shop-admin::format.invoice')
            ->with($data);
        } else {
            return redirect(gp247_route_admin('admin.data_not_found'))->with(['url' => url()->full()]);
        }
    }

    /**
     * Check permisison item
     */
    public function checkPermisisonItem($id)
    {
        return AdminOrder::getOrderAdmin($id);
    }
}
