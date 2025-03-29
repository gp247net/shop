<?php
#GP247\Shop\Modelss/ShopProductDescription.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProductDescription extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    protected $primaryKey = ['lang', 'product_id'];
    public $incrementing  = false;
    protected $guarded    = [];
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_product_description';
    protected $connection = GP247_DB_CONNECTION;
}
