<?php
#GP247/Shop/Models/ShopTax.php
namespace GP247\Shop\Models;

use GP247\Core\Models\AdminStore;
use Illuminate\Database\Eloquent\Model;

class ShopTax extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    public $table = GP247_DB_PREFIX.'shop_tax';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    // WHY: value is decimal(8,4) since modification 20260803T223543 (ADR
    // shop-admin_tax-standardization, D1). Cast to float so reads/arithmetic keep
    // fractional percents (8.5, 8.375) and displays stay clean (no "8.5000" string).
    protected $casts = ['value' => 'float'];

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
     * The store that owns this tax rate (1-1 ownership).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-store-1to1-schema
     * @aidlc-adr multi-store_one-to-one-store-ownership
     */
    public function store()
    {
        return $this->belongsTo(AdminStore::class, 'store_id', 'id');
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
