<?php
#GP247\Shop\Modelss/ShopEmailTemplate.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopEmailTemplate extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;
    
    public $table = GP247_DB_PREFIX.'shop_email_template';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    //Function get text description
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
                $model->{$model->getKeyName()} = gp247_generate_id($type = 'shop_email_template');
            }
        });
    }
}
