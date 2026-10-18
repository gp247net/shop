<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent upgrade: labels of the shipping readiness checks shown in the "Check before
 * use" box of shipping plugin settings screens (US-shop-shipping-readiness-check). The
 * warnings shared with the Shipping tab (admin.shipping.warn_*) already exist;
 * insertOrIgnore keeps any text a site owner edited.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-readiness-check
 */
return new class extends Migration
{
    /**
     * Label rows shipped by this migration.
     *
     * @return array<int, array<string, string>>
     */
    public static function rows(): array
    {
        $p = 'admin.shipping';
        $labels = [
            'warn_shipping_off' => ['Vận chuyển đang tắt: khách không chọn được cách giao hàng ở trang thanh toán.', 'Shipping is off: shoppers cannot choose a delivery method at checkout.'],
            'warn_provider_off' => ['Hãng vận chuyển này chưa được bật cho cửa hàng này.', 'This carrier is not enabled for this store.'],
            'ready_shipping_on' => ['Vận chuyển đang bật ở trang thanh toán.', 'Shipping is on at checkout.'],
            'ready_city' => ['Địa chỉ khách có ô Tỉnh/Thành phố.', 'Customer addresses ask for the province / city.'],
            'ready_ward' => ['Địa chỉ khách có ô Phường/Xã hoặc Quận/Huyện.', 'Customer addresses ask for the ward or district.'],
            'ready_origin' => ['Kho lấy hàng đã có tỉnh, địa chỉ chi tiết và số điện thoại.', 'The pick-up warehouse has a province, street address and phone.'],
            'ready_provider' => ['Hãng vận chuyển này đang bật cho cửa hàng.', 'This carrier is enabled for this store.'],
            'ready_any_provider' => ['Đã có hãng vận chuyển bật cho cửa hàng.', 'At least one carrier is enabled for this store.'],
            'fix_shipping_tab' => ['Cấu hình cửa hàng → Vận chuyển', 'Shop configuration → Shipping'],
            'fix_customer_tab' => ['Cấu hình cửa hàng → Khách hàng', 'Shop configuration → Customer'],
            'fix_plugins' => ['Phần mở rộng → Tiện ích', 'Extension → Plugins'],
        ];
        $rows = [];
        foreach ($labels as $key => [$vi, $en]) {
            $rows[] = ['code' => $p . '.' . $key, 'text' => $vi, 'position' => $p, 'location' => 'vi'];
            $rows[] = ['code' => $p . '.' . $key, 'text' => $en, 'position' => $p, 'location' => 'en'];
        }

        return $rows;
    }

    /**
     * @return void
     */
    public function up()
    {
        DB::connection(GP247_DB_CONNECTION)
            ->table(GP247_DB_PREFIX . 'languages')
            ->insertOrIgnore(self::rows());
    }

    /**
     * WARNING: removes these label rows, including any text a site owner edited.
     *
     * @return void
     */
    public function down()
    {
        DB::connection(GP247_DB_CONNECTION)
            ->table(GP247_DB_PREFIX . 'languages')
            ->whereIn('code', array_values(array_unique(array_column(self::rows(), 'code'))))
            ->delete();
    }
};
