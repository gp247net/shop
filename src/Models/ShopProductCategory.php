<?php
#GP247\Shop\Modelss/ShopProductCategory.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProductCategory extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    protected $primaryKey = ['category_id', 'product_id'];
    public $incrementing  = false;
    protected $guarded    = [];
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_product_category';
    protected $connection = GP247_DB_CONNECTION;
}
