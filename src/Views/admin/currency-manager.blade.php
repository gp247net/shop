{{--
    Currency manager (shop-admin Unit) — two-panel: form (left) + list (right) on
    the core ResourcePanel base (P1). UI text via gp247_language_render.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-005
    @aidlc-adr ADR-005, ADR-006, ADR-007

    Variables: $rows (ShopCurrency paginator); $form, $editingId, $sortField, $sortDir.
--}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Left: add / edit form --}}
    <x-gp247::card :title="gp247_language_render($editingId ? 'action.edit' : 'admin.currency.add_new')">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gp247::input :label="gp247_language_render('admin.currency.name')" name="name"
                    wire:model="form.name" :error="$errors->first('form.name')" required />
                <x-gp247::input :label="gp247_language_render('admin.currency.code')" name="code"
                    wire:model="form.code" :error="$errors->first('form.code')" required />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-gp247::input :label="gp247_language_render('admin.currency.symbol')" name="symbol"
                    wire:model="form.symbol" :error="$errors->first('form.symbol')" required />
                <x-gp247::input type="number" step="0.0001" :label="gp247_language_render('admin.currency.exchange_rate')"
                    name="exchange_rate" wire:model="form.exchange_rate" :error="$errors->first('form.exchange_rate')" required />
                <x-gp247::input type="number" min="0" max="8" :label="gp247_language_render('admin.currency.precision')"
                    name="precision" wire:model="form.precision" :error="$errors->first('form.precision')" required />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="space-y-1">
                    <label for="symbol_first" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ gp247_language_render('admin.currency.symbol_first') }}</label>
                    <select id="symbol_first" wire:model="form.symbol_first"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        <option value="0">{{ gp247_language_render('admin.core.no') }}</option>
                        <option value="1">{{ gp247_language_render('admin.core.yes') }}</option>
                    </select>
                    @error('form.symbol_first') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-gp247::input :label="gp247_language_render('admin.currency.thousands')" name="thousands"
                    wire:model="form.thousands" :error="$errors->first('form.thousands')" required />
                <x-gp247::input type="number" min="0" :label="gp247_language_render('admin.core.sort')"
                    name="sort" wire:model="form.sort" :error="$errors->first('form.sort')" required />
            </div>
            <x-gp247::checkbox :label="gp247_language_render('admin.core.active')" wire:model="form.status" value="1" />
            <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                <x-gp247::button variant="secondary" href="{{ gp247_route_admin('admin_currency.index') }}" wire:navigate>{{ gp247_language_render($editingId ? 'admin.core.cancel' : 'admin.core.reset') }}</x-gp247::button>
                <x-gp247::button type="submit" wire:loading.attr="disabled">
                    <i class="fas fa-save"></i> {{ gp247_language_render($editingId ? 'admin.core.update' : 'admin.core.submit') }}
                </x-gp247::button>
            </div>
        </form>
    </x-gp247::card>

    {{-- Right: list --}}
    <x-gp247::card :title="gp247_language_render('admin.currency.title')">
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="keyword" placeholder="{{ gp247_language_render('admin.currency.name') }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
        </div>

        <x-gp247::table :empty="$rows->isEmpty() ? gp247_language_render('admin.core.no_records') : null">
            <x-slot:head>
                <tr>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('name')">
                        {{ gp247_language_render('admin.currency.name') }} @if ($sortField === 'name')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('code')">
                        {{ gp247_language_render('admin.currency.code') }} @if ($sortField === 'code')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('status')">
                        {{ gp247_language_render('admin.core.status') }} @if ($sortField === 'status')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.core.action') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ (string) $row->id === (string) $editingId ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}" wire:key="currency-{{ $row->id }}">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $row->name }} <span class="text-xs text-gray-400">{{ $row->symbol }}</span></td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $row->code }}</td>
                    <td class="px-4 py-3"><x-gp247::badge :color="$row->status ? 'green' : 'gray'">{{ $row->status ? gp247_language_render('admin.core.active') : gp247_language_render('admin.core.inactive') }}</x-gp247::badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <x-gp247::button size="sm" variant="ghost" href="{{ gp247_route_admin('admin_currency.edit', $row->id) }}" wire:navigate><i class="fas fa-edit"></i></x-gp247::button>
                            <x-gp247::button size="sm" variant="ghost" wire:click="delete({{ $row->id }})" wire:confirm="{{ gp247_language_render('action.delete_confirm') }}"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-gp247::table>

        <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
    </x-gp247::card>
</div>
