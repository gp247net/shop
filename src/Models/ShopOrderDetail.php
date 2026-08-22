<?php
#GP247/Shop/Models/ShopOrderDetail.php
namespace GP247\Shop\Models;

use GP247\Shop\Models\ShopProduct;
use Illuminate\Database\Eloquent\Model;

class ShopOrderDetail extends Model
{
    use \GP247\Core\Models\ModelTrait;
    use \GP247\Core\Models\UuidTrait;
    
    protected $table = GP247_DB_PREFIX.'shop_order_detail';
    protected $connection = GP247_DB_CONNECTION;
    protected $guarded = [];

    /**
     * Cast the decimal(16,6) exchange_rate line snapshot to a number (Laravel
     * hands a raw decimal column back as a string), so consumers do arithmetic
     * on a numeric value (ADR compat-foundation_exchange-rate-precision).
     *
     * @var array<string, string>
     */
    protected $casts = ['exchange_rate' => 'float'];
    public function order()
    {
        return $this->belongsTo(ShopOrder::class, 'order_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'product_id', 'id');
    }

    public function updateDetail($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }
    public function addNewDetail(array $data)
    {
        if ($data) {
            // WHY: insert() is a query-builder write that bypasses the `creating`
            // model event, so the integrity guard must be applied here explicitly
            // for every row (NFR-MAINT-order-line-integrity-guard, US-SADM-order-line-integrity).
            foreach ($data as $item) {
                self::assertLineIntegrity($item);
            }
            $this->insert($data);
            //Update stock, sold
            foreach ($data as $key => $item) {
                //Update stock, sold
                ShopProduct::updateStock($item['product_id'], $item['qty']);
            }
        }
    }

    /**
     * Guard the minimum integrity of an order-detail row: every line must be tied
     * to a real product (non-empty product_id) and carry a non-empty name.
     *
     * Shared single source so no caller can persist an empty "ghost" line — an
     * empty product_id ('' or null) once slipped through because the column is
     * CHAR(36) NOT NULL (which does not forbid the empty string) with no foreign
     * key. Called from addNewDetail() (query-builder insert) and the `creating`
     * event (Eloquent create) so both write paths are covered.
     *
     * @param array<string, mixed> $row Order-detail attributes about to be written.
     * @return void
     * @throws \InvalidArgumentException When product_id or name is empty.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-line-integrity
     * @aidlc-adr shop-admin_order-line-integrity
     */
    private static function assertLineIntegrity(array $row): void
    {
        if (trim((string) ($row['product_id'] ?? '')) === '') {
            throw new \InvalidArgumentException('Order detail requires a non-empty product_id.');
        }
        if (trim((string) ($row['name'] ?? '')) === '') {
            throw new \InvalidArgumentException('Order detail requires a non-empty name.');
        }
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
            // WHY: last-resort integrity guard for the Eloquent create() path
            // (storefront ShopOrder::addOrderDetail) — mirrors the addNewDetail
            // guard so no channel can persist a productless line
            // (NFR-MAINT-order-line-integrity-guard).
            self::assertLineIntegrity([
                'product_id' => $model->product_id,
                'name' => $model->name,
            ]);

            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = gp247_generate_id('ODD');
            }
        });
    }
}
