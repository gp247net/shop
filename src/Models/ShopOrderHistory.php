<?php
#GP247\Shop\Modelss/ShopOrderHistory.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopOrderHistory extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;

    public $table = GP247_DB_PREFIX.'shop_order_history';
    protected $connection = GP247_DB_CONNECTION;
    const CREATED_AT = 'add_date';
    const UPDATED_AT = null;
    protected $guarded           = [];

    protected static function boot()
    {
        parent::boot();
        // before delete() method call this
        static::deleting(
            function ($obj) {
                //
            }
        );

        //Uuid
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = gp247_generate_id('ODH');
            }
        });
    }
}
