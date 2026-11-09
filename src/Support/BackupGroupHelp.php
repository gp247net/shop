<?php

namespace GP247\Shop\Support;

/**
 * What the backup groups gp247/shop adds tables to hold, in business words (en / vi / ja). The provider
 * passes these keys to the core backup registry; the language seeder and the upgrade migration
 * install the texts from here (one source). No table names: the backup screen is read by site owners.
 *
 * @aidlc-unit backup-engine
 * @aidlc-story US-backup-engine-data-groups
 * @aidlc-adr backup-engine_data-group-registry
 */
final class BackupGroupHelp
{
    /** @var array<string, array{0: string, 1: string, 2: string}> Language key => [en, vi, ja]. */
    private const TEXTS = [
        'admin.backup.group_help.shop.catalog' => ['Products (descriptions, images, attributes, promotions, downloads, tags, bundles and groups), categories, brands, suppliers', 'Sản phẩm (mô tả, ảnh, thuộc tính, khuyến mãi, file tải, tag, combo và nhóm), danh mục, thương hiệu, nhà cung cấp', '商品（説明、画像、属性、セール、ダウンロード、タグ、セット・グループ）、カテゴリー、ブランド、仕入先'],
        'admin.backup.group_help.shop.orders' => ['Orders with their lines, history and totals, payment transactions and requests, shipments', 'Đơn hàng kèm dòng đơn, lịch sử và tổng tiền; giao dịch và yêu cầu thanh toán; vận chuyển', '注文（明細、履歴、合計）、決済取引と決済リクエスト、配送'],
        'admin.backup.group_help.shop.customers' => ['Customer accounts, addresses and saved carts', 'Tài khoản khách hàng, địa chỉ và giỏ hàng đã lưu', '顧客アカウント、住所、保存されたカート'],
        'admin.backup.group_help.shop.config' => ['Currencies, taxes, order, payment and shipping statuses', 'Tiền tệ, thuế, trạng thái đơn hàng, thanh toán và vận chuyển', '通貨、税、注文・支払い・配送のステータス'],
    ];

    /**
     * @param string $group Backup group key (catalog, orders, customers, config).
     * @return string Language key of the description of gp247/shop's part of that group.
     */
    public static function key(string $group): string
    {
        return 'admin.backup.group_help.shop.'.$group;
    }

    /**
     * Rows for the `languages` table.
     *
     * @return list<array{code: string, text: string, position: string, location: string}>
     */
    public static function languageRows(): array
    {
        $rows = [];
        foreach (self::TEXTS as $code => [$en, $vi, $ja]) {
            foreach (['en' => $en, 'vi' => $vi, 'ja' => $ja] as $location => $text) {
                $rows[] = ['code' => $code, 'text' => $text, 'position' => 'admin.backup.group_help.shop', 'location' => $location];
            }
        }

        return $rows;
    }
}
