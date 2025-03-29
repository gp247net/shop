<?php
namespace GP247\Shop\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class AdminTemplate extends Model
{
    public $table = GP247_DB_PREFIX.'admin_template';
    protected $guarded = [];
    protected $connection = GP247_DB_CONNECTION;

    /**
     * Get list template installed
     *
     * @return void
     */
    public function getListTemplate()
    {
        return $this->pluck('name', 'key')
            ->all();
    }
}
