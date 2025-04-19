<?php
#GP247\Shop\Modelss/ShopProductDownload.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use GP247\Shop\Models\ShopProduct;

class ShopProductDownload extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;

    protected $primaryKey = ['download_path', 'product_id'];
    public $incrementing  = false;
    protected $guarded    = [];
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_product_download';
    protected $connection = GP247_DB_CONNECTION;
    
    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'product_id', 'id');
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
