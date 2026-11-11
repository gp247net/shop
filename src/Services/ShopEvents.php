<?php

namespace GP247\Shop\Services;

use GP247\Core\Api\Sync\ApiEvents;

/**
 * The shop's sync events: the eight types it registers with core's event log, and the one
 * place each of them is recorded from — the order / product / customer model hooks (any
 * write through Eloquent: storefront, admin screens, API, plugins) and the stock seams that
 * write with the query builder. Events are thin (declared fields, names of changed columns,
 * never values of personal data); nothing is read or written while the log is off, and a
 * failure is reported, never thrown, so it cannot break the change.
 *
 * @aidlc-unit api-admin-write
 * @aidlc-story US-api-admin-write-shop-events
 * @aidlc-adr api-admin-write_model-hook-event-emission
 */
final class ShopEvents
{
    /** Types registered with `gp247-config.api.events`: fields kept, screen whose readers may read them. */
    public const TYPES = [
        'order.created'                => ['fields' => ['status', 'payment_status', 'channel', 'total', 'currency'], 'permission' => 'admin_order.index'],
        'order.status_changed'         => ['fields' => ['field', 'from', 'to'], 'permission' => 'admin_order.index'],
        'order.payment_status_changed' => ['fields' => ['from', 'to', 'received', 'balance'], 'permission' => 'admin_order.index'],
        'stock.changed'                => ['fields' => ['delta', 'stock'], 'permission' => 'admin_product.index'],
        'product.updated'              => ['fields' => ['fields', 'status'], 'permission' => 'admin_product.index'],
        'product.deleted'              => ['fields' => ['sku'], 'permission' => 'admin_product.index'],
        'customer.created'             => ['fields' => ['status'], 'permission' => 'admin_customer.index'],
        'customer.updated'             => ['fields' => ['fields', 'status'], 'permission' => 'admin_customer.index'],
    ];

    /** Product columns that are not an edit: counters, the stock (its own event) and timestamps. */
    public const PRODUCT_IGNORED = ['view', 'date_lastview', 'sold', 'stock', 'created_at', 'updated_at'];

    /** Customer columns left out of `customer.updated`: secrets and timestamps. */
    public const CUSTOMER_IGNORED = ['password', 'remember_token', 'created_at', 'updated_at'];

    /** Order columns whose change is an `order.status_changed`. */
    private const ORDER_STATUS_FIELDS = ['status', 'shipping_status'];

    /** Depth of muted() calls in this process (nested bulk steps). */
    private static int $muted = 0;

    /**
     * Add the shop's types to the registry, keeping the types core and plugins declared.
     *
     * @return void
     */
    public static function register(): void
    {
        config(['gp247-config.api.events' => array_merge(self::TYPES, (array) config('gp247-config.api.events', []))]);
    }

    /**
     * Whether events are being recorded — checked before any extra read.
     *
     * @return bool
     */
    public static function active(): bool
    {
        return self::$muted === 0 && class_exists(ApiEvents::class) && ApiEvents::enabled() && ApiEvents::ready();
    }

    /**
     * Run a bulk change the site owner chose to keep out of the sync log (e.g. a data import
     * before the shop opens): nothing is recorded inside $change, in this process only; recording
     * resumes when it returns or throws. Nested calls are allowed.
     *
     * @template T
     * @param callable(): T $change
     * @return T What $change returns.
     *
     * @aidlc-unit api-admin-write
     * @aidlc-story US-api-admin-write-shop-events
     * @aidlc-adr shop-admin_catalog-write-service
     */
    public static function muted(callable $change): mixed
    {
        self::$muted++;
        try {
            return $change();
        } finally {
            self::$muted--;
        }
    }

    /**
     * @param \GP247\Shop\Models\ShopOrder $order Just created.
     * @return void
     */
    public static function orderCreated($order): void
    {
        self::guard(function () use ($order) {
            $values = self::stored($order, ['status', 'payment_status', 'channel', 'total', 'currency']);
            self::record('order.created', $order, [
                'status' => (int) $values['status'], 'payment_status' => (int) $values['payment_status'], 'channel' => $values['channel'],
                'total' => (float) $values['total'], 'currency' => $values['currency'],
            ]);
        });
    }

    /**
     * Status, shipping status and payment status changes of a saved order.
     *
     * @param \GP247\Shop\Models\ShopOrder $order Just updated.
     * @return void
     */
    public static function orderUpdated($order): void
    {
        self::guard(function () use ($order) {
            $changed = self::changed($order, []);
            foreach (self::ORDER_STATUS_FIELDS as $field) {
                if (in_array($field, $changed, true)) {
                    self::record('order.status_changed', $order, ['field' => $field, 'from' => (int) $order->getOriginal($field), 'to' => (int) $order->{$field}]);
                }
            }
            if (in_array('payment_status', $changed, true)) {
                self::record('order.payment_status_changed', $order, [
                    'from' => (int) $order->getOriginal('payment_status'), 'to' => (int) $order->payment_status,
                    'received' => (float) $order->received, 'balance' => (float) $order->balance,
                ]);
            }
        });
    }

