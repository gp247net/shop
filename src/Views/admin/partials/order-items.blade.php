{{--
    Order line-items sub-panel (group E, US-SADM-003): the items table + an
    add/edit form with a product picker (sku/alias search, TailAdmin-only — no
    Select2/jQuery, rule ui-tailadmin P2). Add/edit/delete each recalc the order
    totals via the component. Variables: $items, $itemForm, $editingItemId,
    $productSearch, $inputCls.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003
    @aidlc-adr ADR-006, ADR-007
--}}
<x-gp247::card :title="gp247_language_render('order.product')">
    <x-gp247::table :empty="empty($items) ? gp247_language_render('admin.core.no_records') : null">
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.sku') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.name') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.price') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.qty') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.total') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.core.action') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($items as $item)
            <tr wire:key="item-{{ $item['id'] }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $item['sku'] }}</td>
                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-100">{{ $item['name'] }}</td>
                <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ gp247_currency_render($item['price'], '', '', '', false) }}</td>
                <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ gp247_qty_format($item['qty']) }}</td>
                <td class="px-4 py-3 text-right text-sm font-medium text-gray-800 dark:text-gray-100">{{ gp247_currency_render($item['total_price'], '', '', '', false) }}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-1">
                        <x-gp247::button size="sm" variant="ghost" wire:click="editItem('{{ $item['id'] }}')"><i class="fas fa-edit"></i></x-gp247::button>
                        <x-gp247::button size="sm" variant="ghost" wire:click="deleteItem('{{ $item['id'] }}')" wire:confirm="{{ gp247_language_render('action.delete_confirm') }}"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-gp247::table>

    {{-- Add / edit line-item form --}}
    <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ gp247_language_render($editingItemId ? 'product.edit_product' : 'product.add_product') }}
        </p>

        @if (! $editingItemId)
            <div class="relative mb-3">
                <input type="search" wire:model.live.debounce.300ms="productSearch"
                    placeholder="{{ gp247_language_render('product.sku') }} / {{ gp247_language_render('admin.core.search') }}" class="{{ $inputCls }}">
                @php($results = $this->productResults())
                @if (is_countable($results) && count($results))
                    <div class="mt-1 rounded-lg border border-gray-200 dark:border-gray-700">
                        @foreach ($results as $p)
                            <button type="button" wire:click="selectProduct('{{ $p->id }}')"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                <span class="font-medium">{{ $p->sku }}</span> — {{ $p->getName() ?: $p->alias }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.name') }}</label>
                <input type="text" wire:model="itemForm.name" class="{{ $inputCls }}">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.price') }}</label>
                <input type="number" step="0.01" wire:model="itemForm.price" class="{{ $inputCls }}">
                @error('itemForm.price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.qty') }}</label>
                <input type="number" step="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}" min="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}" wire:model="itemForm.qty" class="{{ $inputCls }}">
                @error('itemForm.qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-3 flex items-center justify-end gap-2">
            @if ($editingItemId)
                <x-gp247::button variant="secondary" wire:click="newItem">{{ gp247_language_render('admin.core.cancel') }}</x-gp247::button>
            @endif
            <x-gp247::button wire:click="saveItem" wire:loading.attr="disabled">
                <i class="fas fa-save"></i> {{ gp247_language_render($editingItemId ? 'admin.core.update' : 'admin.core.submit') }}
            </x-gp247::button>
        </div>
    </div>
</x-gp247::card>
