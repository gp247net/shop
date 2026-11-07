{{--
    Tax manager (shop-admin Unit) — two-panel: form (left) + list (right) on the
    core ResourcePanel base (P1). UI text via gp247_language_render.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-005
    @aidlc-adr ADR-005, ADR-006, ADR-007

    Variables: $rows (ShopTax paginator); $form, $editingId, $sortField, $sortDir.
--}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Left: add / edit form --}}
    <x-gp247::card :title="gp247_language_render($editingId ? 'action.edit' : 'admin.tax.add_new')">
        <x-slot:actions>
            <x-gp247::form-new-button :editing="$editingId" data-testid="admin-tax-form-new" />
        </x-slot:actions>
        <form wire:submit="save" class="space-y-4">
            @include('gp247-admin::partials.store-scope-picker', ['testid' => 'tax-store-select'])
            <x-gp247::input :label="gp247_language_render('admin.tax.name')" name="name"
                wire:model="form.name" :error="$errors->first('form.name')" required />
            <x-gp247::input type="number" step="0.01" min="0" :label="gp247_language_render('admin.tax.value')"
                name="value" wire:model="form.value" :error="$errors->first('form.value')" required />
            <div class="flex items-center justify-end border-t border-gray-200 pt-4 dark:border-gray-700">
                <x-gp247::button type="submit" wire:loading.attr="disabled">
                    <i class="fas fa-save"></i> {{ gp247_language_render($editingId ? 'admin.update' : 'admin.submit') }}
                </x-gp247::button>
            </div>
        </form>
    </x-gp247::card>

    {{-- Right: list --}}
    <x-gp247::card :title="gp247_language_render('admin.tax.title')">
        <div class="mb-3">
            <x-gp247::search-box :placeholder="$this->searchPlaceholder()" :hint="$this->searchHint()" />
        </div>

        <x-gp247::table :empty="$rows->isEmpty() ? $this->searchEmptyMessage() : null">
            <x-slot:head>
                <tr>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('name')">
                        {{ gp247_language_render('admin.tax.name') }} @if ($sortField === 'name')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('value')">
                        {{ gp247_language_render('admin.tax.value') }} @if ($sortField === 'value')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.action') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ (string) $row->id === (string) $editingId ? 'bg-blue-100 border-l-4 border-blue-500 dark:bg-blue-900 dark:border-blue-500' : '' }}" wire:key="tax-{{ $row->id }}">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">
                        {{ $row->name }}
                        @include('gp247-admin::partials.store-scope-line', ['storeId' => $row->store_id])
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $row->value }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <x-gp247::button size="sm" variant="ghost" wire:click="editRow('{{ $row->id }}')" data-testid="admin-tax-list-edit"><i class="fas fa-edit"></i></x-gp247::button>
                            <x-gp247::button size="sm" variant="ghost" wire:click="delete('{{ $row->id }}')" wire:confirm="{{ gp247_language_render('action.delete_confirm') }}"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-gp247::table>

        <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
    </x-gp247::card>
</div>
