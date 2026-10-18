<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\GP247AdminComponent;
use GP247\Shop\Admin\Models\AdminOrder;
use GP247\Shop\Shipping\Contracts\ShippingProvider;
use GP247\Shop\Shipping\Data\RateSnapshot;
use GP247\Shop\Shipping\Models\ShopShipment;
use GP247\Shop\Shipping\ShipmentService;
use GP247\Shop\Shipping\ShippingException;
use GP247\Shop\Shipping\ShippingProviderRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Shipping" block of the order detail screen: book a shipment with any carrier that
 * can, cancel it, download its label, sync it and read its timeline. Carrier-specific
 * inputs come from the provider (shipmentFormFields()); everything else is the
 * standard contract (US-shop-shipping-shipment-lifecycle).
 *
 * Gated like the order screen itself (admin_order: view on mount, `update` on every
 * mutating action). The order id and its store scope are passed by OrderManager and
 * locked, so a client cannot point the panel at another store's order.
 *
 * @aidlc-unit shop-shipping
 * @aidlc-unit shop-admin
 * @aidlc-story US-shop-shipping-shipment-lifecycle
 * @aidlc-story US-shop-shipping-tracking-webhook
 * @aidlc-adr shop-shipping_shipment-entity
 */
class OrderShipmentPanel extends GP247AdminComponent
{
    protected ?string $permission = 'admin_order';

    /** @var string Order being shipped. */
    #[Locked]
    public string $orderId = '';

    /** @var string|null Store the viewer may act on (null = every store, root multi-store). */
    #[Locked]
    public ?string $scopeStoreId = null;

    /** @var bool The create form is open. */
    public bool $creating = false;

    /** @var array<string, mixed> Create form values (see ShipmentService::defaults()). */
    public array $form = [];

    /**
     * @param string      $orderId      Order id.
     * @param string|null $scopeStoreId Store scope of the viewer.
     * @return void
     */
    public function mount(string $orderId = '', ?string $scopeStoreId = null): void
    {
        $prefix = defined('GP247_ADMIN_PREFIX') ? GP247_ADMIN_PREFIX : 'gp247_admin';
        $this->screenUri = $prefix . '/order';
        parent::mount();

        $this->orderId = $orderId;
        $this->scopeStoreId = ($scopeStoreId === '') ? null : $scopeStoreId;

        $order = $this->order();
        if ($order !== null && ($shipment = ShipmentService::instance()->activeShipment($order)) !== null) {
            ShipmentService::instance()->lazyRefresh($shipment);
        }
    }

    /**
     * Open the create form, prefilled from the order and its rate snapshot.
     *
     * @return void
     */
    public function openCreate(): void
    {
        $this->authorizeAction('update');
        $order = $this->order();
        if ($order === null) {
            return;
        }
        $this->form = ShipmentService::instance()->defaults($order);
        $this->creating = true;
    }

    /**
     * @return void
     */
    public function closeCreate(): void
    {
        $this->creating = false;
        $this->form = [];
        $this->resetValidation();
    }

    /**
     * Book the shipment with the selected carrier.
     *
     * @return void
     */
    public function createShipment(): void
    {
        $this->authorizeAction('update');
        $order = $this->order();
        if ($order === null) {
            return;
        }
        $key = (string) ($this->form['plugin_key'] ?? '');
        $rules = [
            'form.plugin_key' => ['required', 'string', \Illuminate\Validation\Rule::in(ShippingProviderRegistry::instance()->shipmentCapableKeys())],
            'form.service_code' => 'nullable|string|max:100',
            'form.name' => 'required|string|max:200',
            'form.phone' => 'required|string|max:30',
            'form.province' => 'required|string|max:100',
            'form.district' => 'nullable|string|max:100',
            'form.ward' => 'nullable|string|max:100',
            'form.address' => 'required|string|max:255',
            'form.address2' => 'nullable|string|max:255',
            'form.weight_grams' => 'required|integer|min:1|max:1000000',
            'form.length_cm' => 'nullable|integer|min:0|max:1000',
            'form.width_cm' => 'nullable|integer|min:0|max:1000',
            'form.height_cm' => 'nullable|integer|min:0|max:1000',
            'form.declared_value' => 'required|numeric|min:0',
            'form.cod_amount' => 'required|numeric|min:0',
            'form.note' => 'nullable|string|max:500',
        ];
        foreach ($this->providerFields($key) as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || !preg_match('/^[a-z0-9_]{1,50}$/', $name)) {
                continue;
            }
            $rules['form.extra.' . $name] = (!empty($field['required']) ? 'required' : 'nullable')
                . (($field['type'] ?? 'text') === 'checkbox' ? '|boolean' : '|string|max:' . (int) ($field['max'] ?? 255));
        }
        $validated = $this->validate($rules)['form'];
        $input = array_map(static fn ($v) => is_string($v) ? (string) gp247_clean(data: $v, hight: true) : $v, $validated);
        $input['extra'] = array_map(static fn ($v) => is_string($v) ? (string) gp247_clean(data: $v, hight: true) : $v, (array) ($validated['extra'] ?? []));

