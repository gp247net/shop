{{--
    "Shipping" tab of the shop configuration (Unit shop-shipping, US-shop-shipping-origin-config).
    One place for shipping: status + switch, carriers in use, warnings for what is missing,
    then the pick-up warehouse, the parcel default and tracking — each field with a hint.
    Fields stay editable while shipping is off, so the owner can prepare before switching on.

    Variables: $tab (id, fields[] with key/label/type/group/note), $shippingOverview
    (on, providers[key,title,caps,url], warnings[] lang codes, pluginsUrl).

    @aidlc-unit shop-shipping
    @aidlc-story US-shop-shipping-origin-config
--}}
@php
    $groups = [
        'origin' => ['title' => 'admin.shipping.group_origin', 'intro' => 'admin.shipping.group_origin_intro'],
        'parcel' => ['title' => 'admin.shipping.group_parcel', 'intro' => ''],
        'tracking' => ['title' => 'admin.shipping.group_tracking', 'intro' => ''],
    ];
    $byGroup = collect($tab['fields'])->groupBy('group');
    $statusField = $byGroup->get('status', collect())->first();
    $inputCls = 'block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
    $on = (bool) ($shippingOverview['on'] ?? false);
@endphp

{{-- ① Status --}}
<div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/40"
     data-testid="shop-config-shipping-status-{{ $on ? 'on' : 'off' }}">
    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
        {{ gp247_language_render('admin.shipping.status') }}:
        <span class="ml-1 inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $on ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' }}">
            {{ $on ? gp247_language_render('admin.shipping.status_on') : gp247_language_render('admin.shipping.status_off') }}
        </span>
    </h4>
    @if (!$on)
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ gp247_language_render('admin.shipping.status_off_note') }}</p>
    @endif

    @if ($statusField)
        <div class="mt-3">
            <x-gp247::checkbox :label="gp247_language_render($statusField['label'])" wire:model="values.{{ $statusField['key'] }}" value="1" data-testid="shop-config-shipping-enable" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render($statusField['note']) }}</p>
        </div>
    @endif

    <div class="mt-3">
        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ gp247_language_render('admin.shipping.providers') }}</p>
        @forelse ($shippingOverview['providers'] ?? [] as $provider)
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300" data-testid="shop-config-shipping-provider-{{ $provider['key'] }}">
                @if ($provider['url'] !== '')
                    <a href="{{ $provider['url'] }}" class="text-blue-600 hover:underline">{{ $provider['title'] }}</a>
                @else
                    {{ $provider['title'] }}
                @endif
                &middot; {{ implode(', ', array_map(static fn ($c) => gp247_language_render('admin.shipping.cap_' . $c), $provider['caps'])) }}
            </p>
        @empty
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                {{ gp247_language_render('admin.shipping.providers_none') }}
                <a href="{{ $shippingOverview['pluginsUrl'] ?? '#' }}" class="text-blue-600 hover:underline">{{ gp247_language_render('admin.shipping.open_plugins') }}</a>
            </p>
        @endforelse
    </div>

    @if (!empty($shippingOverview['warnings']))
        <ul class="mt-3 space-y-1" data-testid="shop-config-shipping-warnings">
            @foreach ($shippingOverview['warnings'] as $warning)
                <li class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">{{ $warning }}</li>
            @endforeach
        </ul>
    @endif
</div>

{{-- ②③④ Blocks --}}
@foreach ($groups as $groupId => $meta)
    @php $fields = $byGroup->get($groupId, collect()); @endphp
    @if ($fields->isNotEmpty())
        <div class="mb-4" data-testid="shop-config-shipping-group-{{ $groupId }}">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ gp247_language_render($meta['title']) }}</h4>
            @if ($meta['intro'] !== '')
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render($meta['intro']) }}</p>
            @endif
            <div class="mt-3 space-y-3">
                @foreach ($fields as $field)
                    @php $key = $field['key']; $label = gp247_language_render($field['label']); @endphp
                    @if ($field['type'] === 'checkbox')
                        <div>
                            <x-gp247::checkbox :label="$label" wire:model="values.{{ $key }}" value="1" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render($field['note']) }}</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:items-start">
                            <label class="text-sm text-gray-700 dark:text-gray-200 sm:col-span-1" for="shipcfg-{{ $key }}">{{ $label }}</label>
                            <div class="sm:col-span-2">
                                <input id="shipcfg-{{ $key }}" type="{{ $field['type'] === 'number' ? 'number' : 'text' }}" wire:model="values.{{ $key }}"
                                    data-testid="shop-config-shipping-{{ str_replace('_', '-', $key) }}" class="{{ $inputCls }}">
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render($field['note']) }}</p>
                                @error('values.' . $key)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
@endforeach
