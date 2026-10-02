<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment requests (mod 20260929T060454) — everything the shop needs, in one idempotent,
 * add-only migration: the two tables (requests + their money movements, with the payment
 * link token encrypted at rest), the admin menu entry, the two permissions (screen /
 * money out), and the labels of the admin screen, the public pay page and the order
 * purposes.
 *
 * Run by gp247:shop-install (fresh install, after the shop tables and seeders) and by
 * gp247:shop-update (existing sites). Tables are guarded by hasTable / hasColumn, menu
 * and permissions by their natural keys (uri, slug), labels by insertOrIgnore on
 * (code, location): nothing an owner already changed is overwritten, no row is modified
 * or removed. gp247:shop-uninstall never calls down(): the money ledger outlives a
 * shop reinstall.
 *
 * Table names, URLs, route names, permission slugs and label codes are unchanged from
 * the development build that lived in gp247/core, so a database that ran it needs
 * nothing more (this migration is then a no-op).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-generic-money-request
 * @aidlc-story US-payment-request-movement-ledger
 * @aidlc-story US-payment-request-admin-screen
 * @aidlc-story US-payment-request-public-pay-link
 * @aidlc-story US-payment-request-public-pay-page
 * @aidlc-story US-SADM-order-payment-request-balance
 * @aidlc-story US-SADM-order-payment-request-refund
 * @aidlc-adr payment-request_generic-money-request
 */