    /**
     * Stock move and edit of a product saved through the model.
     *
     * @param \GP247\Shop\Models\ShopProduct $product Just updated.
     * @return void
     */
    public static function productUpdated($product): void
    {
        self::guard(function () use ($product) {
            if (in_array('stock', self::changed($product, []), true)) {
                $stock = (float) $product->stock;
                self::record('stock.changed', $product, ['delta' => self::number($stock - (float) $product->getOriginal('stock')), 'stock' => self::number($stock)]);
            }
            $fields = self::changed($product, self::PRODUCT_IGNORED);
            if ($fields !== []) {
                self::record('product.updated', $product, ['fields' => $fields, 'status' => (int) $product->status]);
            }
        });
    }

    /**
     * A product's texts changed (they live in another table, so the model sees nothing).
     *
     * @param \GP247\Shop\Models\ShopProduct $product
     * @param string[]                       $fields  e.g. ["descriptions"].
     * @return void
     */
    public static function productTouched($product, array $fields): void
    {
        self::guard(fn () => self::record('product.updated', $product, ['fields' => $fields, 'status' => (int) $product->status]));
    }

    /**
     * @param \GP247\Shop\Models\ShopProduct $product Just deleted.
     * @return void
     */
    public static function productDeleted($product): void
    {
        self::guard(fn () => self::record('product.deleted', $product, ['sku' => (string) $product->sku]));
    }

    /**
     * Stock moved by a query-builder seam (no model event fires there).
     *
     * @param int|string $productId
     * @param float      $delta     Change of the stock (+ in, - out).
     * @return void
     */
    public static function stockMoved($productId, float $delta): void
    {
        if ($delta == 0.0 || !self::active()) {
            return;
        }
        self::guard(function () use ($productId, $delta) {
            $row = \GP247\Shop\Models\ShopProduct::where('id', $productId)->first(['id', 'stock', 'store_id']);
            if ($row !== null) {
                self::record('stock.changed', $row, ['delta' => self::number($delta), 'stock' => self::number((float) $row->stock)]);
            }
        });
    }

    /**
     * @param \GP247\Shop\Models\ShopCustomer $customer Just created.
     * @return void
     */
    public static function customerCreated($customer): void
    {
        self::guard(fn () => self::record('customer.created', $customer, ['status' => (int) self::stored($customer, ['status'])['status']]));
    }

    /**
     * @param \GP247\Shop\Models\ShopCustomer $customer Just updated.
     * @return void
     */
    public static function customerUpdated($customer): void
    {
        self::guard(function () use ($customer) {
            $fields = self::changed($customer, self::CUSTOMER_IGNORED);
            if ($fields !== []) {
                self::record('customer.updated', $customer, ['fields' => $fields, 'status' => (int) $customer->status]);
            }
        });
    }

    /**
     * @param string                              $type
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array                               $data
     * @return void
     */
    private static function record(string $type, $model, array $data): void
    {
        ApiEvents::record($type, (string) $model->getKey(), $model->store_id !== null ? (string) $model->store_id : null, $data);
    }

    /**
     * Columns of a just-saved record whose value really changed. WHY: the model sees `10`
     * written over `"10.00"`, or `""` over `null`, as a change (the product form rewrites
     * every field on each save) — an equal number, or empty over empty, is not a change.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string[]                            $ignored
     * @return string[]
     */
    private static function changed($model, array $ignored): array
    {
        $fields = [];
        foreach (array_keys($model->getChanges()) as $column) {
            if (in_array($column, $ignored, true)) {
                continue;
            }
            $old = $model->getOriginal($column);
            $new = $model->getAttribute($column);
            if ((is_numeric($old) && is_numeric($new) && (float) $old == (float) $new)
                || (($old === null || $old === '') && ($new === null || $new === ''))) {
                continue;
            }
            $fields[] = $column;
        }

        return $fields;
    }

    /**
     * Values of $columns of a just-created record: a column the code left out holds its
     * database default, which the model instance does not know — read those back.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string[]                            $columns
     * @return array<string, mixed>
     */
    private static function stored($model, array $columns): array
    {
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = $model->getAttribute($column);
        }
        $missing = array_keys(array_filter($values, fn ($v) => $v === null));
        if ($missing !== []) {
            $row = $model->newQueryWithoutScopes()->whereKey($model->getKey())->first($missing);
            foreach ($missing as $column) {
                $values[$column] = $row?->getAttribute($column);
            }
        }

        return $values;
    }

    /**
     * Run $emit unless the log is off; report and swallow any failure.
     *
     * @param callable $emit
     * @return void
     */
    private static function guard(callable $emit): void
    {
        try {
            if (self::active()) {
                $emit();
            }
        } catch (\Throwable $e) {
            gp247_report($e);
        }
    }

    /**
     * A quantity as JSON shows it best: an integer when whole.
     *
     * @param float $value
     * @return int|float
     */
    private static function number(float $value)
    {
        return floor($value) == $value ? (int) $value : round($value, 4);
    }
}
