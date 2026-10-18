{{--
    Shipping block of the order detail screen (OrderShipmentPanel, Unit shop-shipping).
    Shows the rate the shopper chose, the order's shipments with their timeline, and
    the actions the carrier really supports (create / cancel / label / sync).
    TailAdmin-only (<x-gp247::*>), no jQuery (rule ui-tailadmin P2).

    Variables: $orderRow, $shipments, $active, $capabilities, $capableKeys,
    $extraFields, $snapshot, $locked.

    @aidlc-unit shop-shipping
    @aidlc-story US-shop-shipping-shipment-lifecycle
    @aidlc-story US-shop-shipping-tracking-webhook
--}}
@php
    $inputCls = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
    $labelCls = 'mb-1 block text-xs text-gray-500 dark:text-gray-400';
    $statusColor = [
        'pending' => 'gray', 'picked_up' => 'blue', 'in_transit' => 'blue', 'out_for_delivery' => 'blue',
        'delivery_failed' => 'red', 'delivered' => 'green', 'returning' => 'amber', 'returned' => 'amber',
        'cancelled' => 'gray', 'exception' => 'red',
    ];
@endphp
<div data-testid="order-shipment-panel">
<x-gp247::card :title="gp247_language_render('admin.shipment.title')">
    @if ($snapshot)
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-300" data-testid="order-shipment-chosen-rate">
            {{ $snapshot->title }} &mdash; {{ gp247_currency_render_symbol($snapshot->displayAmount, $orderRow->currency ?? null) }}
            @if ($snapshot->providerAmount->currency !== ($snapshot->displayCurrency ?? ''))
                <span class="text-xs text-gray-400">({{ $snapshot->providerAmount->amount }} {{ $snapshot->providerAmount->currency }})</span>
            @endif
        </p>
    @endif

    @forelse ($shipments as $shipment)
        @php
            $caps = $capabilities[$shipment->plugin_key] ?? [];
            $status = $shipment->shipmentStatus();
        @endphp
        <div class="mb-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700" wire:key="shipment-{{ $shipment->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm text-gray-700 dark:text-gray-200">
                    <span class="font-semibold">{{ $shipment->plugin_key }}</span>
                    @if ($shipment->service_code)<span class="text-gray-400">/ {{ $shipment->service_code }}</span>@endif
                    &middot;
                    @if ($shipment->tracking_url && preg_match('#^https?://#i', $shipment->tracking_url))
                        <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline" data-testid="order-shipment-tracking">{{ $shipment->tracking_number }}</a>
                    @else
                        <span data-testid="order-shipment-tracking">{{ $shipment->tracking_number }}</span>
                    @endif
                </div>
                <x-gp247::badge :color="$statusColor[$status->value] ?? 'gray'" data-testid="order-shipment-status">{{ gp247_language_render($status->labelKey()) }}</x-gp247::badge>
            </div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ gp247_language_render('admin.shipment.fee') }}: {{ number_format((float) $shipment->fee, 0) }} {{ $shipment->currency }}
                @if ((float) $shipment->cod_amount > 0)
                    &middot; {{ gp247_language_render('admin.shipment.cod_amount') }}: {{ number_format((float) $shipment->cod_amount, 0) }} {{ $shipment->currency }}
                @endif
                &middot; {{ gp247_language_render('admin.shipment.weight') }}: {{ (int) $shipment->weight_grams }}
            </div>

            @if ($shipment->isActive())
                <div class="mt-2 flex flex-wrap gap-2">
                    @if (in_array('documents', $caps, true))
                        <x-gp247::button size="sm" variant="secondary" wire:click="downloadLabel('{{ $shipment->id }}')" data-testid="order-shipment-label">{{ gp247_language_render('admin.shipment.label') }}</x-gp247::button>
                    @endif
                    @if (in_array('tracking', $caps, true) && !$status->isFinal())
                        <x-gp247::button size="sm" variant="secondary" wire:click="syncShipment('{{ $shipment->id }}')" wire:loading.attr="disabled" data-testid="order-shipment-sync">{{ gp247_language_render('admin.shipment.sync') }}</x-gp247::button>
                    @endif
                    @if (in_array('cancel', $caps, true) && !$status->isFinal() && !$locked)
                        <x-gp247::button size="sm" variant="danger" wire:click="cancelShipment('{{ $shipment->id }}')" wire:confirm="{{ gp247_language_render('admin.shipment.cancel_confirm') }}" data-testid="order-shipment-cancel">{{ gp247_language_render('admin.shipment.cancel') }}</x-gp247::button>
                    @endif
                </div>
            @endif

            @if ($shipment->events->isNotEmpty())
                <details class="mt-2">
                    <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.shipment.timeline') }}</summary>
                    <ol class="mt-1 space-y-1">
                        @foreach ($shipment->events as $event)
                            <li class="text-xs text-gray-600 dark:text-gray-300" wire:key="shipment-event-{{ $event->id }}">
                                {{ optional($event->occurred_at)->format('d/m/Y H:i') }} &middot;
                                {{ $event->status ? gp247_language_render('admin.shipment.status_' . $event->status) : $event->provider_status }}
                                @if ($event->message) &mdash; {{ $event->message }}@endif
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endif
        </div>
    @empty
        <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.shipment.none') }}</p>
    @endforelse

    @if (!$active && !$locked)
        @if ($capableKeys === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.shipment.no_provider') }}</p>
        @elseif (!$creating)
            <x-gp247::button size="sm" wire:click="openCreate" data-testid="order-shipment-create">{{ gp247_language_render('admin.shipment.create') }}</x-gp247::button>
        @else
            <form wire:submit="createShipment" class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2" data-testid="order-shipment-form">
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('admin.shipment.provider') }}</label>
                    <select wire:model.live="form.plugin_key" class="{{ $inputCls }}" data-testid="order-shipment-form-provider">
                        @foreach ($capableKeys as $key)
                            <option value="{{ $key }}">{{ $key }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('admin.shipment.service') }}</label>
                    <input type="text" wire:model="form.service_code" class="{{ $inputCls }}" data-testid="order-shipment-form-service">
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('admin.shipment.recipient') }} *</label>
                    <input type="text" wire:model="form.name" class="{{ $inputCls }}">
                    @error('form.name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('customer.phone') }} *</label>
                    <input type="text" wire:model="form.phone" class="{{ $inputCls }}">
                    @error('form.phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('customer.city') }} *</label>
                    <input type="text" wire:model="form.province" class="{{ $inputCls }}">
                    @error('form.province')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('customer.district') }}</label>
                    <input type="text" wire:model="form.district" class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('customer.ward') }}</label>
                    <input type="text" wire:model="form.ward" class="{{ $inputCls }}" data-testid="order-shipment-form-ward">
                    @error('form.ward')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('customer.address1') }} *</label>
                    <input type="text" wire:model="form.address" class="{{ $inputCls }}">
                    @error('form.address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('admin.shipment.weight') }} *</label>
                    <input type="number" min="1" wire:model="form.weight_grams" class="{{ $inputCls }}" data-testid="order-shipment-form-weight">
                    @error('form.weight_grams')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('admin.shipment.parcel_size') }}</label>
                    <div class="grid grid-cols-3 gap-2">
                        <input type="number" min="0" max="1000" wire:model="form.length_cm" placeholder="{{ gp247_language_render('admin.shipment.length') }}" class="{{ $inputCls }}" data-testid="order-shipment-form-length">
                        <input type="number" min="0" max="1000" wire:model="form.width_cm" placeholder="{{ gp247_language_render('admin.shipment.width') }}" class="{{ $inputCls }}" data-testid="order-shipment-form-width">
                        <input type="number" min="0" max="1000" wire:model="form.height_cm" placeholder="{{ gp247_language_render('admin.shipment.height') }}" class="{{ $inputCls }}" data-testid="order-shipment-form-height">
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.shipment.parcel_size_hint') }}</p>
                    @error('form.length_cm')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('admin.shipment.declared_value') }} ({{ $form['currency'] ?? '' }})</label>
                    <input type="number" min="0" step="any" wire:model="form.declared_value" class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('admin.shipment.cod_amount') }} ({{ $form['currency'] ?? '' }})</label>
                    <input type="number" min="0" step="any" wire:model="form.cod_amount" class="{{ $inputCls }}" data-testid="order-shipment-form-cod">
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $labelCls }}">{{ gp247_language_render('admin.shipment.note') }}</label>
                    <input type="text" wire:model="form.note" maxlength="500" class="{{ $inputCls }}">
                </div>
                @foreach ($extraFields as $field)
                    @php
                        $fname = (string) ($field['name'] ?? '');
                        $flabel = gp247_language_render((string) ($field['label'] ?? $fname));
                        $ftype = (string) ($field['type'] ?? 'text');
                    @endphp
                    @if (preg_match('/^[a-z0-9_]{1,50}$/', $fname))
                    <div wire:key="extra-{{ $fname }}">
                        <label class="{{ $labelCls }}">{{ $flabel }}@if (!empty($field['required'])) *@endif</label>
                        @if ($ftype === 'select')
                            <select wire:model="form.extra.{{ $fname }}" class="{{ $inputCls }}">
                                <option value=""></option>
                                @foreach ((array) ($field['options'] ?? []) as $value => $text)
                                    <option value="{{ $value }}">{{ gp247_language_render((string) $text) }}</option>
                                @endforeach
                            </select>
                        @elseif ($ftype === 'checkbox')
                            <input type="checkbox" wire:model="form.extra.{{ $fname }}" value="1">
                        @else
                            <input type="text" wire:model="form.extra.{{ $fname }}" maxlength="{{ (int) ($field['max'] ?? 255) }}" class="{{ $inputCls }}">
                        @endif
                        @error('form.extra.' . $fname)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @endif
                @endforeach
                <div class="flex gap-2 sm:col-span-2">
                    <x-gp247::button type="submit" size="sm" wire:loading.attr="disabled" data-testid="order-shipment-form-submit">{{ gp247_language_render('admin.shipment.create') }}</x-gp247::button>
                    <x-gp247::button type="button" size="sm" variant="ghost" wire:click="closeCreate">{{ gp247_language_render('admin.shipment.close') }}</x-gp247::button>
                </div>
            </form>
        @endif
    @endif
</x-gp247::card>
</div>
