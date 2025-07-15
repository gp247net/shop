<?php
namespace GP247\Shop\Models;

use GP247\Shop\Models\ShopProduct;
use Illuminate\Database\Eloquent\Model;
use GP247\Core\Models\AdminStore;


class ShopBrand extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;

    public $table = GP247_DB_PREFIX.'shop_brand';
    protected $guarded = [];
    private static $getList = null;
    protected $connection = GP247_DB_CONNECTION;


    public static function getListAll()
    {
        if (self::$getList === null) {
            self::$getList = self::get()->keyBy('id');
        }
        return self::$getList;
    }

    public function products()
    {
        return $this->hasMany(ShopProduct::class, 'brand_id', 'id');
    }


    protected static function boot()
    {
        parent::boot();
        // before delete() method call this
        static::deleting(function ($brand) {

            //Delete custom field
            (new \GP247\Core\Models\AdminCustomFieldDetail)
            ->join(GP247_DB_PREFIX.'admin_custom_field', GP247_DB_PREFIX.'admin_custom_field.id', GP247_DB_PREFIX.'admin_custom_field_detail.custom_field_id')
            ->where(GP247_DB_PREFIX.'admin_custom_field_detail.rel_id', $brand->id)
            ->where(GP247_DB_PREFIX.'admin_custom_field.type', 'shop_brand')
            ->delete();
        });

        //Uuid
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = gp247_generate_id();
            }
        });
    }

    /**
     * [getUrl description]
     * @return [type] [description]
     */
    public function getUrl($lang = null)
    {
        return gp247_route_front('brand.detail', ['alias' => $this->alias, 'lang' => $lang ?? app()->getLocale()]);
    }

    /*
    Get thumb
    */
    public function getThumb()
    {
        return gp247_image_get_path_thumb($this->image);
    }

    /*
    Get image
    */
    public function getImage()
    {
        return gp247_image_get_path($this->image);
    }


    public function scopeSort($query, $sortBy = null, $sortOrder = 'asc')
    {
        $sortBy = $sortBy ?? 'sort';
        return $query->orderBy($sortBy, $sortOrder);
    }
    

    /**
     * Get page detail
     *
     * @param   [string]  $key     [$key description]
     * @param   [string]  $type  [id, alias]
     * @param   [int]  $checkActive
     *
     */
    public function getDetail($key, $type = null, $checkActive = 1)
    {
        if (empty($key)) {
            return null;
        }
        $storeId = config('app.storeId');
        $dataSelect = $this->getTable().'.*';
        $data = $this->selectRaw($dataSelect);
        if ($type === null) {
            $data = $data->where($this->getTable().'.id', $key);
        } else {
            $data = $data->where($type, $key);
        }
        if (gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) {
            $tableBrandStore = (new ShopBrandStore)->getTable();
            $tableStore = (new AdminStore)->getTable();
            $data = $data->join($tableBrandStore, $tableBrandStore.'.brand_id', $this->getTable() . '.id');
            $data = $data->join($tableStore, $tableStore . '.id', $tableBrandStore.'.store_id');
            $data = $data->where($tableStore . '.status', '1');
            $data = $data->where($tableBrandStore.'.store_id', $storeId);
        }
        if ($checkActive) {
            $data = $data->where($this->getTable() .'.status', 1);
        }
        return $data->first();
    }


    /**
     * Start new process get data
     *
     * @return  new model
     */
    public function start()
    {
        return new ShopBrand;
    }

    /**
     * build Query
     */
    public function buildQuery()
    {
        $storeId = config('app.storeId');
        $dataSelect = $this->getTable().'.*';
        $query = $this->selectRaw($dataSelect)
            ->where($this->getTable().'.status', 1);

        if (gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) {
            $tableBrandStore = (new ShopBrandStore)->getTable();
            $tableStore = (new AdminStore)->getTable();
            $query = $query->join($tableBrandStore, $tableBrandStore.'.brand_id', $this->getTable() . '.id');
            $query = $query->join($tableStore, $tableStore . '.id', $tableBrandStore.'.store_id');
            $query = $query->where($tableStore . '.status', '1');
            $query = $query->where($tableBrandStore.'.store_id', $storeId);
        }

        $query = $this->processMoreQuery($query);
        
        if ($this->gp247_random) {
            $query = $query->inRandomOrder();
        } else {
            $checkSort = false;
            if (is_array($this->gp247_sort) && count($this->gp247_sort)) {
                foreach ($this->gp247_sort as  $rowSort) {
                    if (is_array($rowSort) && count($rowSort) == 2) {
                        if ($rowSort[0] == 'sort') {
                            $checkSort = true;
                        }
                        $query = $query->sort($rowSort[0], $rowSort[1]);
                    }
                }
            }
            //Use field "sort" if haven't above
            if (empty($checkSort)) {
                $query = $query->orderBy($this->getTable().'.sort', 'asc');
            }
            //Default, will sort id
            $query = $query->orderBy($this->getTable().'.created_at', 'desc');
        }

        return $query;
    }
}
