{{--
    Order manager (shop-admin Unit, group E, US-SADM-003) — one ResourcePanel
    component, two faces: the base route shows a store-scoped, filterable LIST;
    the edit/{id} route shows a bespoke DETAIL (customer info, line items, total
    breakdown, status workflow, history) with print/email actions. Renders on
    $editingId. TailAdmin-first, no jQuery/AdminLTE (rule ui-tailadmin P1/P2).
    UI text via gp247_language_render.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003
    @aidlc-adr ADR-005, ADR-006, ADR-007

    Variables: $rows (order paginator); $form, $order, $items, $totals, $history,
    $itemForm, $editingId, $editingItemId, $keyword, $filter* (state).
--}}
@php($inputCls = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100')

<div>
    @if ($editingId)
        @include('gp247-shop-admin::partials.order-detail', ['inputCls' => $inputCls])
    @else
        {{-- ===================== LIST ===================== --}}
        <x-gp247::card :title="gp247_language_render('admin.order.list')">
            <div class="mb-4 flex items-center justify-between">
                <div></div>
                <a href="{{ gp247_route_admin('admin_order.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-plus text-xs"></i>
                    {{ gp247_language_render('action.add') }}
                </a>
            </div>
            <div class="mb-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                <input type="search" wire:model.live.debounce.300ms="keyword"
                    placeholder="{{ gp247_language_render('search.placeholder') }}" class="{{ $inputCls }}">
                <select wire:model.live="filterStatus" class="{{ $inputCls }}">
                    <option value="">{{ gp247_language_render('admin.order_status.list') }}</option>
                    @foreach ($this->orderStatusOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <x-gp247::input type="date" wire:model.live="filterFrom" name="filterFrom"
                    placeholder="{{ gp247_language_render('admin.core.from_date') }}" />
                <x-gp247::input type="date" wire:model.live="filterTo" name="filterTo"
                    placeholder="{{ gp247_language_render('admin.core.to_date') }}" />
            </div>

            <x-gp247::table :empty="$rows->isEmpty() ? gp247_language_render('admin.core.no_records') : null">
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.id') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('customer.email') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('total')">
                            {{ gp247_language_render('order.totals.total') }} @if ($sortField === 'total')<span class="text-xs">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.payment_status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.shipping_status') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('status')">
                            {{ gp247_language_render('order.status') }} @if ($sortField === 'status')<span class="text-xs">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                        </th>
                        <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('created_at')">
                            {{ gp247_language_render('order.created_at') }} @if ($sortField === 'created_at')<span class="text-xs">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.core.action') }}</th>
                    </tr>
                </x-slot:head>

                @foreach ($rows as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="order-{{ $row->id }}">
                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $row->id }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $row->email }}</td>
                        <td class="px-4 py-3 text-right text-sm text-gray-800 dark:text-gray-100">{{ gp247_currency_render($row->total, '', '', '', false) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $this->paymentStatusOptions()[$row->payment_status] ?? $row->payment_status }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $this->shippingStatusOptions()[$row->shipping_status] ?? $row->shipping_status }}</td>
                        <td class="px-4 py-3"><x-gp247::badge :color="$this->statusBadgeColor($row->status)">{{ $this->orderStatusOptions()[$row->status] ?? $row->status }}</x-gp247::badge></td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $row->created_at }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <x-gp247::button size="sm" variant="ghost" href="{{ gp247_route_admin('gp247.shop-admin.order.edit', $row->id) }}" wire:navigate><i class="fas fa-eye"></i></x-gp247::button>
                                <x-gp247::button size="sm" variant="ghost" wire:click="delete('{{ $row->id }}')" wire:confirm="{{ gp247_language_render('action.delete_confirm') }}"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-gp247::table>

            <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
        </x-gp247::card>
    @endif
</div>
