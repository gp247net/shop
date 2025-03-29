<?php
#GP247\Shop\Modelss/ShopProductAttribute.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProductAttribute extends Model
{
    use \GP247\Core\Models\ModelTrait;
    public $timestamps    = false;
    public $table = GP247_DB_PREFIX.'shop_product_attribute';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;
    public function attGroup()
    {
        return $this->belongsTo(ShopAttributeGroup::class, 'attribute_group_id', 'id');
    }
}
