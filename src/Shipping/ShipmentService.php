<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Shipping\Contracts\CancelsShipments;
use GP247\Shop\Shipping\Contracts\CreatesShipments;
use GP247\Shop\Shipping\Contracts\ProvidesShippingDocuments;
use GP247\Shop\Shipping\Contracts\ShippingProvider;
use GP247\Shop\Shipping\Contracts\TracksShipments;
use GP247\Shop\Shipping\Data\Money;
use GP247\Shop\Shipping\Data\Parcel;
use GP247\Shop\Shipping\Data\RateSnapshot;
use GP247\Shop\Shipping\Data\ShipmentRequest;
use GP247\Shop\Shipping\Data\ShippingAddress;
use GP247\Shop\Shipping\Data\ShippingDocument;
use GP247\Shop\Shipping\Data\ShippingItem;
use GP247\Shop\Shipping\Data\TrackingEvent;
use GP247\Shop\Shipping\Models\ShopShipment;
use GP247\Shop\Shipping\Models\ShopShipmentEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle of shipments: book, cancel, fetch documents, sync, apply carrier events,
 * fold the shipment status into the order (the single seam applyToOrder()) and record
 * reconciled COD cash. Carrier specifics stay in the provider; this class only speaks
 * the contract (ADR shop-shipping_shipment-entity).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 * @aidlc-story US-shop-shipping-tracking-webhook
 * @aidlc-story US-shop-shipping-cod
 * @aidlc-adr shop-shipping_shipment-entity
 */
class ShipmentService
{
    /**
     * @param ShippingProviderRegistry $registry Provider resolution.
     */
    public function __construct(private readonly ShippingProviderRegistry $registry)
    {
    }

    /**
     * @return self The container-bound service.
     */
    public static function instance(): self
    {
        return app(self::class);
    }

    /**
     * Whether the shipping tables exist (a site not yet upgraded with gp247:shop-update
     * must keep rendering the order screen).
     *
     * @return bool
     */
    public static function available(): bool
    {
        static $available = null;

        return $available ??= \Illuminate\Support\Facades\Schema::connection(GP247_DB_CONNECTION)->hasTable(GP247_DB_PREFIX . 'shop_shipment');
    }

