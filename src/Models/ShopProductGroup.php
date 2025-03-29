<?php
#GP247\Shop\Modelss/ShopProductGroup.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use GP247\Shop\Models\ShopProduct;

class ShopProductGroup extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    protected $primaryKey = ['group_id', 'product_id'];
    public $incrementing  = false;
    protected $guarded    = [];
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_product_group';
    protected $connection = GP247_DB_CONNECTION;

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'product_id', 'id');
    }
}
