{{--
    Customer manager (shop-admin Unit) — two-panel: add/edit form (left) + list
    (right), on the core ResourcePanel base + custom-fields trait (P1/P3). Mirrors
    the legacy customer screen: config-driven fields + custom fields. Validation /
    persistence reuse the brownfield helpers (see CustomerManager). The address
    sub-panel (1:N) renders on edit. UI text via gp247_language_render.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-004
    @aidlc-adr ADR-005, ADR-006, ADR-007

    Variables: $rows (customer paginator); $form, $customFields, $editingId, $sortField, $sortDir (state).
--}}
@php($inputCls = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Left: add / edit form --}}
    <x-gp247::card :title="gp247_language_render($editingId ? 'action.edit' : 'admin.customer.add_new_title')">
        <form wire:submit="save" class="space-y-4">
            @if (gp247_config_admin('customer_email'))
                <x-gp247::input type="email"
                    :label="gp247_language_render('customer.email') . (gp247_config_admin('customer_email_required') ? ' *' : '')"
                    name="email" wire:model="form.email" :error="$errors->first('form.email')"
                    :required="(bool) gp247_config_admin('customer_email_required')" />
            @endif

            <x-gp247::input :label="gp247_language_render('customer.first_name')" name="first_name"
                wire:model="form.first_name" :error="$errors->first('form.first_name')" required />

            @if (gp247_config_admin('customer_lastname'))
                <x-gp247::input :label="gp247_language_render('customer.last_name')" name="last_name"
                    wire:model="form.last_name" :error="$errors->first('form.last_name')" />
            @endif

            <x-gp247::input type="password" :label="gp247_language_render('customer.password') . ($editingId ? ' (' . gp247_language_render('admin.core.leave_blank_keep') . ')' : '')"
                name="password" wire:model="form.password" :error="$errors->first('form.password')" />
            <x-gp247::input type="password" :label="gp247_language_render('customer.confirm_password')"
                name="password_confirmation" wire:model="form.password_confirmation" />

            @if (gp247_config_admin('customer_phone'))
                <x-gp247::input :label="gp247_language_render('customer.phone')" name="phone"
                    wire:model="form.phone" :error="$errors->first('form.phone')" />
            @endif

            @if (gp247_config_admin('customer_country'))
                <x-gp247::searchable-select
                    model="form.country"
                    :label="gp247_language_render('customer.country')"
                    :options="collect($this->countryOptions())->map(fn ($name, $code) => ['id' => $code, 'label' => $name])->values()->all()"
                    :error="$errors->first('form.country')"
                />
            @endif

            @if (gp247_config_admin('customer_postcode'))
                <x-gp247::input :label="gp247_language_render('customer.postcode')" name="postcode"
                    wire:model="form.postcode" :error="$errors->first('form.postcode')" />
            @endif

            @if (gp247_config_admin('customer_address1') || true)
                <x-gp247::input :label="gp247_language_render('customer.address1')" name="address1"
                    wire:model="form.address1" :error="$errors->first('form.address1')" />
            @endif

            @if (gp247_config_admin('customer_address2'))
                <x-gp247::input :label="gp247_language_render('customer.address2')" name="address2"
                    wire:model="form.address2" :error="$errors->first('form.address2')" />
            @endif

            @if (gp247_config_admin('customer_address3'))
                <x-gp247::input :label="gp247_language_render('customer.address3')" name="address3"
                    wire:model="form.address3" :error="$errors->first('form.address3')" />
            @endif

            @if (gp247_config_admin('customer_company'))
                <x-gp247::input :label="gp247_language_render('customer.company')" name="company"
                    wire:model="form.company" :error="$errors->first('form.company')" />
            @endif

            @if (gp247_config_admin('customer_birthday'))
                <x-gp247::input type="date" :label="gp247_language_render('customer.birthday')" name="birthday"
                    wire:model="form.birthday" :error="$errors->first('form.birthday')" />
            @endif

            {{-- Custom fields (admin-defined) --}}
            @php($customDefs = $this->customFieldList())
            @if (is_countable($customDefs) && count($customDefs))
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.custom_field.title') }}</p>
                    <div class="space-y-3">
                        @foreach ($customDefs as $field)
                            @php($opts = json_decode($field->default ?? '', true) ?: [])
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200">{{ gp247_language_render($field->name) }}</label>
                                @switch($field->option)
                                    @case('textarea')
                                        <textarea wire:model="customFields.{{ $field->code }}" rows="2" class="{{ $inputCls }}"></textarea>
                                        @break
                                    @case('select')
                                        <select wire:model="customFields.{{ $field->code }}" class="{{ $inputCls }}">
                                            <option value="">--</option>
                                            @foreach ($opts as $optVal => $optLabel)<option value="{{ $optVal }}">{{ $optLabel }}</option>@endforeach
                                        </select>
                                        @break
                                    @case('checkbox')
                                        <div class="flex flex-wrap gap-3">
                                            @foreach ($opts as $optVal => $optLabel)
                                                <x-gp247::checkbox :label="$optLabel" wire:model="customFields.{{ $field->code }}" value="{{ $optVal }}" />
                                            @endforeach
                                        </div>
                                        @break
                                    @default
                                        <input type="text" wire:model="customFields.{{ $field->code }}" class="{{ $inputCls }}">
                                @endswitch
                                @error('customFields.' . $field->code)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-gp247::checkbox :label="gp247_language_render('admin.core.active')" wire:model="form.status" value="1" />

            <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                <x-gp247::button variant="secondary" href="{{ gp247_route_admin('admin_customer.index') }}" wire:navigate>{{ gp247_language_render($editingId ? 'admin.core.cancel' : 'admin.core.reset') }}</x-gp247::button>
                <x-gp247::button type="submit" wire:loading.attr="disabled">
                    <i class="fas fa-save"></i> {{ gp247_language_render($editingId ? 'admin.core.update' : 'admin.core.submit') }}
                </x-gp247::button>
            </div>
        </form>

        {{-- Address sub-panel (1:N) renders on edit --}}
        @if ($editingId)
            @include('gp247-shop-admin::partials.customer-addresses')
        @endif
    </x-gp247::card>

    {{-- Right: list --}}
    <x-gp247::card :title="gp247_language_render('admin.customer.list')">
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="keyword" placeholder="{{ gp247_language_render('search.placeholder') }}"
                class="{{ $inputCls }}">
        </div>

        <x-gp247::table :empty="$rows->isEmpty() ? gp247_language_render('admin.core.no_records') : null">
            <x-slot:head>
                <tr>
                    @if (gp247_config_admin('customer_email'))
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('email')">
                        {{ gp247_language_render('customer.email') }} @if ($sortField === 'email')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    @endif
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.name') }}</th>
                    @if (gp247_config_admin('customer_address1'))
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.address1') }}</th>
                    @endif
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.core.status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.core.action') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ (string) $row->id === (string) $editingId ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}" wire:key="customer-{{ $row->id }}">
                    @if (gp247_config_admin('customer_email'))
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $row->email }}</td>
                    @endif
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ trim($row->first_name . ' ' . $row->last_name) }}</td>
                    @if (gp247_config_admin('customer_address1'))
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $row->address1 }}</td>
                    @endif
                    <td class="px-4 py-3"><x-gp247::badge :color="$row->status ? 'green' : 'gray'">{{ $row->status ? gp247_language_render('admin.core.active') : gp247_language_render('admin.core.inactive') }}</x-gp247::badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <x-gp247::button size="sm" variant="ghost" href="{{ gp247_route_admin('admin_customer.edit', $row->id) }}" wire:navigate><i class="fas fa-edit"></i></x-gp247::button>
                            <x-gp247::button size="sm" variant="ghost" wire:click="delete('{{ $row->id }}')" wire:confirm="{{ gp247_language_render('action.delete_confirm') }}"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-gp247::table>

        <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
    </x-gp247::card>
</div>