    /**
     * @param ShopOrder $order Order.
     * @return ShopShipment|null Latest shipment that is not cancelled.
     */
    public function activeShipment(ShopOrder $order): ?ShopShipment
    {
        return ShopShipment::where('order_id', $order->id)
            ->where('status', '!=', ShipmentStatus::CANCELLED->value)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @param ShopOrder $order Order.
     * @return Collection<int, ShopShipment> All shipments of the order, newest first.
     */
    public function shipments(ShopOrder $order): Collection
    {
        return ShopShipment::with('events')->where('order_id', $order->id)->orderByDesc('created_at')->get();
    }

    /**
     * Prefill of the "create shipment" form, from the order and its rate snapshot.
     *
     * @param ShopOrder $order Order.
     * @return array<string, mixed>
     */
    public function defaults(ShopOrder $order): array
    {
        $snapshot = RateSnapshot::fromArray(json_decode((string) $order->shipping_rate, true));
        $capable = $this->registry->shipmentCapableKeys();
        $plugin = $snapshot?->pluginKey ?? (string) $order->shipping_method;
        if (!in_array($plugin, $capable, true)) {
            $plugin = $capable[0] ?? '';
        }

        return [
            'plugin_key' => $plugin,
            'service_code' => ($snapshot !== null && $snapshot->pluginKey === $plugin) ? $snapshot->serviceCode : (string) ($order->shipping_service ?? ''),
            'name' => trim($order->first_name . ' ' . $order->last_name),
            'phone' => (string) $order->phone,
            'province' => (string) $order->city,
            'district' => (string) $order->district,
            'ward' => (string) ($order->ward ?? ''),
            'address' => (string) $order->address1,
            'address2' => trim($order->address2 . ' ' . $order->address3),
            // WHY fallback: an order without lines (or weights) still needs a parcel weight to book.
            'weight_grams' => $this->orderWeightGrams($order) ?: ShippingSettings::defaultWeightGrams((string) $order->store_id),
            // Box size is measured by the admin when packing (0 = not measured).
            'length_cm' => 0,
            'width_cm' => 0,
            'height_cm' => 0,
            'declared_value' => (float) $order->subtotal,
            'cod_amount' => CodResolver::forOrder($order),
            'currency' => (string) $order->currency,
            'note' => '',
            'extra' => [],
        ];
    }

    /**
     * Book a shipment with the carrier.
     *
     * @param ShopOrder            $order   Order to ship.
     * @param array<string, mixed> $input   Validated form values (see defaults()).
     * @param string               $adminId Admin performing the action.
     * @return ShopShipment
     * @throws ShippingException When the order/carrier refuses.
     */
    public function create(ShopOrder $order, array $input, string $adminId = '0'): ShopShipment
    {
        if ($order->isLocked()) {
            throw new ShippingException(gp247_language_render('admin.shipment.order_locked'));
        }
        if ($this->activeShipment($order) !== null) {
            throw new ShippingException(gp247_language_render('admin.shipment.active_exists'));
        }
        $key = (string) ($input['plugin_key'] ?? '');
        $provider = $this->registry->provider($key);
        if (!$provider instanceof CreatesShipments || !$this->registry->supports($key, ShippingProvider::CAP_SHIPMENTS)) {
            throw new ShippingException(gp247_language_render('admin.shipment.no_provider'));
        }

        $attempt = ShopShipment::where('order_id', $order->id)->count() + 1;
        $request = $this->buildRequest($order, $key, $input, $provider, $attempt);
        $result = $provider->createShipment($request);

        $existing = ShopShipment::where('plugin_key', $key)->where('tracking_number', $result->trackingNumber)->first();
        if ($existing !== null) {
            if (!$existing->isActive()) {
                // The carrier answered with a booking this shop already cancelled.
                throw new ShippingException(gp247_language_render('admin.shipment.active_exists'));
            }

            return $existing;
        }

        $currency = $result->fee?->currency ?? $request->codAmount->currency;
        $cod = ShippingCurrency::convert($request->codAmount, $currency) ?? $request->codAmount;
        $trackingUrl = preg_match('#^https?://#i', $result->trackingUrl) ? $result->trackingUrl : '';

        try {
            return $this->persistBooking($order, $key, $request, $result, $currency, $cod, $trackingUrl, $adminId);
        } catch (\Throwable $e) {
            // The carrier booked but the shop could not record it: undo at the carrier when
            // possible so no booking exists that the shop does not know about.
            if ($provider instanceof CancelsShipments) {
                try {
                    $provider->cancelShipment($result->trackingNumber, (string) $order->store_id);
                } catch (\Throwable $ignored) {
                    gp247_report('[GP247 shipping] could not cancel orphan booking ' . $key . ' ' . $result->trackingNumber);
                }
            }
            throw $e;
        }
    }

    /**
     * Store a booking (shipment row, first event, history, order status) atomically.
     *
     * @param ShopOrder                                   $order       Order shipped.
     * @param string                                      $key         Provider key.
     * @param ShipmentRequest                             $request     What was booked.
     * @param \GP247\Shop\Shipping\Data\ShipmentResult $result      Carrier answer.
     * @param string                                      $currency    Carrier currency.
     * @param Money                                       $cod         COD in carrier currency.
     * @param string                                      $trackingUrl Sanitised (http/https) tracking link.
     * @param string                                      $adminId     Admin performing the action.
     * @return ShopShipment
     */
    private function persistBooking(ShopOrder $order, string $key, ShipmentRequest $request, \GP247\Shop\Shipping\Data\ShipmentResult $result, string $currency, Money $cod, string $trackingUrl, string $adminId): ShopShipment
    {
        return DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($order, $key, $request, $result, $currency, $cod, $trackingUrl, $adminId): ShopShipment {
            $shipment = ShopShipment::create([
                'order_id' => $order->id,
                'store_id' => (string) $order->store_id,
                'plugin_key' => $key,
                'service_code' => $request->serviceCode,
                'tracking_number' => $result->trackingNumber,
                'tracking_url' => $trackingUrl,
                'provider_reference' => $result->providerReference,
                'status' => $result->status->value,
                'fee' => $result->fee?->value() ?? 0,
                'cod_amount' => $cod->value(),
                'currency' => $currency,
                'weight_grams' => $request->totalWeightGrams(),
                'request' => $request->toArray(),
                'meta' => array_filter(['eta' => $result->etaText] + $result->meta) + ['last_event_at' => time()],
                'last_synced_at' => now(),
            ]);
            $this->recordEvent($shipment, new TrackingEvent($result->trackingNumber, $result->status, 'created', time()), 'admin');
            $this->history($order, gp247_language_render('admin.shipment.history_created', ['carrier' => e($key), 'tracking' => e($result->trackingNumber)]), $adminId);
            $this->applyToOrder($shipment, $adminId);

            return $shipment;
        });
    }

