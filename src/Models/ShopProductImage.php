<?php
#GP247\Shop\Modelss/ShopProductImage.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProductImage extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;

    public $timestamps = false;
    public $table = GP247_DB_PREFIX.'shop_product_image';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    /*
    Get thumb
     */
    public function getThumb()
    {
        return gp247_image_get_path_thumb($this->image);
    }

    /*
    Get image
     */
    public function getImage()
    {
        return gp247_image_get_path($this->image);
    }

    protected static function boot()
    {
        parent::boot();
        // before delete() method call this
        static::deleting(
            function ($model) {
            //
            }
        );
        //Uuid
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = gp247_generate_id();
            }
        });
    }
}
