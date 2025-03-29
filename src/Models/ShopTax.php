<?php
#GP247\Shop\Modelss/ShopTax.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopTax extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    public $table = GP247_DB_PREFIX.'shop_tax';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    private static $getList = null;
    private static $status = null;
    private static $arrayId = null;
    private static $arrayValue = null;

    /**
     * Get list item
     *
     * @return  [type]  [return description]
     */
    public static function getListAll()
    {
        if (self::$getList === null) {
            $data = self::get()->pluck('name', 'id')->toArray();
            $data['none'] = gp247_language_render('admin.tax.dont_use');
            $data['auto'] = gp247_language_render('admin.tax.auto');
            self::$getList = $data;
        }
        return self::$getList;
    }

    /**
     * Get array ID
     *
     * @return  [type]  [return description]
     */
    public static function getArrayId()
    {
        if (self::$arrayId === null) {
            self::$arrayId = self::pluck('id')->all();
        }
        return self::$arrayId;
    }

    /**
     * Get array value
     *
     * @return  [type]  [return description]
     */
    public static function getArrayValue()
    {
        if (self::$arrayValue === null) {
            self::$arrayValue = self::pluck('value', 'id')->all();
        }
        return self::$arrayValue;
    }


    /**
     * Check status tax
     *
     * @return  [type]  [return description]
     */
    public static function checkStatus()
    {
        $arrTaxId = self::getArrayId();
        if (self::$status === null) {
            if (!gp247_config('product_tax') || gp247_config('product_tax') == 'none') {
                $status = 0;
            } else {
                if (!in_array(gp247_config('product_tax'), $arrTaxId)) {
                    $status = 0;
                } else {
                    $status = gp247_config('product_tax');
                }
            }
            self::$status = $status;
        }
        return self::$status;
    }
}
