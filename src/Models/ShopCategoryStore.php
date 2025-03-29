<?php
#GP247\Shop\Modelss/ShopCategoryStore.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopCategoryStore extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    protected $primaryKey = ['store_id', 'category_id'];
    public $incrementing  = false;
    protected $guarded    = [];
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_category_store';
    protected $connection = GP247_DB_CONNECTION;
}
