<?php
#GP247/Shop/Models/ShopOrderStatus.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopOrderStatus extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    public $table = GP247_DB_PREFIX.'shop_order_status';
    protected $connection = GP247_DB_CONNECTION;
    protected $guarded           = [];
    protected static $listStatus = null;

    /**
     * The seeded statuses, by id.
     *
     * These are not a new assumption. OrderStatusManager::protectedMap() already
     * hardcoded all seven ids with their names and refuses to let an admin delete
     * them, so "id 4 means cancelled" has been enforced all along — it was simply
     * written down in a screen rather than in the model that owns it. Naming them
     * here puts the assumption in one place instead of two, the same correction made
     * for payment statuses (ADR shop-admin_payment-status-enum-alignment).
     *
     * Admins may still RENAME any of them, so these are for logic only; anything
     * displayed comes from getIdAll().
     */
    public const NEW = 1;
    public const PROCESSING = 2;
    public const HOLD = 3;
    public const CANCELED = 4;
    public const DONE = 5;
    public const FAILED = 6;
    public const REFUNDED = 7;

    /**
     * The business statuses, id => default name, in seeded order.
     *
     * @return array<int, string> Ids that carry meaning to the code, with their seed names.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-cancel-restock
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public static function businessIds(): array
    {
        return [
            self::NEW => 'New',
            self::PROCESSING => 'Processing',
            self::HOLD => 'Hold',
            self::CANCELED => 'Canceled',
            self::DONE => 'Done',
            self::FAILED => 'Failed',
            self::REFUNDED => 'Refunded',
        ];
    }

    public static function getIdAll()
    {
        if (!self::$listStatus) {
            self::$listStatus = self::pluck('name', 'id')->all();
        }
        return self::$listStatus;
    }
}
