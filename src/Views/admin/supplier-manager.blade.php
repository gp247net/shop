{{--
    Supplier manager (shop-admin Unit) — two-panel: form (left) + list (right) on
    the core ResourcePanel base (P1). UI text via gp247_language_render.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-002
    @aidlc-adr ADR-005, ADR-006, ADR-007

    Variables: $rows (ShopSupplier paginator); $form, $editingId, $sortField, $sortDir.
--}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Left: add / edit form --}}
    <x-gp247::card :title="gp247_language_render($editingId ? 'action.edit' : 'admin.supplier.add_new')">
        <form wire:submit="save" class="space-y-4">
            <x-gp247::input :label="gp247_language_render('admin.supplier.name')" name="name"
                wire:model="form.name" :error="$errors->first('form.name')" required />
            <x-gp247::media-input :label="gp247_language_render('admin.supplier.image')" name="image" type="supplier"
                wire:model="form.image" :value="$form['image'] ?? ''" :error="$errors->first('form.image')" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gp247::input :label="gp247_language_render('admin.supplier.email')" name="email"
                    wire:model="form.email" :error="$errors->first('form.email')" />
                <x-gp247::input :label="gp247_language_render('admin.supplier.phone')" name="phone"
                    wire:model="form.phone" :error="$errors->first('form.phone')" />
            </div>
            <x-gp247::input :label="gp247_language_render('admin.supplier.url')" name="url"
                wire:model="form.url" :error="$errors->first('form.url')" />
            <x-gp247::input :label="gp247_language_render('admin.supplier.address')" name="address"
                wire:model="form.address" :error="$errors->first('form.address')" />
            <x-gp247::input type="number" min="0" :label="gp247_language_render('admin.sort')"
                name="sort" wire:model="form.sort" :error="$errors->first('form.sort')" required />
            <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                <x-gp247::button variant="secondary" href="{{ gp247_route_admin('admin_supplier.index') }}" wire:navigate>{{ gp247_language_render($editingId ? 'admin.cancel' : 'admin.reset') }}</x-gp247::button>
                <x-gp247::button type="submit" wire:loading.attr="disabled">
                    <i class="fas fa-save"></i> {{ gp247_language_render($editingId ? 'admin.update' : 'admin.submit') }}
                </x-gp247::button>
            </div>
        </form>
    </x-gp247::card>

    {{-- Right: list --}}
    <x-gp247::card :title="gp247_language_render('admin.supplier.title')">
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="keyword" placeholder="{{ gp247_language_render('admin.supplier.name') }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
        </div>

        <x-gp247::table :empty="$rows->isEmpty() ? gp247_language_render('admin.no_records') : null">
            <x-slot:head>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.supplier.image') }}</th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('name')">
                        {{ gp247_language_render('admin.supplier.name') }} @if ($sortField === 'name')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('email')">
                        {{ gp247_language_render('admin.supplier.email') }} @if ($sortField === 'email')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.action') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ (string) $row->id === (string) $editingId ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}" wire:key="supplier-{{ $row->id }}">
                    <td class="px-4 py-3">
                        @if ($row->image)<img src="{{ gp247_image_get_path_thumb($row->image) }}" alt="" class="h-9 w-auto rounded border border-gray-200 dark:border-gray-600">@else<span class="text-xs text-gray-400">—</span>@endif
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $row->name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $row->email }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <x-gp247::button size="sm" variant="ghost" href="{{ gp247_route_admin('admin_supplier.edit', $row->id) }}" wire:navigate><i class="fas fa-edit"></i></x-gp247::button>
                            <x-gp247::button size="sm" variant="ghost" wire:click="delete('{{ $row->id }}')" wire:confirm="{{ gp247_language_render('action.delete_confirm') }}"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-gp247::table>

        <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
    </x-gp247::card>
</div>