        try {
            $shipment = ShipmentService::instance()->create($order, $input, $this->adminId());
        } catch (ShippingException $e) {
            $this->notify('error', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            gp247_report('[GP247 shipping] create shipment for order ' . $order->id . ' failed: ' . $e->getMessage());
            $this->notify('error', gp247_language_render('admin.shipment.error'));

            return;
        }

        $this->closeCreate();
        $this->notify('success', gp247_language_render('admin.shipment.created', ['tracking' => (string) $shipment->tracking_number]));
        $this->dispatch('gp247-order-shipping-changed');
    }

    /**
     * @param string $shipmentId Shipment id.
     * @return void
     */
    public function cancelShipment(string $shipmentId): void
    {
        $this->authorizeAction('update');
        $shipment = $this->shipment($shipmentId);
        if ($shipment === null) {
            return;
        }
        try {
            ShipmentService::instance()->cancel($shipment, $this->adminId());
        } catch (ShippingException $e) {
            $this->notify('error', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            gp247_report('[GP247 shipping] cancel shipment ' . $shipment->id . ' failed: ' . $e->getMessage());
            $this->notify('error', gp247_language_render('admin.shipment.error'));

            return;
        }
        $this->notify('success', gp247_language_render('admin.shipment.cancelled'));
        $this->dispatch('gp247-order-shipping-changed');
    }

    /**
     * @param string $shipmentId Shipment id.
     * @return void
     */
    public function syncShipment(string $shipmentId): void
    {
        $this->authorizeAction('update');
        $shipment = $this->shipment($shipmentId);
        if ($shipment === null) {
            return;
        }
        try {
            ShipmentService::instance()->sync($shipment);
        } catch (ShippingException $e) {
            $this->notify('error', $e->getMessage());

            return;
        } catch (\Throwable $e) {
            gp247_report('[GP247 shipping] sync shipment ' . $shipment->id . ' failed: ' . $e->getMessage());
            $this->notify('error', gp247_language_render('admin.shipment.error'));

            return;
        }
        $this->notify('success', gp247_language_render('admin.shipment.synced'));
        $this->dispatch('gp247-order-shipping-changed');
    }

    /**
     * Stream the carrier label (the carrier token never reaches the browser).
     *
     * @param string $shipmentId Shipment id.
     * @return StreamedResponse|null
     */
    public function downloadLabel(string $shipmentId): ?StreamedResponse
    {
        $this->authorizeAction('update');
        $shipment = $this->shipment($shipmentId);
        if ($shipment === null) {
            return null;
        }
        try {
            $document = ShipmentService::instance()->document($shipment);
        } catch (ShippingException $e) {
            $this->notify('error', $e->getMessage());

            return null;
        }

        return response()->streamDownload(static function () use ($document): void {
            echo $document->content;
        }, preg_replace('/[^A-Za-z0-9._-]/', '_', $document->filename) ?: 'label.pdf', ['Content-Type' => $document->mime]);
    }

    /**
     * @return View
     */
    public function render(): View
    {
        $order = $this->order();
        $registry = ShippingProviderRegistry::instance();
        $shipments = $order !== null ? ShipmentService::instance()->shipments($order) : collect();
        $capabilities = [];
        foreach ($shipments->pluck('plugin_key')->unique() as $key) {
            $capabilities[$key] = $registry->capabilities((string) $key);
        }
        $capable = $registry->shipmentCapableKeys();
        $snapshot = $order !== null ? RateSnapshot::fromArray(json_decode((string) $order->shipping_rate, true)) : null;

        return view('gp247-shop-admin::partials.order-shipment-panel', [
            'orderRow' => $order,
            'shipments' => $shipments,
            'active' => $shipments->first(fn (ShopShipment $s): bool => $s->isActive()),
            'capabilities' => $capabilities,
            'capableKeys' => $capable,
            'extraFields' => $this->providerFields((string) ($this->form['plugin_key'] ?? '')),
            'snapshot' => $snapshot,
            'locked' => $order !== null && $order->isLocked(),
            'capRates' => ShippingProvider::CAP_RATES,
        ]);
    }

    /**
     * @return AdminOrder|null The order within the viewer's store scope.
     */
    private function order(): ?AdminOrder
    {
        if ($this->orderId === '') {
            return null;
        }

        return AdminOrder::getOrderAdmin($this->orderId, $this->scopeStoreId);
    }

    /**
     * @param string $shipmentId Shipment id.
     * @return ShopShipment|null Shipment of THIS order (never another one).
     */
    private function shipment(string $shipmentId): ?ShopShipment
    {
        $order = $this->order();
        if ($order === null) {
            return null;
        }

        return ShopShipment::where('id', $shipmentId)->where('order_id', $order->id)->first();
    }

    /**
     * @param string $key Provider key.
     * @return array<int, array<string, mixed>> Carrier-specific form fields.
     */
    private function providerFields(string $key): array
    {
        if ($key === '') {
            return [];
        }
        $provider = ShippingProviderRegistry::instance()->provider($key);
        if (!$provider instanceof \GP247\Shop\Shipping\Contracts\CreatesShipments) {
            return [];
        }
        try {
            return array_values(array_filter($provider->shipmentFormFields(), 'is_array'));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return string Current admin id ('0' when none).
     */
    private function adminId(): string
    {
        return (function_exists('admin') && admin()->user()) ? (string) admin()->user()->id : '0';
    }
}
