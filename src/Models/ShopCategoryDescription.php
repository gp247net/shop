<?php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopCategoryDescription extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    protected $primaryKey = ['category_id', 'lang'];
    public $incrementing  = false;
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_category_description';
    protected $connection = GP247_DB_CONNECTION;
    protected $guarded    = [];
}
