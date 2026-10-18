<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Standard shipping layer (mod 20261017T185906) — everything the shop needs, in one
 * idempotent, add-only migration:
 *  - tables shop_shipment + shop_shipment_event (shipments and their carrier events);
 *  - shop_order.shipping_service + shop_order.shipping_rate (snapshot of the chosen rate);
 *  - `ward` on the three address blocks (shop_order, shop_customer, shop_customer_address);
 *  - default-off config rows (customer_ward, shipping_config group) and labels.
 *
 * Run by gp247:shop-install (fresh install, after the shop tables and seeders) and by
 * gp247:shop-update (existing sites). Every step is guarded (hasTable / hasColumn,
 * insertOrIgnore on natural keys): re-running is a no-op, nothing an owner changed is
 * overwritten, no existing row is modified or removed (data-safety §C — no data-loss
 * operation). gp247:shop-uninstall never calls down().
 *
 * @aidlc-unit shop-shipping
 * @aidlc-unit compat-foundation
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 * @aidlc-story US-shop-shipping-rate-snapshot
 * @aidlc-story US-shop-shipping-origin-config
 * @aidlc-story US-CMP-address-ward
 * @aidlc-adr shop-shipping_shipment-entity
 * @aidlc-adr compat-foundation_address-ward
 */
return new class extends Migration
{
    /** @var string[] Tables carrying the denormalized customer address block. */
    private array $addressTables = ['shop_order', 'shop_customer', 'shop_customer_address'];

    /**
     * @return void
     */
    public function up()
    {
        $this->createTables();
        $this->addColumns();
        $db = DB::connection(GP247_DB_CONNECTION);
        $db->table(GP247_DB_PREFIX . 'admin_config')->insertOrIgnore(self::configRows());
        $db->table(GP247_DB_PREFIX . 'languages')->insertOrIgnore(self::labels());
    }

    /**
     * WARNING: drops shop_shipment / shop_shipment_event (every shipment and carrier event
     * recorded) and the added columns (ward values, rate snapshots) — irreversible data
     * loss for those columns. Config rows and labels are kept so an owner's edits survive.
     * Never called by gp247:shop-uninstall.
     *
     * @return void
     */
    public function down()
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);
        $schema->dropIfExists(GP247_DB_PREFIX . 'shop_shipment_event');
        $schema->dropIfExists(GP247_DB_PREFIX . 'shop_shipment');

        $order = GP247_DB_PREFIX . 'shop_order';
        foreach (['shipping_service', 'shipping_rate'] as $column) {
            if ($schema->hasColumn($order, $column)) {
                $schema->table($order, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
        foreach ($this->addressTables as $table) {
            $full = GP247_DB_PREFIX . $table;
            if ($schema->hasColumn($full, 'ward')) {
                $schema->table($full, fn (Blueprint $t) => $t->dropColumn('ward'));
            }
        }
    }

    /**
     * The two shipment tables (add-only). Public so a test can create them on a database
     * that was never upgraded.
     *
     * @return void
     */
    public function createTables(): void
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);

        if (!$schema->hasTable(GP247_DB_PREFIX . 'shop_shipment')) {
            $schema->create(GP247_DB_PREFIX . 'shop_shipment', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id')->index();
                $table->string('store_id', 36)->index();
                $table->string('plugin_key', 100);
                $table->string('service_code', 100)->nullable();
                $table->string('tracking_number', 100)->nullable();
                $table->string('tracking_url', 500)->nullable();
                $table->string('provider_reference', 191)->nullable();
                $table->string('status', 30)->default('pending')->index()->comment('GP247\\Shop\\Shipping\\ShipmentStatus');
                $table->string('provider_status', 50)->nullable()->comment('carrier raw status code');
                $table->decimal('fee', 15, 2)->default(0)->comment('carrier fee, carrier currency');
                $table->decimal('cod_amount', 15, 2)->default(0)->comment('cash to collect, carrier currency');
                $table->string('currency', 10)->nullable()->comment('carrier currency');
                $table->unsignedInteger('weight_grams')->default(0);
                $table->text('request')->nullable()->comment('JSON copy of what was booked (no secrets)');
                $table->text('meta')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                // WHY unique: one tracking number per carrier — a replayed booking never
                // creates a second row (US-shop-shipping-shipment-lifecycle).
                $table->unique(['plugin_key', 'tracking_number'], 'shop_shipment_carrier_tracking_unique');
            });
        }

        if (!$schema->hasTable(GP247_DB_PREFIX . 'shop_shipment_event')) {
            $schema->create(GP247_DB_PREFIX . 'shop_shipment_event', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('shipment_id')->index();
                $table->string('status', 30)->nullable()->comment('normalised; null = informational');
                $table->string('provider_status', 50)->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->string('message', 500)->nullable();
                $table->string('source', 20)->default('sync')->comment('webhook | sync | admin');
                // WHY unique: a replayed webhook / repeated sync must be a no-op (MC-shipping-status-sync).
                $table->string('dedupe_key', 191)->unique();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Snapshot columns on the order and the ward column on the three address blocks.
     *
     * @return void
     */
    public function addColumns(): void
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);
        $order = GP247_DB_PREFIX . 'shop_order';
        $schema->table($order, function (Blueprint $table) use ($schema, $order) {
            if (!$schema->hasColumn($order, 'shipping_service')) {
                $table->string('shipping_service', 100)->nullable();
            }
            if (!$schema->hasColumn($order, 'shipping_rate')) {
                $table->text('shipping_rate')->nullable()->comment('JSON RateSnapshot of the chosen rate');
            }
        });

        foreach ($this->addressTables as $table) {
            $full = GP247_DB_PREFIX . $table;
            $schema->table($full, function (Blueprint $blueprint) use ($schema, $full) {
                if (!$schema->hasColumn($full, 'ward')) {
                    $blueprint->string('ward', 100)->nullable();
                }
            });
        }
    }

    /**
     * Default config rows (GLOBAL base; store rows override via the shop config screen).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function configRows(): array
    {
        $g = defined('GP247_STORE_ID_GLOBAL') ? GP247_STORE_ID_GLOBAL : '0';
        $row = static fn (string $code, string $key, string $value, int $sort, string $detail): array => [
            'group' => 'gp247_cart', 'code' => $code, 'key' => $key, 'value' => $value,
            'sort' => $sort, 'detail' => $detail, 'store_id' => $g,
        ];

        return [
            $row('customer_config_attribute', 'customer_ward', '0', 2, 'admin.customer.config_manager.ward'),
            $row('customer_config_attribute_required', 'customer_ward_required', '0', 2, ''),
            $row('shipping_config', 'shipping_origin_name', '', 1, 'admin.shipping.origin_name'),
            $row('shipping_config', 'shipping_origin_phone', '', 2, 'admin.shipping.origin_phone'),
            $row('shipping_config', 'shipping_origin_country', 'VN', 3, 'admin.shipping.origin_country'),
            $row('shipping_config', 'shipping_origin_province', '', 4, 'admin.shipping.origin_province'),
            $row('shipping_config', 'shipping_origin_district', '', 5, 'admin.shipping.origin_district'),
            $row('shipping_config', 'shipping_origin_ward', '', 6, 'admin.shipping.origin_ward'),
            $row('shipping_config', 'shipping_origin_address', '', 7, 'admin.shipping.origin_address'),
            $row('shipping_config', 'shipping_default_weight', '500', 8, 'admin.shipping.default_weight'),
            $row('shipping_config', 'shipping_sync_interval', '30', 9, 'admin.shipping.sync_interval'),
            $row('shipping_config', 'shipping_cod_auto_record', '0', 10, 'admin.shipping.cod_auto_record'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function labels(): array
    {
        $rows = [];
        $add = static function (string $code, string $vi, string $en) use (&$rows): void {
            $position = substr($code, 0, (int) strrpos($code, '.'));
            $rows[] = ['code' => $code, 'text' => $vi, 'position' => $position, 'location' => 'vi'];
            $rows[] = ['code' => $code, 'text' => $en, 'position' => $position, 'location' => 'en'];
        };

        // Ward (address block)
        $add('cart.ward', 'Phường/Xã', 'Ward');
        $add('customer.ward', 'Phường/Xã', 'Ward');
        $add('order.ward', 'Phường/Xã', 'Ward');
        $add('admin.customer.config_manager.ward', 'Sử dụng phường/xã', 'Use ward');

        // Checkout
        $add('cart.shipping_rate_none', 'Chưa có dịch vụ giao hàng cho địa chỉ này. Vui lòng kiểm tra lại địa chỉ hoặc liên hệ cửa hàng.', 'No delivery service is available for this address. Please check the address or contact the shop.');
        $add('cart.shipping_eta_days', 'Dự kiến :min–:max ngày', 'Estimated :min–:max days');
        $add('cart.shipping_rate_changed', 'Phí vận chuyển đã thay đổi, vui lòng chọn lại.', 'The shipping price has changed, please choose again.');

        // Shop config tab "Shipping" (US-shop-shipping-origin-config)
        $add('admin.shop.config_shipping', 'Vận chuyển', 'Shipping');
        $add('admin.shipping.status', 'Trạng thái vận chuyển', 'Shipping status');
        $add('admin.shipping.status_on', 'ĐANG BẬT', 'ON');
        $add('admin.shipping.status_off', 'ĐANG TẮT', 'OFF');
        $add('admin.shipping.status_off_note', 'Khách không thấy bước chọn vận chuyển và đơn không tính phí ship. Bạn vẫn có thể thiết lập trước bên dưới, rồi bật khi đã sẵn sàng.', 'Shoppers do not see a shipping step and orders carry no shipping fee. You can still set everything up below and switch on when ready.');
        $add('admin.shipping.enable', 'Bật vận chuyển khi đặt hàng', 'Enable shipping at checkout');
        $add('admin.shipping.hint_use_shipping', 'Hiện bước chọn hãng và dịch vụ giao hàng ở trang thanh toán. Thiết lập này trước đây nằm ở tab Đơn hàng.', 'Shows the carrier and service step at checkout. This setting used to be on the Order tab.');
        $add('admin.shipping.providers', 'Hãng vận chuyển đang bật cho cửa hàng này', 'Carriers enabled for this store');
        $add('admin.shipping.providers_none', 'Chưa có hãng vận chuyển nào được bật.', 'No carrier is enabled.');
        $add('admin.shipping.open_plugins', 'Mở danh sách Plugin để cài và bật', 'Open Plugins to install and enable one');
        foreach (['rates' => ['báo giá', 'quotes'], 'shipments' => ['tạo vận đơn', 'shipments'], 'cancel' => ['huỷ', 'cancel'],
                  'documents' => ['in nhãn', 'labels'], 'tracking' => ['theo dõi', 'tracking'], 'webhook' => ['cập nhật tự động', 'automatic updates'],
                  'cod' => ['thu hộ', 'cash on delivery']] as $cap => [$vi, $en]) {
            $add('admin.shipping.cap_' . $cap, $vi, $en);
        }
        $add('admin.shipping.warn_city', 'Địa chỉ khách chưa bật ô Tỉnh/Thành phố (tab Khách hàng). Hãng vận chuyển cần thông tin này để tính phí.', 'Customer addresses do not ask for City/Province (Customer tab). Carriers need it to quote.');
        $add('admin.shipping.warn_ward', 'Địa chỉ khách chưa bật ô Phường/Xã hoặc Quận/Huyện (tab Khách hàng). Các hãng tại Việt Nam bắt buộc thông tin này.', 'Customer addresses ask for neither Ward nor District (Customer tab). Vietnamese carriers require it.');
        $add('admin.shipping.warn_origin', 'Kho lấy hàng chưa đủ Tỉnh/Thành phố, Địa chỉ chi tiết và Số điện thoại (khối Kho lấy hàng bên dưới).', 'The pick-up warehouse lacks province, street address or phone (Pick-up warehouse block below).');
        $add('admin.shipping.warn_no_provider', 'Chưa bật hãng vận chuyển nào cho cửa hàng này — khách sẽ không có dịch vụ để chọn.', 'No carrier is enabled for this store — shoppers will have nothing to choose.');
        $add('admin.shipping.group_origin', 'Kho lấy hàng (địa chỉ người gửi)', 'Pick-up warehouse (sender address)');
        $add('admin.shipping.group_origin_intro', 'Hãng vận chuyển đến đây lấy hàng và tính phí từ địa chỉ này.', 'Carriers pick parcels up here and price shipping from this address.');
        $add('admin.shipping.group_parcel', 'Gói hàng', 'Parcel');
        $add('admin.shipping.group_tracking', 'Theo dõi và đối soát', 'Tracking and reconciliation');
        $add('admin.shipping.origin_name', 'Tên người gửi / tên cửa hàng', 'Sender / shop name');
        $add('admin.shipping.origin_phone', 'Số điện thoại kho', 'Warehouse phone');
        $add('admin.shipping.origin_country', 'Quốc gia (mã 2 chữ)', 'Country (2-letter code)');
        $add('admin.shipping.origin_province', 'Tỉnh/Thành phố', 'Province / city');
        $add('admin.shipping.origin_ward', 'Phường/Xã', 'Ward');
        $add('admin.shipping.origin_district', 'Quận/Huyện', 'District');
        $add('admin.shipping.origin_address', 'Địa chỉ chi tiết', 'Street address');
        $add('admin.shipping.default_weight', 'Khối lượng mặc định mỗi sản phẩm (gram)', 'Default weight per product (grams)');
        $add('admin.shipping.sync_interval', 'Tự cập nhật trạng thái vận đơn sau (phút)', 'Refresh shipment status after (minutes)');
        $add('admin.shipping.cod_auto_record', 'Tự ghi tiền thu hộ (COD) vào đơn khi hãng báo đã đối soát', 'Record COD cash on the order when the carrier reports it reconciled');
        $add('admin.shipping.hint_shipping_origin_name', 'In trên nhãn vận đơn. Ví dụ: Cửa hàng ABC', 'Printed on the shipping label. Example: ABC Store');
        $add('admin.shipping.hint_shipping_origin_phone', 'Shipper gọi số này khi đến lấy hàng. Ví dụ: 0901234567', 'The courier calls this number at pick-up. Example: 0901234567');
        $add('admin.shipping.hint_shipping_origin_country', 'Mã quốc gia theo chuẩn ISO, 2 chữ cái. Ví dụ: VN', 'ISO country code, 2 letters. Example: VN');
        $add('admin.shipping.hint_shipping_origin_province', 'Ghi tên đầy đủ theo địa giới mới. Ví dụ: TP. Hồ Chí Minh', 'Full name under the current division. Example: Ho Chi Minh City');
        $add('admin.shipping.hint_shipping_origin_ward', 'Ví dụ: Phường Sài Gòn', 'Example: Sai Gon Ward');
        $add('admin.shipping.hint_shipping_origin_district', 'Bỏ trống nếu địa phương đã bỏ cấp huyện (từ 01/07/2025).', 'Leave empty where districts no longer exist (since 2025-07-01).');
        $add('admin.shipping.hint_shipping_origin_address', 'Số nhà, tên đường. Ví dụ: 12 Lê Lợi', 'House number and street. Example: 12 Le Loi');
        $add('admin.shipping.hint_shipping_default_weight', 'Dùng cho sản phẩm chưa nhập khối lượng. Khối lượng sai làm phí hãng thu khác phí khách đã trả — nên nhập khối lượng thật cho từng sản phẩm.', 'Used for products without a weight. A wrong weight makes the carrier charge differ from what the shopper paid — set real weights on products.');
        $add('admin.shipping.hint_shipping_sync_interval', 'Khi mở đơn mà lần cập nhật trước đã quá số phút này, hệ thống hỏi lại hãng. Dùng khi webhook của hãng không gửi về được (ví dụ site chạy nội bộ). Từ 5 đến 1440.', 'When an order is opened and the last update is older than this, the carrier is asked again. Useful when carrier webhooks cannot reach the site (e.g. a local site). 5 to 1440.');
        $add('admin.shipping.hint_shipping_cod_auto_record', 'Chỉ bật nếu bạn đối chiếu tiền COD theo báo cáo của hãng. Mỗi vận đơn chỉ được ghi một lần.', 'Only if you reconcile COD cash from the carrier report. Each shipment is recorded once at most.');

        // Shipment panel
        $add('admin.shipment.title', 'Vận chuyển', 'Shipping');
        $add('admin.shipment.none', 'Đơn chưa có vận đơn.', 'No shipment yet.');
        $add('admin.shipment.create', 'Tạo vận đơn', 'Create shipment');
        $add('admin.shipment.create_title', 'Tạo vận đơn', 'Create shipment');
        $add('admin.shipment.provider', 'Hãng vận chuyển', 'Carrier');
        $add('admin.shipment.service', 'Dịch vụ', 'Service');
        $add('admin.shipment.tracking_number', 'Mã vận đơn', 'Tracking number');
        $add('admin.shipment.status', 'Trạng thái', 'Status');
        $add('admin.shipment.fee', 'Phí hãng', 'Carrier fee');
        $add('admin.shipment.fee_on_order', 'Phí trên đơn', 'Fee on the order');
        $add('admin.shipment.cod_amount', 'Tiền thu hộ (COD)', 'Cash on delivery');
        $add('admin.shipment.declared_value', 'Giá trị khai báo', 'Declared value');
        $add('admin.shipment.weight', 'Khối lượng (gram)', 'Weight (grams)');
        $add('admin.shipment.parcel_size', 'Kích thước kiện (cm): dài × rộng × cao', 'Parcel size (cm): length × width × height');
        $add('admin.shipment.parcel_size_hint', 'Số đo hộp sau khi đóng gói. Bỏ trống nếu chưa đo — hãng tính theo khối lượng và kích thước từng sản phẩm.', 'Measured box after packing. Leave empty if not measured — the carrier uses the weight and the size of each product.');
        $add('admin.shipment.length', 'Dài', 'Length');
        $add('admin.shipment.width', 'Rộng', 'Width');
        $add('admin.shipment.height', 'Cao', 'Height');
        $add('admin.shipment.note', 'Ghi chú cho hãng', 'Note to the carrier');
        $add('admin.shipment.recipient', 'Người nhận', 'Recipient');
        $add('admin.shipment.cancel', 'Huỷ vận đơn', 'Cancel shipment');
        $add('admin.shipment.cancel_confirm', 'Huỷ vận đơn này với hãng vận chuyển?', 'Cancel this shipment with the carrier?');
        $add('admin.shipment.label', 'Tải nhãn', 'Download label');
        $add('admin.shipment.sync', 'Đồng bộ', 'Sync');
        $add('admin.shipment.timeline', 'Lịch sử vận chuyển', 'Tracking history');
        $add('admin.shipment.close', 'Đóng', 'Close');
        $add('admin.shipment.error', 'Không thực hiện được thao tác vận chuyển. Xem nhật ký hệ thống.', 'The shipping action failed. See the system log.');
        $add('admin.shipment.no_provider', 'Chưa có hãng vận chuyển nào hỗ trợ tạo vận đơn.', 'No enabled carrier can book shipments.');
        $add('admin.shipment.active_exists', 'Đơn đã có vận đơn đang hiệu lực. Huỷ vận đơn đó trước khi tạo mới.', 'This order already has an active shipment. Cancel it first.');
        $add('admin.shipment.order_locked', 'Đơn đã khoá, không thao tác vận đơn được.', 'The order is locked; shipments cannot be changed.');
        $add('admin.shipment.created', 'Đã tạo vận đơn :tracking', 'Shipment :tracking created');
        $add('admin.shipment.cancelled', 'Đã huỷ vận đơn', 'Shipment cancelled');
        $add('admin.shipment.synced', 'Đã đồng bộ trạng thái', 'Status synchronised');
        $add('admin.shipment.fee_differs', 'Phí hãng (:carrier) khác phí trên đơn (:order).', 'Carrier fee (:carrier) differs from the fee on the order (:order).');
        $add('admin.shipment.history_created', 'Tạo vận đơn :carrier :tracking', 'Shipment :tracking booked with :carrier');
        $add('admin.shipment.history_status', 'Vận đơn :tracking: :status', 'Shipment :tracking: :status');
        $add('admin.shipment.history_cod', 'Ghi nhận tiền thu hộ :amount từ :carrier (:tracking)', 'COD :amount recorded from :carrier (:tracking)');
        $add('admin.shipment.history_cod_mismatch', 'Hãng báo thu hộ :carrier_amount khác số dư đơn :balance', 'Carrier reported :carrier_amount COD, order balance is :balance');
        foreach (['pending' => ['Chờ lấy hàng', 'Awaiting pick-up'], 'picked_up' => ['Đã lấy hàng', 'Picked up'],
                  'in_transit' => ['Đang vận chuyển', 'In transit'], 'out_for_delivery' => ['Đang giao', 'Out for delivery'],
                  'delivery_failed' => ['Giao thất bại', 'Delivery failed'], 'delivered' => ['Đã giao', 'Delivered'],
                  'returning' => ['Đang hoàn hàng', 'Returning'], 'returned' => ['Đã hoàn hàng', 'Returned'],
                  'cancelled' => ['Đã huỷ', 'Cancelled'], 'exception' => ['Ngoại lệ', 'Exception']] as $status => [$vi, $en]) {
            $add('admin.shipment.status_' . $status, $vi, $en);
        }

        return $rows;
    }
};
