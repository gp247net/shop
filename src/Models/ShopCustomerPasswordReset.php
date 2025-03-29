<?php
#GP247\Shop\Modelss/ShopCustomerPasswordReset.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopCustomerPasswordReset extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    protected $primaryKey = ['token'];
    public $incrementing  = false;
    protected $guarded    = [];
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_password_resets';
    protected $connection = GP247_DB_CONNECTION;
}
