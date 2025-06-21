<?php
namespace GP247\Shop\Models;

use GP247\Shop\Models\ShopCategoryDescription;
use GP247\Shop\Models\ShopProduct;
use GP247\Core\Models\AdminStore;
use Illuminate\Database\Eloquent\Model;


class ShopCategory extends Model
{
    
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;
    
    public $table = GP247_DB_PREFIX . 'shop_category';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    protected $gp247_parent = ''; // category id parent
    protected $gp247_top = 'all'; // 1 - category display top, 0 -non top, all - all

    public function products()
    {
        return $this->belongsToMany(ShopProduct::class, GP247_DB_PREFIX . 'shop_product_category', 'category_id', 'product_id');
    }
    public function stores()
    {
        return $this->belongsToMany(AdminStore::class, ShopCategoryStore::class, 'category_id', 'store_id');
    }

    public function descriptions()
    {
        return $this->hasMany(ShopCategoryDescription::class, 'category_id', 'id');
    }
    //Function get text description
    public function getText()
    {
        return $this->descriptions()->where('lang', gp247_get_locale())->first();
    }
    public function getTitle()
    {
        return $this->getText()->title ?? '';
    }
    public function getDescription()
    {
        return $this->getText()->description ?? '';
    }
    public function getKeyword()
    {
        return $this->getText()->keyword?? '';
    }
    //End  get text description

    /**
     * Get category parent
     */
    public function getParent()
    {
        return $this->getDetail($this->parent);
    }

    protected static function boot()
    {
        parent::boot();
        // before delete() method call this
        static::deleting(function ($category) {
            //Delete category descrition
            $category->descriptions()->delete();
            $category->products()->detach();
            $category->stores()->detach();

            //Delete custom field
            (new \GP247\Core\Models\AdminCustomFieldDetail)
            ->join(GP247_DB_PREFIX.'admin_custom_field', GP247_DB_PREFIX.'admin_custom_field.id', GP247_DB_PREFIX.'admin_custom_field_detail.custom_field_id')
            ->where(GP247_DB_PREFIX.'admin_custom_field_detail.rel_id', $category->id)
            ->where(GP247_DB_PREFIX.'admin_custom_field.type', 'shop_category')
            ->delete();


        });
        //Uuid
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = gp247_generate_id();
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

    public function getUrl($lang = null)
    {
        return gp247_route_front('category.detail', ['alias' => $this->alias, 'lang' => $lang ?? app()->getLocale()]);
    }

    
    public function scopeSort($query, $sortBy = null, $sortOrder = 'asc')
    {
        $sortBy = $sortBy ?? 'sort';
        return $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Get categoy detail
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
        $tableDescription = (new ShopCategoryDescription)->getTable();
        $dataSelect = $this->getTable().'.*, '.$tableDescription.'.*';
        $category = $this->selectRaw($dataSelect)
            ->leftJoin($tableDescription, $tableDescription . '.category_id', $this->getTable() . '.id')
            ->where($tableDescription . '.lang', gp247_get_locale());

        if (gp247_store_check_multi_store_installed()) {
            $tableCategoryStore = (new ShopCategoryStore)->getTable();
            $tableStore = (new AdminStore)->getTable();
            $category = $category->join($tableCategoryStore, $tableCategoryStore.'.category_id', $this->getTable() . '.id');
            $category = $category->join($tableStore, $tableStore . '.id', $tableCategoryStore.'.store_id');
            $category = $category->where($tableStore . '.status', '1');
            $category = $category->where($tableCategoryStore.'.store_id', $storeId);
        }

        if ($type === null) {
            $category = $category->where($this->getTable().'.id', $key);
        } else {
            $category = $category->where($type, $key);
        }
        if ($checkActive) {
            $category = $category->where($this->getTable() .'.status', 1);
        }
        return $category->first();
    }
    


    /**
     * Start new process get data
     *
     * @return  new model
     */
    public function start()
    {
        return new ShopCategory;
    }

    /**
     * Set category parent
     */
    public function setParent($parent)
    {
        $this->gp247_parent = $parent;
        return $this;
    }

    /**
     * Set top value
     */
    private function setTop($top)
    {
        if ($top === 'all') {
            $this->gp247_top = $top;
        } else {
            $this->gp247_top = (int)$top ? 1 : 0;
        }
        return $this;
    }

    /**
     * Category root
     */
    public function getCategoryRoot()
    {
        $this->setParent(0);
        return $this;
    }

    /**
     * Category top
     */
    public function getCategoryTop()
    {
        $this->setTop(1);
        return $this;
    }

    /**
     * build Query
     */
    public function buildQuery()
    {
        $storeId = config('app.storeId');
        $tableDescription = (new ShopCategoryDescription)->getTable();
        $dataSelect = $this->getTable().'.*, '.$tableDescription.'.*';
        //description
        $query = $this->selectRaw($dataSelect)
            ->leftJoin($tableDescription, $tableDescription . '.category_id', $this->getTable() . '.id')
            ->where($tableDescription . '.lang', gp247_get_locale());
        //search keyword
        if ($this->gp247_keyword !='') {
            $query = $query->where(function ($sql) use ($tableDescription) {
                $sql->where($tableDescription . '.title', 'like', '%' . $this->gp247_keyword . '%')
                ->orWhere($tableDescription . '.keyword', 'like', '%' . $this->gp247_keyword . '%')
                ->orWhere($tableDescription . '.description', 'like', '%' . $this->gp247_keyword . '%');
            });
        }

        if (gp247_store_check_multi_store_installed()) {
            $tableCategoryStore = (new ShopCategoryStore)->getTable();
            $tableStore = (new AdminStore)->getTable();
            $query = $query->join($tableCategoryStore, $tableCategoryStore.'.category_id', $this->getTable() . '.id');
            $query = $query->join($tableStore, $tableStore . '.id', $tableCategoryStore.'.store_id');
            $query = $query->where($tableStore . '.status', '1');
            $query = $query->where($tableCategoryStore.'.store_id', $storeId);
        }

        $query = $query->where($this->getTable().'.status', 1);

        if ($this->gp247_parent !== '') {
            $query = $query->where($this->getTable().'.parent', $this->gp247_parent);
        }

        if ($this->gp247_top !== 'all') {
            $query = $query->where($this->getTable().'.top', $this->gp247_top);
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

    /**
     * Get list id sub categories, including itself
     *
     * @param   string  $cId  [$cId description]
     *
     * @return  array         [return description]
     */
    public function getListSub(string $cId):array {
        $arrayReturn = [$cId];
        $arrayMid = $this->where('parent', $cId)->pluck('id')->toArray();
        $arraySmall = $this->whereIn('parent', $arrayMid)->pluck('id')->toArray();
        return array_merge($arrayReturn, $arrayMid, $arraySmall);
    }
}
