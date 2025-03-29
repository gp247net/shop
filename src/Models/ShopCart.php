<?php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopCart extends Model
{
    protected $primaryKey = null;
    public $incrementing  = false;
    public $table = GP247_DB_PREFIX.'shop_shoppingcart';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;
}
