{{--
    Shop configuration (shop-admin Unit). Tabbed config sections; one Save writes
    all keys back through AdminConfig. Tabs via Alpine (no jQuery). UI text via
    gp247_language_render.

    Tabs with section='basic'+'attribute'+'attribute_required' fields render as a
    two-column layout (basic settings left, attribute table right) matching the
    original legacy layout. Other tabs render as a flat list.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-005
    @aidlc-adr ADR-006, ADR-007

    Variables: $tabs (array of {id, label, fields[]}), $values bound via wire:model.
--}}
<div x-data="{ tab: '{{ $tabs[0]['id'] ?? 'product' }}' }">
    <form wire:submit="save">
        <x-gp247::card>
            {{-- Tab nav --}}
            <div class="mb-4 flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
                @foreach ($tabs as $tab)
                    <button type="button" x-on:click="tab = '{{ $tab['id'] }}'"
                        :class="tab === '{{ $tab['id'] }}'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                        class="border-b-2 px-4 py-2 text-sm font-medium transition">
                        {{ gp247_language_render($tab['label']) }}
                    </button>
                @endforeach
            </div>

            {{-- Tab panels --}}
            @foreach ($tabs as $tab)
                @php
                    $basicFields = collect($tab['fields'])->filter(fn ($f) => ($f['section'] ?? '') === 'basic')->values();
                    $attrFields  = collect($tab['fields'])->filter(fn ($f) => ($f['section'] ?? '') === 'attribute')->values();
                    $reqFields   = collect($tab['fields'])->filter(fn ($f) => ($f['section'] ?? '') === 'attribute_required')->keyBy('key');
                    $flatFields  = collect($tab['fields'])->filter(fn ($f) => ($f['section'] ?? '') === '')->values();
                    $hasAttrTable = $attrFields->isNotEmpty();
                @endphp

                <div x-show="tab === '{{ $tab['id'] }}'" x-cloak>
                    @if ($tab['id'] === 'sendmail')
                        {{-- Sendmail: status badge + note, then checkboxes --}}
                        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                {!! gp247_language_render('admin.shop.config_sendmail_status') !!}:
                                <span class="ml-1 inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $sendmailStatus ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' }}">
                                    {{ $sendmailStatus ? 'ON' : 'OFF' }}
                                </span>
                            </h4>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                {!! gp247_language_render('admin.shop.config_sendmail_note', ['url' => $adminConfigUrl]) !!}
                            </p>
                        </div>

                        <div class="space-y-3">
                            @forelse ($flatFields as $field)
                                @php $key = $field['key']; $label = gp247_language_render($field['label']) . ($field['labelSuffix'] ?? ''); @endphp
                                <x-gp247::checkbox :label="$label" wire:model="values.{{ $key }}" value="1" :disabled="(bool) $field['disabled']" />
                            @empty
                                <p class="text-sm text-gray-400">{{ gp247_language_render('admin.no_records') }}</p>
                            @endforelse
                        </div>

                    @elseif ($tab['id'] === 'limit')
                        {{-- Limit per page: 3-column table (Detail | Key | Value) --}}
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="py-2 pr-4 text-left font-semibold text-gray-700 dark:text-gray-200">
                                            {{ gp247_language_quickly('admin.admin_custom_config.add_new_detail', 'Key detail') }}
                                        </th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">
                                            {{ gp247_language_quickly('admin.admin_custom_config.add_new_key', 'Key') }}
                                        </th>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">
                                            {{ gp247_language_quickly('admin.admin_custom_config.add_new_value', 'Value') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @forelse ($flatFields as $field)
                                        @php $key = $field['key']; @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-200">
                                                {{ gp247_language_render($field['label']) }}
                                            </td>
                                            <td class="px-4 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">
                                                {{ $key }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" wire:model="values.{{ $key }}" @disabled($field['disabled'])
                                                    class="block w-32 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="py-3 text-sm text-gray-400">{{ gp247_language_render('admin.no_records') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                    @elseif ($hasAttrTable)
                        {{-- Two-column layout: basic settings | attribute table --}}
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                            {{-- Left: basic settings --}}
                            <div class="space-y-3">
                                @forelse ($basicFields as $field)
                                    @php
                                        $key   = $field['key'];
                                        $label = gp247_language_render($field['label']);
                                    @endphp
                                    @if ($field['type'] === 'checkbox')
                                        <x-gp247::checkbox :label="$label" wire:model="values.{{ $key }}" value="1" :disabled="(bool) $field['disabled']" />
                                    @elseif ($field['type'] === 'select')
                                        <div class="flex flex-wrap items-center gap-3">
                                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $label }}</span>
                                            <div class="flex-1" style="min-width:160px">
                                                <x-gp247::searchable-select
                                                    model="values.{{ $key }}"
                                                    :options="collect($field['options'])->map(fn ($optLabel, $optValue) => ['id' => (string) $optValue, 'label' => $optLabel])->values()->all()"
                                                    :disabled="$field['disabled']"
                                                />
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex flex-wrap items-center gap-3">
                                            <label class="text-sm text-gray-700 dark:text-gray-200">{{ $label }}</label>
                                            <input type="text" wire:model="values.{{ $key }}" @disabled($field['disabled'])
                                                class="block flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                        </div>
                                    @endif
                                @empty
                                    <p class="text-sm text-gray-400">-</p>
                                @endforelse
                            </div>

                            {{-- Right: attribute table (Field | Value | Required) --}}
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="py-2 pr-4 text-left font-semibold text-gray-700 dark:text-gray-200">
                                                {{ gp247_language_render('admin.product.config_manager.field') }}
                                            </th>
                                            <th class="px-4 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">
                                                {{ gp247_language_render('admin.product.config_manager.value') }}
                                            </th>
                                            <th class="px-4 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">
                                                {{ gp247_language_render('admin.product.config_manager.required') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @foreach ($attrFields as $attrField)
                                            @php
                                                $attrKey  = $attrField['key'];
                                                $reqField = $reqFields->get($attrKey . '_required');
                                            @endphp
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-200">
                                                    {{ gp247_language_render($attrField['label']) }}
                                                </td>
                                                <td class="px-4 py-2">
                                                    <div class="flex justify-center">
                                                        <x-gp247::checkbox wire:model="values.{{ $attrKey }}" value="1" :disabled="(bool) $attrField['disabled']" />
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2">
                                                    @if ($reqField)
                                                        <div class="flex justify-center">
                                                            <x-gp247::checkbox wire:model="values.{{ $reqField['key'] }}" value="1" />
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    @else
                        {{-- Flat list: order, sendmail, limit, layout, captcha --}}
                        <div class="space-y-3">
                            @forelse ($flatFields as $field)
                                @php $key = $field['key']; $label = gp247_language_render($field['label']) . ($field['labelSuffix'] ?? ''); @endphp

                                @if ($field['type'] === 'checkbox')
                                    <x-gp247::checkbox :label="$label" wire:model="values.{{ $key }}" value="1" :disabled="(bool) $field['disabled']" />

                                @elseif ($field['type'] === 'checklist')
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:items-start">
                                        <label class="text-sm text-gray-700 dark:text-gray-200 sm:col-span-1">{{ $label }}</label>
                                        <div class="flex flex-wrap gap-3 sm:col-span-2">
                                            @foreach ($field['options'] as $optValue => $optLabel)
                                                <x-gp247::checkbox :label="$optLabel" wire:model="values.{{ $key }}" value="{{ $optValue }}" :disabled="(bool) $field['disabled']" />
                                            @endforeach
                                        </div>
                                    </div>

                                @elseif ($field['type'] === 'select')
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:items-center">
                                        <label class="text-sm text-gray-700 dark:text-gray-200 sm:col-span-1">{{ $label }}</label>
                                        <div class="sm:col-span-2">
                                            <x-gp247::searchable-select
                                                model="values.{{ $key }}"
                                                :options="collect($field['options'])->map(fn ($optLabel, $optValue) => ['id' => (string) $optValue, 'label' => $optLabel])->values()->all()"
                                                :disabled="$field['disabled']"
                                            />
                                        </div>
                                    </div>

                                @elseif ($field['type'] === 'number')
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:items-center">
                                        <label class="text-sm text-gray-700 dark:text-gray-200 sm:col-span-1">{{ $label }}</label>
                                        <input type="number" wire:model="values.{{ $key }}" @disabled($field['disabled'])
                                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 sm:col-span-2">
                                    </div>

                                @else
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:items-center">
                                        <label class="text-sm text-gray-700 dark:text-gray-200 sm:col-span-1">{{ $label }}</label>
                                        <input type="text" wire:model="values.{{ $key }}" @disabled($field['disabled'])
                                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 sm:col-span-2">
                                    </div>
                                @endif
                            @empty
                                <p class="text-sm text-gray-400">{{ gp247_language_render('admin.no_records') }}</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="mt-6 flex items-center justify-end border-t border-gray-200 pt-4 dark:border-gray-700">
                <x-gp247::button type="submit" wire:loading.attr="disabled">
                    <i class="fas fa-save"></i> {{ gp247_language_render('admin.save') }}
                </x-gp247::button>
            </div>
        </x-gp247::card>
    </form>
</div>
