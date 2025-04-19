<?php
#GP247\Shop\Modelss/ShopOrder.php
namespace GP247\Shop\Models;

use GP247\Shop\Models\ShopOrderDetail;
use GP247\Shop\Models\ShopOrderHistory;
use GP247\Shop\Models\ShopOrderTotal;
use GP247\Shop\Models\ShopProduct;
use DB;
use Illuminate\Database\Eloquent\Model;


class ShopOrder extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;

    public $table = GP247_DB_PREFIX.'shop_order';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    protected $gp247_order_profile = 0; // 0: all, 1: only user's order
    public $gp247_status = 1;
    
    public function details()
    {
        return $this->hasMany(ShopOrderDetail::class, 'order_id', 'id');
    }
    public function orderTotal()
    {
        return $this->hasMany(ShopOrderTotal::class, 'order_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo('GP247\Shop\Models\ShopCustomer', 'customer_id', 'id');
    }
    public function orderStatus()
    {
        return $this->hasOne(ShopOrderStatus::class, 'id', 'status');
    }
    public function paymentStatus()
    {
        return $this->hasOne(ShopPaymentStatus::class, 'id', 'payment_status');
    }
    public function history()
    {
        return $this->hasMany(ShopOrderHistory::class, 'order_id', 'id');
    }
    protected static function boot()
    {
        parent::boot();
        // before delete() method call this
        static::deleting(function ($order) {
            foreach ($order->details as $key => $orderDetail) {
                //Update stock, sold
                ShopProduct::updateStock($orderDetail->product_id, -$orderDetail->qty);
            }
            $order->details()->delete(); //delete order details
            $order->orderTotal()->delete(); //delete order total
            $order->history()->delete(); //delete history
        });

        //Uuid
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'OD-'.gp247_token(8);
            }
        });
    }


    /**
     * Update status order
     *
     * @param [type] $orderId
     * @param integer $status
     * @param array $history
     * @return void
     */
    public function updateStatus($orderId, $status = 0, $history = [])
    {
        $order = $this->find($orderId);
        if ($order) {
            //Update status
            $order->update(['status' => (int) $status]);

            //Add history
            $dataHistory = [
                'order_id' => $orderId,
                'customer_id' => $history['user_id'] ?? 0,
                'admin_id' => $history['admin_id'] ?? 0,
                'content' => $history['content'] ?? '',
                'order_status_id' => $status,
            ];
            $this->addOrderHistory($dataHistory);

            //Process event update status order
            gp247_event_order_update_status($order);
        }
    }


    public function scopeSort($query, $sortBy = null, $sortOrder = 'asc')
    {
        $sortBy = $sortBy ?? 'sort';
        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Create new order
     * @param  [array] $dataOrder
     * @param  [array] $dataTotal
     * @param  [array] $arrCartDetail
     * @return [array]
     */
    public function createOrder($dataOrder, $dataTotal, $arrCartDetail)
    {
        //Process escape
        $dataOrder     = gp247_clean($dataOrder);
        $dataTotal     = gp247_clean($dataTotal);
        $arrCartDetail = gp247_clean($arrCartDetail);
        try {
            DB::connection(GP247_DB_CONNECTION)->beginTransaction();
            $dataOrder['domain'] = url('/');
            $uID = $dataOrder['customer_id'] ?? 0;
            $adminID = $dataOrder['admin_id'] ?? 0;
            unset($dataOrder['admin_id']);
            $currency = $dataOrder['currency'];
            $exchange_rate = $dataOrder['exchange_rate'];

            //Insert order
            $order = ShopOrder::create($dataOrder);
            $orderID = $order->id;
            //End insert order

            //Insert order total
            foreach ($dataTotal as $key => $row) {
                $row = gp247_clean($row);
                $row['id'] = gp247_generate_id();
                $row['order_id'] = $orderID;
                $row['created_at'] = gp247_time_now();
                $dataTotal[$key] = $row;
            }
            ShopOrderTotal::insert($dataTotal);
            //End order total

            //Order detail
            foreach ($arrCartDetail as $cartDetail) {
                $pID = $cartDetail['product_id'];
                $product = ShopProduct::find($pID);
                
                //Check product flash sale over stock
                if (function_exists('gp247_product_flash_check_over') && !gp247_product_flash_check_over($pID, $cartDetail['qty'])) {
                    throw new \Exception(gp247_language_render('cart.item_over_qty', ['sku' => $product->sku, 'qty' => $cartDetail['qty']]));
                }

                //If product out of stock
                if (!gp247_config('product_buy_out_of_stock') && $product->stock < $cartDetail['qty']) {
                    throw new \Exception(gp247_language_render('cart.item_over_qty', ['sku' => $product->sku, 'qty' => $cartDetail['qty']]));
                }
                //
                $tax = (gp247_tax_price($cartDetail['price'], $product->getTaxValue()) - $cartDetail['price']) *  $cartDetail['qty'];

                $cartDetail['order_id'] = $orderID;
                $cartDetail['currency'] = $currency;
                $cartDetail['exchange_rate'] = $exchange_rate;
                $cartDetail['sku'] = $product->sku;
                $cartDetail['tax'] = $tax;
                $cartDetail['store_id'] = $cartDetail['store_id'];
                $cartDetail['attribute'] = json_encode($cartDetail['attribute']);
                $this->addOrderDetail($cartDetail);

                //Update stock flash sale
                if (function_exists('gp247_product_flash_update_stock')) {
                    gp247_product_flash_update_stock($pID, $cartDetail['qty']);
                }

                //Update stock and sold
                ShopProduct::updateStock($pID, $cartDetail['qty']);
            }
            //End order detail

            //Add history
            $dataHistory = [
                'order_id' => $orderID,
                'content' => 'New order',
                'customer_id' => $uID,
                'admin_id' => $adminID,
                'order_status_id' => $order->status,
            ];
            $this->addOrderHistory($dataHistory);

            //Process Discount
            $totalMethod = session('totalMethod') ?? [];
            foreach ($totalMethod as $keyPlugin => $codeApply) {
                if ($codeApply) {
                    $moduleClass = gp247_extension_get_namespace(type: 'Plugins', key: $keyPlugin);
                    $moduleClass = $moduleClass . '\Controllers\FrontController';
                    //Check class exist
                    if (class_exists($moduleClass) && method_exists($moduleClass, 'apply')) {
                        $arrReturnModuleDiscount = (new $moduleClass)->apply($codeApply, $uID, $msg = 'Order #' . $orderID);
                        if ($arrReturnModuleDiscount['error'] == 1) {
                            throw new \Exception($arrReturnModuleDiscount['msg']);
                        }
                    }
                }
            }
            // End process Discount

            DB::connection(GP247_DB_CONNECTION)->commit();

            // Process event created
            gp247_event_order_created($order);

            $return = ['error' => 0, 'orderID' => $orderID, 'msg' => "", 'detail' => $order];
        } catch (\Throwable $e) {
            DB::connection(GP247_DB_CONNECTION)->rollBack();
            $return = ['error' => 1, 'msg' => $e->getMessage()];
        }
        return $return;
    }

    /**
     * Add order history
     * @param [array] $dataHistory
     */
    public function addOrderHistory($dataHistory)
    {
        return ShopOrderHistory::create($dataHistory);
    }

    /**
     * Add order detail
     * @param [type] $dataDetail [description]
     */
    public function addOrderDetail($dataDetail)
    {
        return ShopOrderDetail::create($dataDetail);
    }


    /**
     * Start new process get data
     *
     * @return  new model
     */
    public function start()
    {
        if ($this->gp247_order_profile) {
            $obj = (new ShopOrder);
            $obj->gp247_order_profile = 1;
            return $obj;
        } else {
            return new ShopOrder;
        }
    }

    /**
     * Get order detail
     *
     * @param   [int]  $orderID
     *
     */
    public function getDetail($orderID)
    {
        if (empty($orderID)) {
            return null;
        }
        $customer = customer()->user();
        if ($customer) {
            return $this->where('id', $orderID)
                ->where('customer_id', $customer->id)
                ->first();
        } else {
            return null;
        }
    }

    /**
     * Disable only user's order mode
     */
    public function setOrderProfile()
    {
        $this->gp247_order_profile = 1;
        $this->gp247_status = 'all' ;
        return $this;
    }

    public function profile()
    {
        $this->setOrderProfile();
        return $this;
    }

    /**
     * Get list order new
     */
    public function getOrderNew()
    {
        $this->gp247_status = 1;
        return $this;
    }

    /**
     * Get list order processing
     */
    public function getOrderProcessing()
    {
        $this->gp247_status = 2;
        return $this;
    }

    /**
     * Get list order hold
     */
    public function getOrderHold()
    {
        $this->gp247_status = 3;
        return $this;
    }

    /**
     * Get list order canceld
     */
    public function getOrderCanceled()
    {
        $this->gp247_status = 4;
        return $this;
    }

    /**
     * Get list order done
     */
    public function getOrderDone()
    {
        $this->gp247_status = 5;
        return $this;
    }

    /**
     * Get list order failed
     */
    public function getOrderFailed()
    {
        $this->gp247_status = 6;
        return $this;
    }

    /**
     * build Query
     */
    public function buildQuery()
    {
        $customer = customer()->user();
        if ($this->gp247_order_profile == 1) {
            if (!$customer) {
                return null;
            }
            $uID = $customer->id;
            $query = $this->with('orderTotal')->where('customer_id', $uID);
        } else {
            $query = $this->with('orderTotal')->with('details');
        }

        if ($this->gp247_status !== 'all') {
            $query = $query->where('status', $this->gp247_status);
        }

        $query = $this->processMoreQuery($query);
        

        if ($this->random) {
            $query = $query->inRandomOrder();
        } else {
            if (is_array($this->gp247_sort) && count($this->gp247_sort)) {
                foreach ($this->gp247_sort as  $rowSort) {
                    if (is_array($rowSort) && count($rowSort) == 2) {
                        $query = $query->sort($rowSort[0], $rowSort[1]);
                    }
                }
            } else {
                $query = $query->orderBy('created_at', 'desc');
            }
        }

        return $query;
    }

    /**
     * Update value balance, received when order capture full money with payment method
     *
     * @return  [type]  [return description]
     */
    public function processPaymentPaid()
    {
        $total = $this->total;
        $this->balance = 0;
        $this->received = -$total;
        $this->save();
        (new ShopOrderTotal)
            ->where('order_id', $this->id)
            ->where('code', 'received')
            ->update(['value' =>  -$total]);
    }
}
