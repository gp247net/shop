{{--
    Product keyword-tag manager (shop-admin Unit) — two-panel: add/edit form (left)
    + list (right), on the core ResourcePanel base (P1). Mirrors the brand manager
    layout. Manages the shop_product_tag taxonomy (name, alias, sort, status). UI text
    via gp247_language_render.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-product-tags
    @aidlc-adr shop-admin_product-tag-storage, shop-admin_product-tag-input-ux

    Variables: $rows (ShopProductTag paginator); $form, $editingId, $sortField, $sortDir (component state).
--}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2" data-testid="shop-admin-product-tag-manager">

    {{-- Left: add / edit form --}}
    <x-gp247::card :title="gp247_language_render($editingId ? 'action.edit' : 'admin.product_tag.add_new')">
        <form wire:submit="save" class="space-y-4">
            @include('gp247-admin::partials.store-scope-picker', ['testid' => 'product-tag-store-select'])
            <x-gp247::input :label="gp247_language_render('admin.product_tag.name')" name="name"
                wire:model="form.name" :error="$errors->first('form.name')" required />
            <x-gp247::input :label="gp247_language_render('admin.product_tag.alias')" name="alias"
                wire:model="form.alias" :error="$errors->first('form.alias')" :help="gp247_language_render('admin.product_tag.alias_help')" />
            <x-gp247::input type="number" min="0" :label="gp247_language_render('admin.sort')"
                name="sort" wire:model="form.sort" :error="$errors->first('form.sort')" required />
            <x-gp247::checkbox :label="gp247_language_render('admin.active')" wire:model="form.status" value="1" />
            <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                <x-gp247::button variant="secondary" wire:click="cancelEdit" data-testid="admin-product-tag-form-cancel">{{ gp247_language_render($editingId ? 'admin.cancel' : 'admin.reset') }}</x-gp247::button>
                <x-gp247::button type="submit" data-testid="shop-admin-product-tag-submit" wire:loading.attr="disabled">
                    <i class="fas fa-save"></i> {{ gp247_language_render($editingId ? 'admin.update' : 'admin.submit') }}
                </x-gp247::button>
            </div>
        </form>
    </x-gp247::card>

    {{-- Right: list --}}
    <x-gp247::card :title="gp247_language_render('admin.product_tag.title')">
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="keyword" placeholder="{{ gp247_language_render('admin.product_tag.name') }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
        </div>

        <x-gp247::table :empty="$rows->isEmpty() ? gp247_language_render('admin.no_records') : null">
            <x-slot:head>
                <tr>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('name')">
                        {{ gp247_language_render('admin.product_tag.name') }} @if ($sortField === 'name')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.product_tag.alias') }}</th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('status')">
                        {{ gp247_language_render('admin.status') }} @if ($sortField === 'status')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.action') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ (string) $row->id === (string) $editingId ? 'bg-blue-100 border-l-4 border-blue-500 dark:bg-blue-900 dark:border-blue-500' : '' }}" wire:key="product-tag-{{ $row->id }}" data-testid="shop-admin-product-tag-row">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">
                        {{ $row->name }}
                        @include('gp247-admin::partials.store-scope-line', ['storeId' => $row->store_id])
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $row->alias }}</td>
                    <td class="px-4 py-3"><x-gp247::badge :color="$row->status ? 'green' : 'gray'">{{ $row->status ? gp247_language_render('admin.active') : gp247_language_render('admin.inactive') }}</x-gp247::badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <x-gp247::button size="sm" variant="ghost" wire:click="editRow('{{ $row->id }}')" data-testid="admin-product-tag-list-edit"><i class="fas fa-edit"></i></x-gp247::button>
                            <x-gp247::button size="sm" variant="ghost" wire:click="delete('{{ $row->id }}')" wire:confirm="{{ gp247_language_render('action.delete_confirm') }}"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-gp247::table>

        <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
    </x-gp247::card>
</div>
