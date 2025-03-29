<?php
#GP247\Shop\Modelss/ShopProductBuild.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use GP247\Shop\Models\ShopProduct;

class ShopProductBuild extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    protected $primaryKey = ['build_id', 'product_id'];
    public $incrementing  = false;
    protected $guarded    = [];
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_product_build';
    protected $connection = GP247_DB_CONNECTION;
    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'product_id', 'id');
    }
}