return new class extends Migration
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function labels(): array
    {
        return [
            // Admin screen (payment requests)
            ['code' => 'admin.menu_titles.payment_request','text' => 'Yêu cầu thanh toán','position' => 'admin.menu_titles','location' => 'vi'],
            ['code' => 'admin.menu_titles.payment_request','text' => 'Payment requests','position' => 'admin.menu_titles','location' => 'en'],
            ['code' => 'admin.payment_request.title','text' => 'Yêu cầu thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.title','text' => 'Payment requests','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.create_title','text' => 'Tạo yêu cầu thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.create_title','text' => 'New payment request','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.edit_title','text' => 'Yêu cầu thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.edit_title','text' => 'Payment request','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.list_title','text' => 'Danh sách yêu cầu','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.list_title','text' => 'Requests','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.search','text' => 'Tìm đối tác, email, mô tả, #','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.search','text' => 'Search party, email, description, #','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.direction','text' => 'Chiều tiền','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.direction','text' => 'Direction','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.direction_in','text' => 'Thu tiền vào','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.direction_in','text' => 'Money in (collect)','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.direction_out','text' => 'Chi tiền ra','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.direction_out','text' => 'Money out (pay / refund)','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.purpose','text' => 'Mục đích','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.purpose','text' => 'Purpose','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.purpose_free','text' => 'Khoản thu/chi tự do','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.purpose_free','text' => 'Free-form (no linked record)','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.purpose_unknown','text' => 'Mục đích này không khả dụng.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.purpose_unknown','text' => 'This purpose is not available.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.purpose_direction','text' => 'Mục đích này không cho phép chiều tiền đã chọn.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.purpose_direction','text' => 'This purpose does not allow this direction.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.amount','text' => 'Số tiền','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.amount','text' => 'Amount','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.currency','text' => 'Tiền tệ','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.currency','text' => 'Currency','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.currency_unknown','text' => 'Tiền tệ không hợp lệ.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.currency_unknown','text' => 'Unknown currency.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.money_locked','text' => 'Đã phát sinh tiền: không đổi được chiều, số tiền và tiền tệ nữa.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.money_locked','text' => 'Money has already moved: direction, amount and currency can no longer change.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.party_name','text' => 'Đối tác','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.party_name','text' => 'Party','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.party_email','text' => 'Email đối tác','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.party_email','text' => 'Party email','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.party_phone','text' => 'Điện thoại đối tác','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.party_phone','text' => 'Party phone','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.description','text' => 'Mô tả','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.description','text' => 'Description','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.subject','text' => 'Đối tượng liên kết','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.subject','text' => 'Linked record','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.subject_help','text' => 'Tuỳ chọn: mã đơn, hoá đơn, mã đối tác…','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.subject_help','text' => 'Optional: order number, invoice, partner code…','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.expires_at','text' => 'Hạn thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.expires_at','text' => 'Valid until','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.ledger_title','text' => 'Tiền đã di chuyển','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.ledger_title','text' => 'Money movements','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.settled','text' => 'Đã ghi nhận','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.settled','text' => 'Settled','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.no_movements','text' => 'Chưa có khoản tiền nào.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.no_movements','text' => 'No money has moved yet.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.movement_type','text' => 'Loại','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.movement_type','text' => 'Type','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.movement_collect','text' => 'Đã thu','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.movement_collect','text' => 'Collected','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.movement_payout','text' => 'Đã chi','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.movement_payout','text' => 'Paid out','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.movement_refund','text' => 'Đã hoàn','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.movement_refund','text' => 'Refunded','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.gateway','text' => 'Cổng','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.gateway','text' => 'Gateway','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.gateway_manual','text' => 'Ghi nhận tay (chuyển khoản, tiền mặt)','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.gateway_manual','text' => 'Recorded by hand (transfer, cash)','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.record_receipt','text' => 'Ghi nhận khoản thu','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.record_receipt','text' => 'Record a receipt by hand','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.record_payout','text' => 'Ghi nhận khoản chi','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.record_payout','text' => 'Record a payment out by hand','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.record_manual','text' => 'Ghi nhận','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.record_manual','text' => 'Record','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.manual_amount','text' => 'Số tiền','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.manual_amount','text' => 'Amount','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.manual_reference','text' => 'Mã tham chiếu','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.manual_reference','text' => 'Reference','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.manual_paid_at','text' => 'Ngày','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.manual_paid_at','text' => 'Date','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.manual_over','text' => 'Yêu cầu này chỉ còn :outstanding chưa ghi nhận.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.manual_over','text' => 'Only :outstanding is still outstanding on this request.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.manual_recorded','text' => 'Đã ghi nhận tiền.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.manual_recorded','text' => 'Money recorded.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.cancel_request','text' => 'Huỷ yêu cầu','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.cancel_request','text' => 'Cancel request','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.cancel_confirm','text' => 'Huỷ yêu cầu thanh toán này?','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.cancel_confirm','text' => 'Cancel this payment request?','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.cancelled','text' => 'Đã huỷ yêu cầu.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.cancelled','text' => 'Request cancelled.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.cannot_cancel_with_money','text' => 'Không huỷ được yêu cầu đã phát sinh tiền.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.cannot_cancel_with_money','text' => 'A request that already holds money cannot be cancelled.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.cannot_delete_with_money','text' => 'Không xoá được yêu cầu đã phát sinh tiền.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.cannot_delete_with_money','text' => 'A request that already holds money cannot be deleted.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.public_link','text' => 'Link thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.public_link','text' => 'Payment link','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.public_link_unavailable','text' => 'Link thanh toán: chưa khả dụng trên site này','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.public_link_unavailable','text' => 'Payment link: not available yet on this site','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.issue_link','text' => 'Tạo link thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.issue_link','text' => 'Create payment link','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.reissue_link','text' => 'Tạo lại link','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.reissue_link','text' => 'Re-issue link','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.reissue_confirm','text' => 'Link cũ sẽ không dùng được nữa. Tiếp tục?','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.reissue_confirm','text' => 'The old link will stop working. Continue?','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.copy_link','text' => 'Sao chép','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.copy_link','text' => 'Copy','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.public_link_help','text' => 'Gửi link này cho người trả tiền. Ai có link đều trả được khoản này — lộ link thì tạo lại.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.public_link_help','text' => 'Send this link to the payer. Anyone holding it can pay this request — re-issue it if it leaks.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.link_issued','text' => 'Đã tạo link thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.link_issued','text' => 'Payment link created','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.link_not_payable','text' => 'Yêu cầu này không còn nhận thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.link_not_payable','text' => 'This request no longer takes payments','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.refund_via_gateway','text' => 'Hoàn qua cổng','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.refund_via_gateway','text' => 'Refund via gateway','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.refund_amount','text' => 'Số tiền hoàn','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.refund_amount','text' => 'Refund amount','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.refund_submit','text' => 'Hoàn tiền','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.refund_submit','text' => 'Refund','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.refund_confirm','text' => 'Tiền sẽ được trả lại cho người đã thanh toán qua cổng. Tiếp tục?','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.refund_confirm','text' => 'The money will be sent back to the payer through the gateway. Continue?','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.refund_over','text' => 'Chỉ còn hoàn được :refundable','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.refund_over','text' => 'Only :refundable can still be refunded','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.refund_failed','text' => 'Cổng thanh toán từ chối lệnh hoàn — xem nhật ký lỗi','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.refund_failed','text' => 'The gateway refused the refund — see the error log','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.refund_recorded','text' => 'Đã hoàn tiền và ghi vào sổ','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.refund_recorded','text' => 'Refund sent and recorded','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.source_title','text' => 'Hoàn qua cổng khách đã thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.source_title','text' => 'Refund through the gateway the customer paid with','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.new_request','text' => 'Yêu cầu mới','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.new_request','text' => 'New request','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.audit_created','text' => 'Tạo bởi','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.audit_created','text' => 'Created by','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.audit_updated','text' => 'Cập nhật','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.audit_updated','text' => 'Updated','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.audit_cancelled','text' => 'Huỷ bởi','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.audit_cancelled','text' => 'Cancelled by','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.manual_confirm_in','text' => 'Đã nhận','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.manual_confirm_in','text' => 'Received','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.manual_confirm_out','text' => 'Đã chi','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.manual_confirm_out','text' => 'Paid out','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.manual_hint_in','text' => 'Bấm khi tiền đã về bằng chuyển khoản hoặc tiền mặt (không qua cổng online). Khoản này được ghi vào sổ ngay và không hoàn qua cổng được.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.manual_hint_in','text' => 'Click once the money has arrived by bank transfer or cash (not through an online gateway). It is recorded right away and cannot be refunded through a gateway.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.manual_hint_out','text' => 'Bấm sau khi đã chuyển khoản hoặc trả tiền mặt cho người nhận. Hệ thống chỉ ghi nhận, không tự chuyển tiền.','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.manual_hint_out','text' => 'Click after you have transferred or handed over the money. The system only records it; it does not send money.','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.status_open','text' => 'Chờ thanh toán','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.status_open','text' => 'Open','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.status_partially_settled','text' => 'Đã ghi nhận một phần','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.status_partially_settled','text' => 'Partly settled','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.status_settled','text' => 'Hoàn tất','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.status_settled','text' => 'Settled','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.status_cancelled','text' => 'Đã huỷ','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.status_cancelled','text' => 'Cancelled','position' => 'admin.payment_request','location' => 'en'],
            ['code' => 'admin.payment_request.status_expired','text' => 'Quá hạn','position' => 'admin.payment_request','location' => 'vi'],
            ['code' => 'admin.payment_request.status_expired','text' => 'Expired','position' => 'admin.payment_request','location' => 'en'],
            // Public pay page /pay/{token}
            ['code' => 'front.payment_request.title','text' => 'Thanh toán','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.title','text' => 'Payment','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.payer','text' => 'Người thanh toán','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.payer','text' => 'Payer','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.amount_due','text' => 'Số tiền cần thanh toán','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.amount_due','text' => 'Amount due','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.amount_total','text' => 'Tổng khoản yêu cầu','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.amount_total','text' => 'Requested total','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.expires_at','text' => 'Hạn thanh toán','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.expires_at','text' => 'Pay before','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.choose_gateway','text' => 'Chọn phương thức thanh toán','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.choose_gateway','text' => 'Choose a payment method','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.pay_now','text' => 'Thanh toán ngay','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.pay_now','text' => 'Pay now','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.not_payable','text' => 'Yêu cầu này không còn nhận thanh toán.','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.not_payable','text' => 'This request no longer takes payments.','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.gateway_error','text' => 'Không kết nối được cổng thanh toán, vui lòng thử lại sau.','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.gateway_error','text' => 'The payment gateway could not be reached, please try again later.','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.state_settled','text' => 'Khoản này đã được thanh toán. Cảm ơn bạn!','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.state_settled','text' => 'This has been paid. Thank you!','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.state_cancelled','text' => 'Yêu cầu thanh toán này đã bị huỷ.','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.state_cancelled','text' => 'This payment request was cancelled.','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.state_expired','text' => 'Link thanh toán đã hết hạn — vui lòng liên hệ cửa hàng.','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.state_expired','text' => 'This payment link has expired — please contact the store.','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.state_no_gateway','text' => 'Chưa có cổng thanh toán online — vui lòng liên hệ cửa hàng để thanh toán.','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.state_no_gateway','text' => 'No online payment method is available — please contact the store to pay.','position' => 'front.payment_request','location' => 'en'],
            ['code' => 'front.payment_request.state_unavailable','text' => 'Khoản này hiện không thanh toán online được — vui lòng liên hệ cửa hàng.','position' => 'front.payment_request','location' => 'vi'],
            ['code' => 'front.payment_request.state_unavailable','text' => 'This cannot be paid online right now — please contact the store.','position' => 'front.payment_request','location' => 'en'],
            // Order purposes + the order screen block
            ['code' => 'admin.order.payment_request_purpose_balance','text' => 'Đơn hàng: thu phần còn lại','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_purpose_balance','text' => 'Order: collect the balance','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_purpose_refund','text' => 'Đơn hàng: hoàn tiền khách','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_purpose_refund','text' => 'Order: refund the customer','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_block','text' => 'Yêu cầu thanh toán','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_block','text' => 'Payment requests','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_create_balance','text' => 'Tạo link thu phần còn lại','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_create_balance','text' => 'Payment link for the balance','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_create_refund','text' => 'Tạo yêu cầu hoàn tiền','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_create_refund','text' => 'Refund request','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_desc_balance','text' => 'Thanh toán phần còn lại của đơn #:id','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_desc_balance','text' => 'Balance of order #:id','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_desc_refund','text' => 'Hoàn tiền đơn #:id','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_desc_refund','text' => 'Refund of order #:id','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_needs_order','text' => 'Mục đích này cần gắn với một đơn hàng','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_needs_order','text' => 'This purpose needs an order','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_order_missing','text' => 'Không tìm thấy đơn hàng','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_order_missing','text' => 'Order not found','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_other_store','text' => 'Đơn hàng thuộc cửa hàng khác','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_other_store','text' => 'The order belongs to another store','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_other_currency','text' => 'Tiền tệ phải trùng với đơn (:currency)','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_other_currency','text' => 'The currency must be the order\'s (:currency)','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_over','text' => 'Số tiền tối đa cho đơn này là :max','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_over','text' => 'At most :max for this order','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_subject_order','text' => 'Mã đơn hàng','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_subject_order','text' => 'Order ID','position' => 'admin.order','location' => 'en'],
            ['code' => 'admin.order.payment_request_subject_help','text' => 'Mã đơn ở danh sách đơn hàng, vd OD-SZz76cP2 (hoặc tạo từ nút trên màn chi tiết đơn)','position' => 'admin.order','location' => 'vi'],
            ['code' => 'admin.order.payment_request_subject_help','text' => 'The order ID from the order list, e.g. OD-SZz76cP2 (or create it from the order detail screen)','position' => 'admin.order','location' => 'en'],
        ];
    }

    /**
     * The two permissions. WHY two: the screen wildcard must never cover money OUT, so
     * the money-out key is a SIBLING path (payment_request_settle_out), not a child.
     *
     * @param string $prefix Admin URL prefix.
     * @return array<int, array<string, string>>
     */
    public static function permissions(string $prefix): array
    {
        return [
            ['name' => 'Payment requests', 'slug' => 'payment.request', 'http_uri' => 'GET::' . $prefix . '/payment_request,ANY::' . $prefix . '/payment_request/*'],
            ['name' => 'Payment requests - pay out / refund', 'slug' => 'payment.request.settle_out', 'http_uri' => 'POST::' . $prefix . '/payment_request_settle_out'],
        ];
    }

    /**
     * The admin menu entry (placed under System).
     *
     * @return array<string, mixed>
     */
    public static function menu(): array
    {
        return [
            'sort' => 8,
            'title' => 'admin.menu_titles.payment_request',
            'icon' => 'fas fa-hand-holding-usd',
            'uri' => 'admin::payment_request',
            'key' => 'ADMIN_PAYMENT_REQUEST',
            'type' => 0,
        ];
    }

    /**
     * @return void
     */
    public function up()
    {
        $this->createTables();
        $this->seedAdmin();
        DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX . 'languages')->insertOrIgnore(self::labels());
    }

    /**
     * WARNING: drops both tables — every payment request and movement recorded in them —
     * and removes the menu entry, both permissions (and their role grants) and the labels,
     * including any text a site owner edited. The owner ledgers (orders, cash book) keep
     * their own rows. Never called by gp247:shop-uninstall.
     *
     * @return void
     */
    public function down()
    {
        $db = DB::connection(GP247_DB_CONNECTION);
        $db->table(GP247_DB_PREFIX . 'admin_menu')->where('uri', 'admin::payment_request')->delete();
        $ids = $db->table(GP247_DB_PREFIX . 'admin_permission')->whereIn('slug', ['payment.request', 'payment.request.settle_out'])->pluck('id')->all();
        if ($ids !== []) {
            $db->table(GP247_DB_PREFIX . 'admin_role_permission')->whereIn('permission_id', $ids)->delete();
            $db->table(GP247_DB_PREFIX . 'admin_permission')->whereIn('id', $ids)->delete();
        }
        $codes = array_values(array_unique(array_column(self::labels(), 'code')));
        $db->table(GP247_DB_PREFIX . 'languages')->whereIn('code', $codes)->delete();

        $schema = Schema::connection(GP247_DB_CONNECTION);
        $schema->dropIfExists(GP247_DB_PREFIX . 'payment_movement');
        $schema->dropIfExists(GP247_DB_PREFIX . 'payment_request');
    }

    /**
     * The two tables (add-only). Public so a test can create them on a database that was
     * never upgraded without seeding menu / permissions / labels outside its transaction.
     *
     * @return void
     */
    public function createTables(): void
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);
        $requests = GP247_DB_PREFIX . 'payment_request';

        if (!$schema->hasTable($requests)) {
            $schema->create($requests, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('direction', 3)->comment('in | out');
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3);
                $table->decimal('settled_amount', 15, 2)->default(0)->comment('derived: sum of movements, kept for listing');
                $table->string('status', 20)->default('open')->comment('open | partially_settled | settled | cancelled');
                $table->string('purpose', 64)->comment('key in gp247-config.payment.purposes');
                $table->string('subject_type', 64)->nullable()->comment('opaque owner reference');
                $table->string('subject_id', 64)->nullable();
                $table->string('party_name', 191)->nullable();
                $table->string('party_email', 191)->nullable();
                $table->string('party_phone', 50)->nullable();
                $table->text('description')->nullable();
                $table->string('store_id', 36);
                $table->string('gateway', 32)->nullable()->comment('gateway used for the collect/refund flow');
                $table->string('gateway_ref', 191)->nullable()->comment('gateway session reference');
                $table->string('public_token_hash', 64)->nullable()->unique();
                $table->text('public_token_enc')->nullable()->comment('payment link token, encrypted at rest (enc:v2)');
                $table->timestamp('expires_at')->nullable();
                $table->string('created_by', 36)->nullable();
                $table->string('cancelled_by', 36)->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('metadata')->nullable();
                $table->timestamps();

                $table->index(['store_id', 'status']);
                $table->index(['purpose', 'subject_type', 'subject_id'], 'payment_request_subject_index');
                $table->index('gateway_ref');
            });
        } elseif (!$schema->hasColumn($requests, 'public_token_enc')) {
            // A table created by an earlier development build of this feature.
            $schema->table($requests, function (Blueprint $table) {
                $table->text('public_token_enc')->nullable()->after('public_token_hash')
                    ->comment('payment link token, encrypted at rest (enc:v2)');
            });
        }

        if (!$schema->hasTable(GP247_DB_PREFIX . 'payment_movement')) {
            $schema->create(GP247_DB_PREFIX . 'payment_movement', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('request_id');
                $table->string('type', 10)->comment('collect | payout | refund');
                $table->decimal('amount', 15, 2)->comment('always positive; the sign lives in type');
                $table->string('currency', 3);
                $table->string('gateway', 32)->comment('manual or a gateway key');
                // WHY unique: the gateway reference is the idempotency key — a replayed
                // webhook or a double submit must never add a second row.
                $table->string('gateway_ref', 191)->nullable()->unique();
                $table->string('method', 10)->comment('gateway | manual');
                $table->string('reference', 191)->nullable()->comment('free reference typed by the admin');
                $table->timestamp('paid_at')->nullable();
                $table->string('admin_id', 36)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->text('metadata')->nullable();
                $table->timestamps();

                $table->index('request_id');
            });
        }
    }

    /**
     * Menu entry under System and the two permissions, by their natural keys.
     *
     * @return void
     */
    private function seedAdmin(): void
    {
        $db = DB::connection(GP247_DB_CONNECTION);

        if (!$db->table(GP247_DB_PREFIX . 'admin_menu')->where('uri', 'admin::payment_request')->exists()) {
            $system = $db->table(GP247_DB_PREFIX . 'admin_menu')->where('key', 'ADMIN_SYSTEM')->value('id');
            $db->table(GP247_DB_PREFIX . 'admin_menu')->insert(self::menu() + ['parent_id' => $system ?: 0]);
        }

        // Permission ids are auto-increment; the slug is the natural key.
        foreach (self::permissions(GP247_ADMIN_PREFIX) as $row) {
            if (!$db->table(GP247_DB_PREFIX . 'admin_permission')->where('slug', $row['slug'])->exists()) {
                $db->table(GP247_DB_PREFIX . 'admin_permission')->insert($row + ['created_at' => date('Y-m-d H:i:s')]);
            }
        }
    }
};
