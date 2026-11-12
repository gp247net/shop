<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Running sites: give back the plain text of shop tables that gp247_clean() HTML-encoded on
 * save before core 3.12 ("Cron &amp; Queue" ⇒ "Cron & Queue"), once, so text is stored as
 * typed (US-CMP-text-stored-as-typed, decision D2). Column list approved 2026-11-12 (rule
 * data-safety §C, modification_plan_20261112T061240): only plain-text columns; never rich
 * content, JSON, codes, aliases or e-mail addresses.
 * Only columns whose every write path went through gp247_clean(); left out on purpose:
 * shop_order_history.content (mixed: the admin path cleaned it, ShopOrder::addOrderHistory,
 * HistoricalOrderWriter and ShipmentService store it as is — it is shown through
 * gp247_render_basic_markup() either way) and shop_order_transaction.note (never cleaned).
 *
 * CHANGES DATA — back up the database before gp247:update. Idempotent through the
 * `text_storage_decoded_shop` flag; rows are read in chunks and updated by primary key.
 * Needs core 3.12 (GP247\Core\Support\StoredTextDecoder); on an older core it does nothing
 * and leaves the flag unset, so it runs after core is updated.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-CMP-text-stored-as-typed
 * @aidlc-adr compat-foundation_text-stored-as-typed
 */
return new class extends Migration
{
    /**
     * Table (without prefix) => plain-text columns to decode.
     *
     * @return array<string, array<int, string>>
     */
    public static function columns(): array
    {
        return [
            'shop_product_description' => ['name', 'keyword', 'description'],
            'shop_category_description' => ['name', 'keyword', 'description'],
            'shop_brand' => ['name', 'url'],
            'shop_supplier' => ['name', 'phone', 'address', 'url'],
            'shop_product_tag' => ['name'],
            'shop_attribute_group' => ['name'],
            'shop_product_attribute' => ['name'],
            'shop_currency' => ['name'],
            'shop_tax' => ['name'],
            'shop_order_status' => ['name'],
            'shop_payment_status' => ['name'],
            'shop_shipping_status' => ['name'],
            'shop_customer' => ['first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'postcode', 'city', 'district', 'ward', 'address1', 'address2', 'address3', 'company', 'phone'],
            'shop_customer_address' => ['first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'postcode', 'city', 'district', 'ward', 'address1', 'address2', 'address3', 'phone'],
            'shop_order' => ['first_name', 'last_name', 'first_name_kana', 'last_name_kana', 'city', 'district', 'ward', 'address1', 'address2', 'address3', 'company', 'postcode', 'phone', 'comment'],
            'shop_order_total' => ['title'],
            'shop_order_detail' => ['name'],
            'shop_shipment' => ['tracking_number', 'tracking_url'],
            'payment_movement' => ['reference', 'note'],
        ];
    }

    /**
     * Decode once.
     *
     * @return void
     */
    public function up()
    {
        if (!class_exists(\GP247\Core\Support\StoredTextDecoder::class)) {
            return;
        }
        \GP247\Core\Support\StoredTextDecoder::run('text_storage_decoded_shop', self::columns());
    }

    /**
     * Irreversible: encoding the text again would bring back "&amp;" everywhere. Restore the
     * backup taken before gp247:update to go back.
     *
     * @return void
     */
    public function down()
    {
        // Irreversible on purpose — see method doc.
    }
};
