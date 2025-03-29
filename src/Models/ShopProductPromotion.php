<?php
#GP247\Shop\Modelss/ShopProductPromotion.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use GP247\Shop\Models\ShopProduct;

class ShopProductPromotion extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    public $table = GP247_DB_PREFIX.'shop_product_promotion';
    protected $guarded    = [];
    protected $primaryKey = 'product_id';
    public $incrementing  = false;
    protected $connection = GP247_DB_CONNECTION;

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'product_id', 'id');
    }
}
