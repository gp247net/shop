<?php

namespace GP247\Shop\Api\V2\Resources;

use GP247\Core\Api\V2\ApiQuery;

/**
 * Declared v2 shapes of the smaller shop records (category, brand, supplier,
 * customer, order). Each method lists its fields explicitly; a new table column does
 * not reach the API until it is added here.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-v2-contract
 * @aidlc-adr compat-foundation_rest-api-hardening
 */
final class ShopResources
{
    /**
     * @param \GP247\Shop\Models\ShopCategory $c
     * @param string                          $lang
     * @return array<string, mixed>
     */
    public static function category($c, string $lang): array
    {
        return array_merge([
            'id' => $c->id, 'alias' => $c->alias,
        ], ApiQuery::described($c->descriptions, $lang, ['name', 'keyword', 'description']), [
            'image' => $c->image, 'parent' => $c->parent, 'top' => (int) $c->top, 'sort' => (int) $c->sort,
            'status' => (int) $c->status, 'store_id' => $c->store_id,
            'created_at' => self::date($c->created_at), 'updated_at' => self::date($c->updated_at),
        ]);
    }

    /**
     * @param \GP247\Shop\Models\ShopBrand $b
     * @return array<string, mixed>
     */
    public static function brand($b): array
    {
        return [
            'id' => $b->id, 'name' => $b->name, 'alias' => $b->alias, 'image' => $b->image, 'url' => $b->url,
            'sort' => (int) $b->sort, 'status' => (int) $b->status, 'store_id' => $b->store_id,
            'created_at' => self::date($b->created_at), 'updated_at' => self::date($b->updated_at),
        ];
    }

    /**
     * @param \GP247\Shop\Models\ShopSupplier $s
     * @return array<string, mixed>
     */
    public static function supplier($s): array
    {
        return [
            'id' => $s->id, 'name' => $s->name, 'alias' => $s->alias, 'email' => $s->email, 'phone' => $s->phone,
            'image' => $s->image, 'address' => $s->address, 'url' => $s->url, 'sort' => (int) $s->sort,
            'status' => (int) $s->status, 'store_id' => $s->store_id,
            'created_at' => self::date($s->created_at), 'updated_at' => self::date($s->updated_at),
        ];
    }

    /**
     * Customer profile (never password or remember token).
     *
     * @param \GP247\Shop\Models\ShopCustomer $c
     * @param bool                            $admin Include store and verification fields.
     * @return array<string, mixed>
     */
    public static function customer($c, bool $admin = false): array
    {
        $data = [
            'id' => $c->id, 'first_name' => $c->first_name, 'last_name' => $c->last_name,
            'first_name_kana' => $c->first_name_kana, 'last_name_kana' => $c->last_name_kana,
            'email' => $c->email, 'phone' => $c->phone, 'sex' => $c->sex, 'birthday' => $c->birthday,
            'company' => $c->company, 'country' => $c->country, 'postcode' => $c->postcode, 'city' => $c->city,
            'district' => $c->district, 'ward' => $c->ward, 'address1' => $c->address1, 'address2' => $c->address2,
            'address3' => $c->address3, 'status' => (int) $c->status, 'group' => $c->group,
            'created_at' => self::date($c->created_at), 'updated_at' => self::date($c->updated_at),
        ];
        if ($admin) {
            $data += ['store_id' => $c->store_id, 'email_verified_at' => $c->email_verified_at];
        }
        return $data;
    }

    /**
     * Order with its lines and totals. The customer view leaves out the request
     * forensics (ip, user agent, device, domain) and payment internals.
     *
     * @param \GP247\Shop\Models\ShopOrder $o
     * @param bool                         $admin
     * @return array<string, mixed>
     */
    public static function order($o, bool $admin = false): array
    {
        $data = [
            'id' => $o->id, 'customer_id' => $o->customer_id, 'status' => (int) $o->status,
            'payment_status' => (int) $o->payment_status, 'shipping_status' => (int) $o->shipping_status,
            'currency' => $o->currency, 'exchange_rate' => (float) $o->exchange_rate,
            'subtotal' => (float) $o->subtotal, 'tax' => (float) $o->tax, 'shipping' => (float) $o->shipping,
            'discount' => (float) $o->discount, 'other_fee' => (float) $o->other_fee, 'total' => (float) $o->total,
            'received' => (float) $o->received, 'balance' => (float) $o->balance,
            'first_name' => $o->first_name, 'last_name' => $o->last_name, 'email' => $o->email, 'phone' => $o->phone,
            'company' => $o->company, 'country' => $o->country, 'postcode' => $o->postcode, 'city' => $o->city,
            'district' => $o->district, 'ward' => $o->ward, 'address1' => $o->address1, 'address2' => $o->address2,
            'address3' => $o->address3, 'comment' => $o->comment,
            'payment_method' => $o->payment_method, 'shipping_method' => $o->shipping_method,
            'store_id' => $o->store_id,
            'details' => $o->details->map(fn ($d) => [
                'id' => $d->id, 'product_id' => $d->product_id, 'sku' => $d->sku, 'name' => $d->name,
                'price' => (float) $d->price, 'qty' => (float) $d->qty, 'discount' => (float) $d->discount,
                'tax' => (float) $d->tax, 'tax_rate' => (float) $d->tax_rate, 'total_price' => (float) $d->total_price,
                'attribute' => $d->attribute,
            ])->values(),
            'totals' => $o->orderTotal->sortBy('sort')->map(fn ($t) => [
                'code' => $t->code, 'title' => $t->title, 'value' => (float) $t->value, 'text' => $t->text,
            ])->values(),
            'created_at' => self::date($o->created_at), 'updated_at' => self::date($o->updated_at),
        ];
        if ($admin) {
            $data += ['ip' => $o->ip, 'user_agent' => $o->user_agent, 'device_type' => $o->device_type, 'domain' => $o->domain, 'transaction' => $o->transaction];
        }
        return $data;
    }

    /**
     * @param mixed $value
     * @return string|null ISO-8601 or null.
     */
    private static function date($value): ?string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->toIso8601String() : null;
    }
}
