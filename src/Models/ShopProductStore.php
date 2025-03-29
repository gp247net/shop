<?php
#GP247\Shop\Modelss/ShopProductStore.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProductStore extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    protected $primaryKey = ['store_id', 'product_id'];
    public $incrementing  = false;
    protected $guarded    = [];
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_product_store';
    protected $connection = GP247_DB_CONNECTION;
}
