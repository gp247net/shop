{{--
    Shared status manager (shop-admin Unit) — two-panel: add/edit form (left) +
    list (right), on the core ResourcePanel base (P1/P3). Used by the order /
    payment / shipping status managers; the component supplies route + title +
    protected ids via public accessors. UI text via gp247_language_render.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003
    @aidlc-adr ADR-005, ADR-006, ADR-007

    Variables: $rows (status paginator); $form, $editingId, $sortField, $sortDir (state).
--}}
@php($base = $this->routeBaseName())
@php($editBase = $this->routeEditName())
@php($protectedIds = $this->protectedIds())
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Left: add / edit form --}}
    <x-gp247::card :title="gp247_language_render($editingId ? 'action.edit' : 'action.add')">
        <form wire:submit="save" class="space-y-4">
            <x-gp247::input :label="gp247_language_render('admin.core.name')" name="name"
                wire:model="form.name" :error="$errors->first('form.name')" required />

            <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                <x-gp247::button variant="secondary" href="{{ gp247_route_admin($base) }}" wire:navigate>{{ gp247_language_render($editingId ? 'admin.core.cancel' : 'admin.core.reset') }}</x-gp247::button>
                <x-gp247::button type="submit" wire:loading.attr="disabled">
                    <i class="fas fa-save"></i> {{ gp247_language_render($editingId ? 'admin.core.update' : 'admin.core.submit') }}
                </x-gp247::button>
            </div>
        </form>
    </x-gp247::card>

    {{-- Right: list --}}
    <x-gp247::card :title="$this->titleText()">
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="keyword" placeholder="{{ gp247_language_render('admin.core.name') }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
        </div>

        <x-gp247::table :empty="$rows->isEmpty() ? gp247_language_render('admin.core.no_records') : null">
            <x-slot:head>
                <tr>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('id')">
                        ID @if ($sortField === 'id')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('name')">
                        {{ gp247_language_render('admin.core.name') }} @if ($sortField === 'name')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.core.action') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ (string) $row->id === (string) $editingId ? 'bg-blue-50 dark:bg-blue-900/30' : '' }}" wire:key="status-{{ $row->id }}">
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $row->id }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $row->name }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <x-gp247::button size="sm" variant="ghost" href="{{ gp247_route_admin($editBase, $row->id) }}" wire:navigate><i class="fas fa-edit"></i></x-gp247::button>
                            @unless (in_array((string) $row->id, $protectedIds, true))
                                <x-gp247::button size="sm" variant="ghost" wire:click="delete('{{ $row->id }}')" wire:confirm="{{ gp247_language_render('action.delete_confirm') }}"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                            @endunless
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-gp247::table>

        <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
    </x-gp247::card>
</div>
