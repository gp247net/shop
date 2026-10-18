<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent upgrade: labels of the payment readiness checks shown in the "Check before
 * use" box of payment plugin settings screens (US-payment-request-plugin-readiness-check).
 * insertOrIgnore keeps any text a site owner edited.
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-plugin-readiness-check
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
        $p = 'admin.payment';
        $labels = [
            'warn_payment_off' => ['Bước thanh toán đang tắt ở trang thanh toán: khách không được hỏi cách trả tiền.', 'The payment step is off at checkout: shoppers are never asked how to pay.'],
            'ready_payment_on' => ['Bước thanh toán đang bật ở trang thanh toán.', 'The payment step is on at checkout.'],
            'warn_provider_off' => ['Phương thức thanh toán này chưa được bật cho cửa hàng này.', 'This payment method is not enabled for this store.'],
            'ready_provider' => ['Phương thức thanh toán này đang bật cho cửa hàng.', 'This payment method is enabled for this store.'],
            'fix_order_tab' => ['Cấu hình cửa hàng → Đơn hàng', 'Shop configuration → Order'],
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
