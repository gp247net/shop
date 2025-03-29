<?php
namespace GP247\Shop\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotice extends Model
{
    public $table = GP247_DB_PREFIX.'admin_notice';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    /**
     * Get count notice new
     *
     * @return  [type]  [return description]
     */
    public static function getCountNoticeNew()
    {
        return self::where('status', 0)
        ->where('admin_id', admin()->user()->id)
        ->count();
    }

    /**
     * Get count notice new
     *
     * @return  [type]  [return description]
     */
    public static function getTopNotice()
    {
        return self::where('admin_id', admin()->user()->id)
        ->orderBy('id','desc')
        ->limit(10)
        ->get();
    }

    
    /**
     * [getNoticeListAdmin description]
     *
     * @return  [type]  [return description]
     */
    public function getNoticeListAdmin()
    {
        return $this->where('admin_id', admin()
        ->user()->id)
        ->orderBy('id', 'desc')
        ->paginate(20);
    }
}
