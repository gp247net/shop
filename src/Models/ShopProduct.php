<?php
namespace GP247\Shop\Models;

use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopProductCategory;
use GP247\Shop\Models\ShopProductDescription;
use GP247\Shop\Models\ShopProductGroup;
use GP247\Shop\Models\ShopProductPromotion;
use GP247\Shop\Models\ShopTax;
use GP247\Core\Models\AdminStore;
use GP247\Shop\Models\ShopProductStore;
use GP247\Core\Models\AdminCustomFieldDetail;
use Illuminate\Database\Eloquent\Model;


class ShopProduct extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;

    public $table = GP247_DB_PREFIX.'shop_product';
    protected $guarded = [];

    protected $connection = GP247_DB_CONNECTION;

    protected $gp247_kind = []; // 0:single, 1:bundle, 2:group
    protected $gp247_tag = 'all'; // 0:physical, 1:download, 2:only view, 3: Service
    protected $gp247_promotion = 0; // 1: only produc promotion,
    protected $gp247_store_info_id = 0;
    protected $gp247_array_ID = []; // array ID product
    protected $gp247_category = []; // array category id
    protected $gp247_category_vendor = []; // array category id
    protected $gp247_brand = []; // array brand id
    protected $gp247_supplier = []; // array supplier id
    protected $gp247_range_price = null; // min__max
    protected static $storeCode = null;

    
    public function brand()
    {
        return $this->belongsTo(ShopBrand::class, 'brand_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(ShopSupplier::class, 'supplier_id', 'id');
    }

    public function categories()
    {
        return $this->belongsToMany(ShopCategory::class, ShopProductCategory::class, 'product_id', 'category_id');
    }
    public function groups()
    {
        return $this->hasMany(ShopProductGroup::class, 'group_id', 'id');
    }
    public function stores()
    {
        return $this->belongsToMany(AdminStore::class, ShopProductStore::class, 'product_id', 'store_id');
    }
    public function builds()
    {
        return $this->hasMany(ShopProductBuild::class, 'build_id', 'id');
    }
    public function images()
    {
        return $this->hasMany(ShopProductImage::class, 'product_id', 'id');
    }

    public function descriptions()
    {
        return $this->hasMany(ShopProductDescription::class, 'product_id', 'id');
    }

    public function promotionPrice()
    {
        return $this->hasOne(ShopProductPromotion::class, 'product_id', 'id');
    }
    public function attributes()
    {
        return $this->hasMany(ShopProductAttribute::class, 'product_id', 'id');
    }
    public function downloadPath()
    {
        return $this->hasOne(ShopProductDownload::class, 'product_id', 'id');
    }

    //Function get text description
    public function getText()
    {
        return $this->descriptions()->where('lang', gp247_get_locale())->first();
    }
    public function getName()
    {
        return $this->getText()->name?? '';
    }
    public function getDescription()
    {
        return $this->getText()->description ?? '';
    }
    public function getKeyword()
    {
        return $this->getText()->keyword?? '';
    }
    public function getContent()
    {
        return $this->getText()->content ?? '';
    }
    //End  get text description

    /*
    *Get final price
    */
    public function getFinalPrice()
    {
        $promotion = $this->processPromotionPrice();
        if ($promotion != -1) {
            return $promotion;
        } else {
            return $this->price;
        }
    }

    /*
    *Get final price with tax
    */
    public function getFinalPriceTax()
    {
        return gp247_tax_price($this->getFinalPrice(), $this->getTaxValue());
    }


    /**
     * [showPrice description]
     * @return [type]           [description]
     */
    public function showPrice()
    {
        if (!gp247_config('product_price', config('app.storeId'))) {
            return false;
        }
        $price = $this->price;
        $priceFinal = $this->getFinalPrice();
        // Process with tax
        $subPath = 'common.shop_show_price';
        $view = gp247_shop_process_view('GP247TemplatePath::' . gp247_store_info('template'),$subPath);
        gp247_check_view($view);
        return  view(
            $view,
            [
                'price' => $price,
                'priceFinal' => $priceFinal,
                'kind' => $this->kind,
            ]
        )->render();
    }

    /**
     * [showPriceDetail description]
     *
     *
     * @return  [type]             [return description]
     */
    public function showPriceDetail()
    {
        if (!gp247_config('product_price', config('app.storeId'))) {
            return false;
        }
        $price = $this->price;
        $priceFinal = $this->getFinalPrice();
        // Process with tax
        $subPath = 'common.shop_show_price_detail';
        $view = gp247_shop_process_view('GP247TemplatePath::' . gp247_store_info('template'),$subPath);
        gp247_check_view($view);
        return  view(
            $view,
            [
                'price' => $price,
                'priceFinal' => $priceFinal,
                'kind' => $this->kind,
            ]
        )->render();
    }

    /**
     * Get product detail
     * @param  [string] $key [description]
     * @param  [string] $type id, sku, alias
     * @return [int]  $checkActive
     */
    public function getDetail($key = null, $type = null, $storeId = null, $checkActive = 1)
    {
        if (empty($key)) {
            return null;
        }
        $storeId = empty($storeId) ? config('app.storeId') : $storeId;
        $tableStore = (new AdminStore)->getTable();
        $tableProductStore = (new ShopProductStore)->getTable();

        // Check store status  = 1
        $store = AdminStore::find($storeId);
        if (!$store || !$store->status) {
            return null;
        }

        if (config('app.storeId') != GP247_STORE_ID_ROOT) {
            //If the store is not the primary store
            //Cannot view the product in another store
            $storeId = config('app.storeId');
        }

        $tableDescription = (new ShopProductDescription)->getTable();

        $dataSelect = $this->getTable().'.*, '.$tableDescription.'.*';

        $product = $this->leftJoin($tableDescription, $tableDescription . '.product_id', $this->getTable() . '.id');
        
        if (gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) {
            $dataSelect .= ', '.$tableProductStore.'.store_id';
            $product = $product->join($tableProductStore, $tableProductStore.'.product_id', $this->getTable() . '.id');
            $product = $product->join($tableStore, $tableStore . '.id', $tableProductStore.'.store_id');
            $product = $product->where($tableStore . '.status', '1');

            if (gp247_store_check_multi_store_installed()  
                || (
                    (gp247_store_check_multi_partner_installed()) 
                    && (!empty($this->gp247_store_info_id) || config('app.storeId') != GP247_STORE_ID_ROOT)
                    )
            ) {
                //store of vendor
                $product = $product->where($tableProductStore.'.store_id', $storeId);
            }
        }

        $product = $product->where($tableDescription . '.lang', gp247_get_locale());

        if (empty($type)) {
            $product = $product->where($this->getTable().'.id', $key);
        } elseif ($type == 'alias') {
            $product = $product->where($this->getTable().'.alias', $key);
        } elseif ($type == 'sku') {
            $product = $product->where($this->getTable().'.sku', $key);
        } else {
            $product = $product->where($this->getTable().'.id', $key);
        }

        if ($checkActive) {
            $product = $product->where($this->getTable() .'.status', 1)->where($this->getTable() .'.approve', 1);
        }
        $product = $product->selectRaw($dataSelect);
        $product = $product
            ->with('images')
            ->with('stores')
            ->with('promotionPrice');
        $product = $product->first();
        return $product;
    }

    protected static function boot()
    {
        parent::boot();
        // before delete() method call this
        static::deleting(
            function ($product) {
                $product->images()->delete();
                $product->descriptions()->delete();
                $product->promotionPrice()->delete();
                $product->groups()->delete();
                $product->attributes()->delete();
                $product->downloadPath()->delete();
                $product->builds()->delete();
                $product->categories()->detach();
                $product->stores()->detach();

                //Delete custom field
                (new \GP247\Core\Models\AdminCustomFieldDetail)
                ->join(GP247_DB_PREFIX.'admin_custom_field', GP247_DB_PREFIX.'admin_custom_field.id', GP247_DB_PREFIX.'admin_custom_field_detail.custom_field_id')
                ->select('code', 'name', 'text')
                ->where(GP247_DB_PREFIX.'admin_custom_field_detail.rel_id', $product->id)
                ->where(GP247_DB_PREFIX.'admin_custom_field.type', 'shop_product')
                ->delete();
            }
        );

        //Uuid
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = gp247_generate_id('PRD');
            }
        });

    }

    /*
    *Get thumb
    */
    public function getThumb()
    {
        return gp247_image_get_path_thumb($this->image);
    }

    /*
    *Get image
    */
    public function getImage()
    {
        return gp247_image_get_path($this->image);
    }

    /**
     * [getUrl description]
     * @return [type] [description]
     */
    public function getUrl($lang = null)
    {
        return gp247_route_front('product.detail', ['alias' => $this->alias, 'lang' => $lang ?? app()->getLocale()]);
    }

    /**
     * [getPercentDiscount description]
     * @return [type] [description]
     */
    public function getPercentDiscount()
    {
        return round((($this->price - $this->getFinalPrice()) / $this->price) * 100);
    }

    public function renderAttributeDetails()
    {
        $subPath = 'common.shop_render_attribute';
        $view = gp247_shop_process_view('GP247TemplatePath::' . gp247_store_info('template'),$subPath);
        gp247_check_view($view);
        return  view(
            $view,
            [
                'details' => $this->attributes()->get()->groupBy('attribute_group_id'),
                'groups' => ShopAttributeGroup::getListAll(),
            ]
        );
    }


    
    public function scopeSort($query, $sortBy = null, $sortOrder = 'asc')
    {
        $sortBy = $sortBy ?? 'id';
        return $query->orderBy($sortBy, $sortOrder);
    }

    /*
    *Condition:
    * -Active
    * -In of stock or allow order out of stock
    * -Date availabe
    * -Not GP247_PRODUCT_GROUP
    */
    public function allowSale()
    {
        if (!gp247_config('product_price', config('app.storeId'))) {
            return false;
        }
        if ($this->status &&
            (gp247_config('product_preorder', config('app.storeId')) == 1 
                || $this->date_available === null 
                || gp247_time_now() >= $this->date_available)
            && (
                gp247_config('product_buy_out_of_stock', config('app.storeId')) 
                || $this->stock 
                || empty(gp247_config('product_stock', config('app.storeId'))))
            && $this->kind != GP247_PRODUCT_GROUP
        ) {
            return true;
        } else {
            return false;
        }
    }

    /*
    Check promotion price
    */
    private function processPromotionPrice()
    {
        $promotion = $this->promotionPrice;
        if ($promotion) {
            if (($promotion['date_end'] >= date("Y-m-d") || $promotion['date_end'] === null)
                && ($promotion['date_start'] <= date("Y-m-d H:i:s") || $promotion['date_start'] === null)
                && $promotion['status_promotion'] = 1) {
                return $promotion['price_promotion'];
            }
        }

        return -1;
    }

    /*
    Upate stock, sold
    */
    public static function updateStock($product_id, $qty_change)
    {
        $item = self::find($product_id);
        if ($item) {
            $item->stock = $item->stock - $qty_change;
            $item->sold = $item->sold + $qty_change;
            $item->save();

            //Process build
            $product = self::find($product_id);
            if ($product->kind == GP247_PRODUCT_BUILD) {
                foreach ($product->builds as $key => $build) {
                    $productBuild = $build->product;
                    $productBuild->stock -= $qty_change * $build->quantity;
                    $productBuild->sold += $qty_change * $build->quantity;
                    $productBuild->save();
                }
            }
        }
    }

    /**
     * Start new process get data
     *
     * @return  new model
     */
    public function start()
    {
        return new ShopProduct;
    }
    
    /**
     * Set product kind
     */
    private function setKind($kind)
    {
        if (is_array($kind)) {
            $this->gp247_kind = $kind;
        } else {
            $this->gp247_kind = array((int)$kind);
        }
        return $this;
    }

    /**
     * Set tag product
     */
    private function setVirtual($tag)
    {
        if ($tag === 'all') {
            $this->gp247_tag = $tag;
        } else {
            $this->gp247_tag = (int)$tag;
        }
        return $this;
    }

    /**
     * Set array category
     *
     * @param   [array|int]  $category
     *
     */
    private function setCategory($category)
    {
        if (is_array($category)) {
            $this->gp247_category = $category;
        } else {
            $this->gp247_category = array($category);
        }
        return $this;
    }

    /**
     * Set array category store
     *
     * @param   [array|int]  $category
     *
     */
    private function setCategoryVendor($category)
    {
        if (is_array($category)) {
            $this->gp247_category_vendor = $category;
        } else {
            $this->gp247_category_vendor = array($category);
        }
        return $this;
    }

    /**
     * Set array brand
     *
     * @param   [array|int]  $brand
     *
     */
    private function setBrand($brand)
    {
        if (is_array($brand)) {
            $this->gp247_brand = $brand;
        } else {
            $this->gp247_brand = array($brand);
        }
        return $this;
    }

    /**
     * Set product promotion
     *
     */
    private function setPromotion()
    {
        $this->gp247_promotion = 1;
        return $this;
    }

    /**
     * Set store id
     *
     */
    public function setStore($id)
    {
        $this->gp247_store_info_id = $id;
        return $this;
    }

    /**
     * Set range price
     *
     */
    public function setRangePrice($price)
    {
        if ($price) {
            $this->gp247_range_price = $price;
        }
        return $this;
    }

    /**
     * Set array ID product
     *
     * @param   [array|int]  $arrID
     *
     */
    private function setArrayID($arrID)
    {
        if (is_array($arrID)) {
            $this->gp247_array_ID = $arrID;
        } else {
            $this->gp247_array_ID = array($arrID);
        }
        return $this;
    }

    
    /**
     * Set array supplier
     *
     * @param   [array|int]  $supplier
     *
     */
    private function setSupplier($supplier)
    {
        if (is_array($supplier)) {
            $this->gp247_supplier = $supplier;
        } else {
            $this->gp247_supplier = array($supplier);
        }
        return $this;
    }

    /**
     * Product hot
     */
    public function getProductHot()
    {
        return $this->getProductPromotion();
    }

    /**
     * Product build
     */
    public function getProductBuild()
    {
        $this->setKind(GP247_PRODUCT_BUILD);
        return $this;
    }

    /**
     * Product group
     */
    public function getProductGroup()
    {
        $this->setKind(GP247_PRODUCT_GROUP);
        return $this;
    }

    /**
     * Product single
     */
    public function getProductSingle()
    {
        $this->setKind(GP247_PRODUCT_SINGLE);
        return $this;
    }

    /**
     * Get product to array Catgory
     * @param   [array|int]  $arrCategory
     */
    public function getProductToCategory($arrCategory)
    {
        $this->setCategory($arrCategory);
        return $this;
    }

    /**
     * Get product to  Catgory store
     * @param   [int]  $category
     */
    public function getProductToCategoryStore($category)
    {
        $this->setCategoryVendor($category);
        return $this;
    }

    /**
     * Get product to array Brand
     * @param   [array|int]  $arrBrand
     */
    public function getProductToBrand($arrBrand)
    {
        $this->setBrand($arrBrand);
        return $this;
    }

    /**
     * Get product to array Supplier
     * @param   [array|int]  $arrSupplier
     */
    private function getProductToSupplier($arrSupplier)
    {
        $this->setSupplier($arrSupplier);
        return $this;
    }


    /**
     * Get product latest
     */
    public function getProductLatest()
    {
        $this->setLimit(10);
        $this->setSort(['created_at', 'desc']);
        return $this;
    }

    /**
     * Get product last view
     */
    public function getProductLastView()
    {
        $this->setLimit(10);
        $this->setSort(['date_available', 'desc']);
        return $this;
    }

    /**
     * Get product best sell
     */
    public function getProductBestSell()
    {
        $this->setLimit(10);
        $this->setSort(['sold', 'desc']);
        return $this;
    }

    /**
     * Get product promotion
     */
    public function getProductPromotion()
    {
        $this->setLimit(10);
        $this->setPromotion();
        return $this;
    }

    /**
     * Get product from list ID product
     *
     * @param   [array]  $arrID  array id product
     *
     * @return  [type]          [return description]
     */
    public function getProductFromListID($arrID)
    {
        if (is_array($arrID)) {
            $this->setArrayID($arrID);
        }
        return $this;
    }

    /**
     * build Query
     */
    public function buildQuery()
    {
        $tableDescription = (new ShopProductDescription)->getTable();
        $tableStore = (new AdminStore)->getTable();
        $tableProductStore = (new ShopProductStore)->getTable();
        $storeId = $this->gp247_store_info_id ? $this->gp247_store_info_id : config('app.storeId');
        
        // Start with a subquery to get unique product IDs first
        $subQuery = $this->select($this->getTable().'.id');
        
        // Apply store filters in subquery
        if (gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) {
            $subQuery = $subQuery->join($tableProductStore, $tableProductStore.'.product_id', $this->getTable() . '.id');
            $subQuery = $subQuery->join($tableStore, $tableStore . '.id', $tableProductStore.'.store_id');
            $subQuery = $subQuery->where($tableStore . '.status', '1');

            if (gp247_store_check_multi_store_installed()
                // Multi store
                || (
                    // Multi vendor and not root store
                    (gp247_store_check_multi_partner_installed()) 
                    && (!empty($this->gp247_store_info_id) || config('app.storeId') != GP247_STORE_ID_ROOT)
                    )
            ) {
                //store of vendor
                $subQuery = $subQuery->where($tableProductStore.'.store_id', $storeId);
            }

            if (count($this->gp247_category_vendor) && gp247_store_check_multi_partner_installed()) {
                if (gp247_config_global('MultiVendorPro')) {
                    $vendorProductCategoryClass = '\App\GP247\Plugins\MultiVendorPro\Models\VendorProductCategory';
                    if (class_exists($vendorProductCategoryClass)) {
                        $tablePTC = (new $vendorProductCategoryClass)->getTable();
                        $subQuery = $subQuery->leftJoin($tablePTC, $tablePTC . '.product_id', $this->getTable() . '.id');
                        $subQuery = $subQuery->whereIn($tablePTC . '.vendor_category_id', $this->gp247_category_vendor);
                    }
                }
            }
        }

        // Apply category filters in subquery
        if (count($this->gp247_category)) {
            $tablePTC = (new ShopProductCategory)->getTable();
            $subQuery = $subQuery->leftJoin($tablePTC, $tablePTC . '.product_id', $this->getTable() . '.id');
            $subQuery = $subQuery->whereIn($tablePTC . '.category_id', $this->gp247_category);
        }

        // Apply promotion filters in subquery
        if ($this->gp247_promotion == 1) {
            $tablePromotion = (new ShopProductPromotion)->getTable();
            $subQuery = $subQuery->join($tablePromotion, $this->getTable() . '.id', '=', $tablePromotion . '.product_id')
                ->where($tablePromotion . '.status_promotion', 1)
                ->where(function ($query) use ($tablePromotion) {
                    $query->where($tablePromotion . '.date_end', '>=', date("Y-m-d"))
                        ->orWhereNull($tablePromotion . '.date_end');
                })
                ->where(function ($query) use ($tablePromotion) {
                    $query->where($tablePromotion . '.date_start', '<=', date("Y-m-d H:i:s"))
                        ->orWhereNull($tablePromotion . '.date_start');
                });
        }

        // Apply other filters in subquery
        if (count($this->gp247_array_ID)) {
            $subQuery = $subQuery->whereIn($this->getTable().'.id', $this->gp247_array_ID);
        }

        $subQuery = $subQuery->where($this->getTable().'.status', 1);
        $subQuery = $subQuery->where($this->getTable().'.approve', 1);

        if ($this->gp247_kind !== []) {
            $subQuery = $subQuery->whereIn($this->getTable().'.kind', $this->gp247_kind);
        }

        //Filter with tag
        if ($this->gp247_tag !== 'all') {
            $subQuery = $subQuery->where($this->getTable().'.tag', $this->gp247_tag);
        }
        //Filter with brand
        if (count($this->gp247_brand)) {
            $subQuery = $subQuery->whereIn($this->getTable().'.brand_id', $this->gp247_brand);
        }
        //Filter with range price
        if ($this->gp247_range_price) {
            $price = explode('__', $this->gp247_range_price);
            $rangePrice['min'] = $price[0] ?? 0;
            $rangePrice['max'] = $price[1] ?? 0;
            if ($rangePrice['max']) {
                $subQuery = $subQuery->whereBetween($this->getTable().'.price', $rangePrice);
            }
        }
        //Filter with supplier
        if (count($this->gp247_supplier)) {
            $subQuery = $subQuery->whereIn($this->getTable().'.supplier_id', $this->gp247_supplier);
        }

        //Hidden product out of stock
        if (empty(gp247_config('product_display_out_of_stock', $storeId)) && !empty(gp247_config('product_stock', $storeId))) {
            $subQuery = $subQuery->where($this->getTable().'.stock', '>', 0);
        }

        // Get unique product IDs
        $subQuery = $subQuery->distinct($this->getTable().'.id');

        // Now build the main query with the filtered product IDs
        $query = $this->whereIn($this->getTable().'.id', $subQuery);
        
        //Select field - remove store_id from select to prevent duplicates
        $dataSelect = $this->getTable().'.*, '.$tableDescription.'.name, '.$tableDescription.'.keyword, '.$tableDescription.'.description';

        //description
        $query = $query
            //join description
            ->leftJoin($tableDescription, $tableDescription . '.product_id', $this->getTable() . '.id')
            ->where($tableDescription . '.lang', gp247_get_locale());

        //search keyword
        if ($this->gp247_keyword !='') {
            $query = $query->where(function ($sql) use ($tableDescription) {
                $sql->where($tableDescription . '.name', 'like', '%' . $this->gp247_keyword . '%')
                    ->orWhere($tableDescription . '.keyword', 'like', '%' . $this->gp247_keyword . '%')
                    ->orWhere($tableDescription . '.description', 'like', '%' . $this->gp247_keyword . '%')
                    ->orWhere($this->getTable() . '.sku', 'like', '%' . $this->gp247_keyword . '%');
            });
        }

        $query = $query->selectRaw($dataSelect);
        $query = $query->with('promotionPrice');
        $query = $query->with('stores');

        $query = $this->processMoreQuery($query);

        if ($this->gp247_random) {
            $query = $query->inRandomOrder();
        } else {
            $checkSort = false;
            $ckeckId = false;
            if (is_array($this->gp247_sort) && count($this->gp247_sort)) {
                foreach ($this->gp247_sort as  $rowSort) {
                    if (is_array($rowSort) && count($rowSort) == 2) {
                        if ($rowSort[0] == 'sort') {
                            //Process sort with sort value
                            $query = $query->orderBy($this->getTable().'.sort', $rowSort[1]);
                            $checkSort = true;
                        } elseif ($rowSort[0] == 'id' || $rowSort[0] == 'new') {
                            //Process sort with product id
                            $query = $query->orderBy($this->getTable().'.created_at', $rowSort[1]);
                            $ckeckId = true;
                        } else {
                            $query = $query->orderBy($rowSort[0], $rowSort[1]);
                        }
                    }
                }
            }
            //Use field "sort" if haven't above
            if (empty($checkSort)) {
                $query = $query->orderBy($this->getTable().'.sort', 'asc');
            }
            //Default, will sort id
            if (!$ckeckId) {
                $query = $query->orderBy($this->getTable().'.created_at', 'desc');
            }
        }

        return $query;
    }

    /**
     * Get tax ID
     *
     * @return  [type]  [return description]
     */
    public function getTaxId()
    {
        if (!ShopTax::checkStatus()) {
            return 0;
        }
        if ($this->tax_id == 'auto') {
            return ShopTax::checkStatus();
        } else {
            $arrTaxList = ShopTax::getListAll();
            if ($this->tax_id == 0 || !$arrTaxList->has($this->tax_id)) {
                return 0;
            }
        }
        return $this->tax_id;
    }

    /**
     * Get value tax (%)
     *
     * @return  [type]  [return description]
     */
    public function getTaxValue()
    {
        $taxId = $this->getTaxId();
        if ($taxId) {
            $arrValue = ShopTax::getArrayValue();
            return $arrValue[$taxId] ?? 0;
        } else {
            return 0;
        }
    }

    /**
     * Go to shop vendor
     *
     * @return  [type]  [return description]
     */
    public function goToShop($code = null)
    {
        return gp247_path_vendor($code);
    }

    /**
     * Show link to vendor
     *
     * @return void
     */
    public function displayVendor()
    {
        if ((gp247_store_check_multi_partner_installed()) && config('app.storeId') == GP247_STORE_ID_ROOT) {
            $subPath = 'shop_vendor.display_vendor';
            $view = gp247_shop_process_view('GP247TemplatePath::' . gp247_store_info('template'), $subPath);
            gp247_check_view($view);
            $vendorCode = $this->stores()->first()->code;
            $vendorUrl = $this->goToShop($vendorCode);
            return  view(
                $view,
                [
                    'vendorCode' => $vendorCode,
                    'vendorUrl' => $vendorUrl,
                ]
            )->render();
        }
    }
}
