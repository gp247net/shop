<?php
#GP247\Shop\Models\ShopShippingStatus.php
namespace GP247\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ShopShippingStatus extends Model
{
    use \GP247\Core\Models\ModelTrait;
    
    public $table = GP247_DB_PREFIX.'shop_shipping_status';
    protected $guarded           = [];
    protected static $listStatus = null;
    protected $connection = GP247_DB_CONNECTION;

    /**
     * The seeded shipping statuses, by id.
     *
     * Not a new assumption: ShippingStatusManager::protectedMap() already hardcoded
     * these four ids and refuses to let an admin delete them, so the meaning of each
     * number has been enforced all along — it was simply written down in a screen
     * rather than in the model that owns it (same correction as ShopOrderStatus).
     *
     * Admins may still rename them, so these are for logic only; anything displayed
     * comes from getIdAll().
     */
    public const NOT_SENT = 1;
    public const SENDING = 2;
    public const SHIPPING_DONE = 3;
    public const REFUNDED = 4;

    /**
     * The shipping statuses, id => default name, in seeded order.
     *
     * @return array<int, string> Ids that carry meaning to the code, with their seed names.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-delete-money-guard
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public static function businessIds(): array
    {
        return [
            self::NOT_SENT => 'Not sent',
            self::SENDING => 'Sending',
            self::SHIPPING_DONE => 'Shipping done',
            self::REFUNDED => 'Refunded',
        ];
    }

    /**
     * Whether this status means the goods have physically left the warehouse.
     *
     * `Refunded` is deliberately NOT included: it means a shipment came back, so the
     * goods are ours again.
     *
     * @param int $status Shipping-status id on an order.
     * @return bool True once the goods are out with the customer or the courier.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-delete-money-guard
     * @aidlc-adr shop-admin_order-cancel-vs-delete
     */
    public static function goodsHaveLeft($status): bool
    {
        return in_array((int) $status, [self::SENDING, self::SHIPPING_DONE], true);
    }
    public static function getIdAll()
    {
        if (!self::$listStatus) {
            self::$listStatus = self::pluck('name', 'id')->all();
        }
        return self::$listStatus;
    }
}
