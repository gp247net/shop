<?php
#GP247\Shop\Models\ShopBrandStore.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopBrandStore extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    protected $primaryKey = ['store_id', 'brand_id'];
    public $incrementing  = false;
    protected $guarded    = [];
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_brand_store';
    protected $connection = GP247_DB_CONNECTION;
}