    /**
     * @param ShopShipment $shipment Shipment to cancel.
     * @param string       $adminId  Admin performing the action.
     * @return void
     * @throws ShippingException When the carrier refuses.
     */
    public function cancel(ShopShipment $shipment, string $adminId = '0'): void
    {
        $provider = $this->registry->provider($shipment->plugin_key);
        if (!$provider instanceof CancelsShipments || !$this->registry->supports($shipment->plugin_key, ShippingProvider::CAP_CANCEL)) {
            throw new ShippingException(gp247_language_render('admin.shipment.no_provider'));
        }
        if (!$shipment->isActive()) {
            return;
        }
        $provider->cancelShipment((string) $shipment->tracking_number, (string) $shipment->store_id);

        DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($shipment, $adminId): void {
            $meta = (array) $shipment->meta;
            $meta['last_event_at'] = time();
            $shipment->forceFill(['status' => ShipmentStatus::CANCELLED->value, 'cancelled_at' => now(), 'meta' => $meta])->save();
            $this->recordEvent($shipment, new TrackingEvent((string) $shipment->tracking_number, ShipmentStatus::CANCELLED, 'cancelled', time()), 'admin');
            $this->applyToOrder($shipment, $adminId);
        });
    }

    /**
     * @param ShopShipment         $shipment Shipment.
     * @param array<string, mixed> $options  Carrier options.
     * @return ShippingDocument
     * @throws ShippingException When the carrier cannot produce it.
     */
    public function document(ShopShipment $shipment, array $options = []): ShippingDocument
    {
        $provider = $this->registry->provider($shipment->plugin_key);
        if (!$provider instanceof ProvidesShippingDocuments || !$this->registry->supports($shipment->plugin_key, ShippingProvider::CAP_DOCUMENTS)) {
            throw new ShippingException(gp247_language_render('admin.shipment.no_provider'));
        }

        return $provider->document((string) $shipment->tracking_number, (string) $shipment->store_id, $options);
    }

    /**
     * Pull the carrier's current state ("Sync").
     *
     * @param ShopShipment $shipment Shipment.
     * @return int Number of new events applied.
     * @throws ShippingException When the carrier cannot be reached.
     */
    public function sync(ShopShipment $shipment): int
    {
        $provider = $this->registry->provider($shipment->plugin_key);
        if (!$provider instanceof TracksShipments || !$this->registry->supports($shipment->plugin_key, ShippingProvider::CAP_TRACKING)) {
            throw new ShippingException(gp247_language_render('admin.shipment.no_provider'));
        }
        $events = $provider->track((string) $shipment->tracking_number, (string) $shipment->store_id);
        $applied = $this->applyEvents($shipment->plugin_key, $events, 'sync');
        $shipment->forceFill(['last_synced_at' => now()])->save();

        return $applied;
    }

    /**
     * Refresh an open shipment when its last sync is older than the store's interval —
     * the cron-free fallback when webhooks do not arrive (NFR-AVAIL-shipping-no-cron).
     * Never throws: a carrier outage must not break the order screen.
     *
     * @param ShopShipment $shipment Shipment.
     * @return bool True when a sync ran successfully.
     */
    public function lazyRefresh(ShopShipment $shipment): bool
    {
        if (!$shipment->isActive() || $shipment->shipmentStatus()->isFinal()
            || !$this->registry->supports($shipment->plugin_key, ShippingProvider::CAP_TRACKING)) {
            return false;
        }
        $interval = ShippingSettings::syncIntervalMinutes((string) $shipment->store_id);
        if ($shipment->last_synced_at !== null && $shipment->last_synced_at->gt(now()->subMinutes($interval))) {
            return false;
        }
        try {
            $this->sync($shipment);

            return true;
        } catch (\Throwable $e) {
            gp247_report('[GP247 shipping] lazy refresh of ' . $shipment->plugin_key . ' ' . $shipment->tracking_number . ' failed: ' . $e->getMessage());
            $shipment->forceFill(['last_synced_at' => now()])->save();

            return false;
        }
    }

    /**
     * Apply carrier events (webhook or sync): each event is stored once (dedupe_key),
     * moves the shipment only forward in time, folds into the order and may record
     * COD cash.
     *
     * @param string                    $pluginKey Provider that produced the events.
     * @param array<int, TrackingEvent> $events    Normalised events.
     * @param string                    $source    webhook|sync|admin.
     * @return int Number of new events stored.
     */
    public function applyEvents(string $pluginKey, array $events, string $source): int
    {
        $applied = 0;
        usort($events, static fn (TrackingEvent $a, TrackingEvent $b): int => $a->occurredAt <=> $b->occurredAt);
        foreach ($events as $event) {
            if (!$event instanceof TrackingEvent) {
                continue;
            }
            $shipment = ShopShipment::where('plugin_key', $pluginKey)->where('tracking_number', $event->trackingNumber)->first();
            if ($shipment === null) {
                continue;
            }
            $stored = DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($shipment, $event, $source): bool {
                if (!$this->recordEvent($shipment, $event, $source)) {
                    return false;
                }
                $this->applyEventToShipment($shipment, $event);
                if ($event->codSettled) {
                    $this->recordCod($shipment, $event);
                }

                return true;
            });
            $applied += $stored ? 1 : 0;
        }

        return $applied;
    }

    /**
     * The single seam folding a shipment's status into shop_order.shipping_status,
     * with one history line per change. Never touches shop_order.status (order state
     * and stock stay human decisions).
     *
     * @param ShopShipment $shipment Shipment.
     * @param string       $adminId  Admin id for the history line ('0' = system).
     * @return bool True when the order changed.
     */
    public function applyToOrder(ShopShipment $shipment, string $adminId = '0'): bool
    {
        $order = ShopOrder::find($shipment->order_id);
        if ($order === null) {
            return false;
        }
        $target = $shipment->shipmentStatus()->orderShippingStatus();
        if ((int) $order->shipping_status === $target) {
            return false;
        }
        $order->forceFill(['shipping_status' => $target])->save();
        $this->history($order, gp247_language_render('admin.shipment.history_status', [
            'tracking' => e((string) $shipment->tracking_number),
            'status' => gp247_language_render($shipment->shipmentStatus()->labelKey()),
        ]) . ' [' . e($shipment->plugin_key . ':' . $shipment->provider_status) . ']', $adminId);

        return true;
    }

    /**
     * @param ShopShipment  $shipment Shipment.
     * @param TrackingEvent $event    Event.
     * @param string        $source   webhook|sync|admin.
     * @return bool False when the event was already stored.
     */
    private function recordEvent(ShopShipment $shipment, TrackingEvent $event, string $source): bool
    {
        $key = $event->dedupeKey($shipment->plugin_key);
        $inserted = ShopShipmentEvent::query()->insertOrIgnore([
            'shipment_id' => $shipment->id,
            'status' => $event->status?->value,
            'provider_status' => mb_substr($event->providerStatus, 0, 50),
            'occurred_at' => date('Y-m-d H:i:s', $event->occurredAt > 0 ? $event->occurredAt : time()),
            'message' => mb_substr($event->message, 0, 500),
            'source' => $source,
            'dedupe_key' => $key,
            'created_at' => now(),
        ]);

        return $inserted > 0;
    }

    /**
     * @param ShopShipment  $shipment Shipment (saved).
     * @param TrackingEvent $event    Event just stored.
     * @return void
     */
    private function applyEventToShipment(ShopShipment $shipment, TrackingEvent $event): void
    {
        if ($event->status === null) {
            return;
        }
        // A cancelled shipment is final for the shop: a late or retried carrier event must
        // not bring it back (it would also block a new booking).
        if ($shipment->cancelled_at !== null && $event->status !== ShipmentStatus::CANCELLED) {
            return;
        }
        $meta = (array) $shipment->meta;
        $lastAt = (int) ($meta['last_event_at'] ?? 0);
        $at = $event->occurredAt > 0 ? $event->occurredAt : time();
        if ($at < $lastAt) {
            return;
        }
        $meta['last_event_at'] = $at;
        $changes = ['status' => $event->status->value, 'provider_status' => mb_substr($event->providerStatus, 0, 50), 'meta' => $meta];
        if ($event->fee !== null) {
            $changes['fee'] = $event->fee->value();
        }
        if ($event->status === ShipmentStatus::CANCELLED) {
            $changes['cancelled_at'] = now();
        }
        $shipment->forceFill($changes)->save();
        $this->applyToOrder($shipment);
    }

    /**
     * Record reconciled COD cash on the order when the store enabled it — idempotent on
     * "<pluginKey>:<tracking>" (US-shop-shipping-cod, RISK-BIZ-shipping-cod-double-record).
     *
     * @param ShopShipment  $shipment Shipment.
     * @param TrackingEvent $event    Event carrying codSettled.
     * @return void
     */
    private function recordCod(ShopShipment $shipment, TrackingEvent $event): void
    {
        if (!ShippingSettings::codAutoRecord((string) $shipment->store_id)) {
            return;
        }
        $order = ShopOrder::find($shipment->order_id);
        if ($order === null || (float) $order->balance <= 0) {
            return;
        }
        $reported = $event->codAmount ?? new Money((string) $shipment->cod_amount, (string) $shipment->currency);
        $inOrderCurrency = ShippingCurrency::convert($reported, (string) $order->currency);
        if ($inOrderCurrency === null || $inOrderCurrency->value() <= 0) {
            return;
        }
        $balanceBefore = (float) $order->balance;
        $amount = min($inOrderCurrency->value(), $balanceBefore);
        $order->recordPayment($amount, $shipment->plugin_key, $shipment->plugin_key . ':' . $shipment->tracking_number, now(),
            gp247_language_render('admin.shipment.history_cod', ['amount' => $amount, 'carrier' => e($shipment->plugin_key), 'tracking' => e((string) $shipment->tracking_number)]));
        if (abs($inOrderCurrency->value() - $balanceBefore) > 0.01) {
            $this->history($order, gp247_language_render('admin.shipment.history_cod_mismatch', ['carrier_amount' => $inOrderCurrency->value(), 'balance' => $balanceBefore]));
        }
    }

    /**
     * @param ShopOrder                 $order    Order.
     * @param string                    $key      Provider key.
     * @param array<string, mixed>      $input    Form values.
     * @param CreatesShipments          $provider Provider (for its extra fields).
     * @return ShipmentRequest
     */
    private function buildRequest(ShopOrder $order, string $key, array $input, CreatesShipments $provider, int $attempt = 1): ShipmentRequest
    {
        $currency = (string) $order->currency;
        $items = $this->orderItems($order, $currency);
        $items = $this->rescale($items, (int) ($input['weight_grams'] ?? 0));
        $allowed = array_column($provider->shipmentFormFields(), 'name');
        $extra = array_intersect_key((array) ($input['extra'] ?? []), array_flip($allowed));

        return new ShipmentRequest(
            orderId: (string) $order->id,
            storeId: (string) $order->store_id,
            pluginKey: $key,
            serviceCode: (string) ($input['service_code'] ?? ''),
            origin: ShippingSettings::origin((string) $order->store_id),
            destination: new ShippingAddress(
                name: (string) ($input['name'] ?? ''),
                phone: (string) ($input['phone'] ?? ''),
                country: strtoupper((string) ($order->country ?: 'VN')),
                province: (string) ($input['province'] ?? ''),
                district: (string) ($input['district'] ?? ''),
                ward: (string) ($input['ward'] ?? ''),
                line1: (string) ($input['address'] ?? ''),
                line2: (string) ($input['address2'] ?? ''),
                postcode: (string) $order->postcode,
                email: (string) $order->email,
            ),
            items: $items,
            declaredValue: new Money((string) max(0, (float) ($input['declared_value'] ?? $order->subtotal)), $currency),
            codAmount: new Money((string) max(0, (float) ($input['cod_amount'] ?? 0)), $currency),
            note: mb_substr((string) ($input['note'] ?? ''), 0, 500),
            extra: $extra,
            attempt: $attempt,
            parcel: new Parcel(
                max(0, (int) ($input['weight_grams'] ?? 0)) ?: array_sum(array_map(static fn (ShippingItem $i): int => $i->lineWeightGrams(), $items)),
                max(0, (int) ($input['length_cm'] ?? 0)),
                max(0, (int) ($input['width_cm'] ?? 0)),
                max(0, (int) ($input['height_cm'] ?? 0)),
            ),
        );
    }

    /**
     * @param ShopOrder $order    Order.
     * @param string    $currency Order currency.
     * @return array<int, ShippingItem>
     */
    private function orderItems(ShopOrder $order, string $currency): array
    {
        $default = ShippingSettings::defaultWeightGrams((string) $order->store_id);
        $items = [];
        foreach ($order->details as $detail) {
            $qty = max(1, (int) round((float) $detail->qty));
            $value = new Money((string) (float) $detail->price, $currency);
            $product = ShopProduct::find($detail->product_id);
            if ($product === null) {
                // Product deleted since the order: ship the line with what the order still knows.
                $items[] = new ShippingItem((string) $detail->name, $qty, $default, $value, (string) ($detail->sku ?? ''));
                continue;
            }
            // One shipping profile for quote and booking (ADR shop-shipping_product-shipping-profile).
            $item = ShippingItemFactory::fromProduct($product, $qty, $detail->attribute, $value, (string) $order->store_id, (string) $detail->name);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param ShopOrder $order Order.
     * @return int Parcel weight in grams computed from the order lines.
     */
    private function orderWeightGrams(ShopOrder $order): int
    {
        $sum = 0;
        foreach ($this->orderItems($order, (string) $order->currency) as $item) {
            $sum += $item->lineWeightGrams();
        }

        return $sum;
    }

    /**
     * Scale unit weights so the parcel matches the weight the admin weighed.
     *
     * @param array<int, ShippingItem> $items  Lines.
     * @param int                      $target Grams typed by the admin (0 = keep).
     * @return array<int, ShippingItem>
     */
    private function rescale(array $items, int $target): array
    {
        $current = array_sum(array_map(static fn (ShippingItem $i): int => $i->lineWeightGrams(), $items));
        if ($target <= 0 || $current <= 0 || $target === $current) {
            return $items;
        }
        $ratio = $target / $current;

        return array_map(static fn (ShippingItem $i): ShippingItem => $i->with(['weightGrams' => max(1, (int) round($i->weightGrams * $ratio))]), $items);
    }

    /**
     * @param ShopOrder $order   Order.
     * @param string    $content History text.
     * @param string    $adminId Admin id ('0' = system).
     * @return void
     */
    private function history(ShopOrder $order, string $content, string $adminId = '0'): void
    {
        $order->addOrderHistory([
            'order_id' => $order->id,
            'content' => $content,
            'admin_id' => $adminId !== '' ? $adminId : '0',
            'order_status_id' => (int) $order->status,
        ]);
    }
}
