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
    <x-gp247::table :empty="empty($items) ? gp247_language_render('admin.no_records') : null">
        <x-slot:head>
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.sku') }}</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.name') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.price') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.qty') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.total') }}</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.action') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($items as $item)
            <tr wire:key="item-{{ $item['id'] }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $item['sku'] }}</td>
                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-100">
                    {{ $item['name'] }}
                    {{-- Selected product attributes (name + surcharge), read-only. US-SADM-order-attribute-display.
                         $att['value'] is markup from gp247_render_option_price (safe: name is admin-managed,
                         add_price is server-authoritative — RISK-TECH-order-attribute-render-xss). --}}
                    @if (! empty($item['attributes']))
                        <div class="mt-1 space-y-0.5" data-testid="shop-admin-order-item-attribute">
                            @foreach ($item['attributes'] as $att)
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $att['name'] }}: {!! $att['value'] !!}</div>
                            @endforeach
                        </div>
                    @endif
                </td>
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

    {{-- Add-item trigger: collapse the form behind a single button (parity with
         the create-order screen), only expanding on demand. --}}
    @if (! $showItemForm)
        <div class="mt-4 flex justify-end">
            <x-gp247::button variant="success" wire:click="showAddItem" data-testid="shop-admin-order-item-add">
                <i class="fas fa-plus"></i> {{ gp247_language_render('product.add_product') }}
            </x-gp247::button>
        </div>
    @else
    {{-- Add / edit line-item form --}}
    <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ gp247_language_render($editingItemId ? 'product.edit_product' : 'product.add_product') }}
        </p>

        @if (! $editingItemId)
            <div class="relative mb-3">
                <input type="search" wire:model.live.debounce.300ms="productSearch" data-testid="shop-admin-order-item-search"
                    placeholder="{{ gp247_language_render('product.sku') }} / {{ gp247_language_render('admin.search') }}" class="{{ $inputCls }}">
                @php($results = $this->productResults())
                @if (is_countable($results) && count($results))
                    <div class="mt-1 rounded-lg border border-gray-200 dark:border-gray-700">
                        @foreach ($results as $p)
                            <button type="button" wire:click="selectProduct('{{ $p->id }}')" data-testid="shop-admin-order-item-result"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                <span class="font-medium">{{ $p->sku }}</span> — {{ $p->getName() ?: $p->alias }}
                                @php($inStock = (float) $p->stock > 0)
                                <x-gp247::badge :color="$inStock ? 'green' : 'red'" class="ml-1">{{ $inStock ? gp247_language_render('product.in_stock') : gp247_language_render('product.out_stock') }}: {{ gp247_qty_format($p->stock) }}</x-gp247::badge>
                            </button>
                        @endforeach
                    </div>
                @endif
                @error('itemForm.product_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.name') }}</label>
                <input type="text" wire:model="itemForm.name" data-testid="shop-admin-order-item-name" class="{{ $inputCls }}">
                @error('itemForm.name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.price') }}</label>
                <input type="number" step="0.01" wire:model="itemForm.price" data-testid="shop-admin-order-item-price" class="{{ $inputCls }}">
                @error('itemForm.price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.qty') }}</label>
                <input type="number" step="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}" min="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}" wire:model="itemForm.qty" data-testid="shop-admin-order-item-qty" class="{{ $inputCls }}">
                @error('itemForm.qty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                {{-- Per-line tax, restored by US-SADM-order-info-edit (legacy .edit-item-detail). --}}
                <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.tax') }}</label>
                <input type="number" step="0.01" min="0" wire:model="itemForm.tax" data-testid="shop-admin-order-item-tax" class="{{ $inputCls }}">
                @error('itemForm.tax')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Attribute selection (one <select> per group) — mandatory when the
             product has attributes. add_price is rebuilt server-side on save;
             the price field only carries the suggested effective price.
             US-SADM-order-item-attribute-select. --}}
        @if (! empty($itemAttrGroups))
            <div class="mt-3 grid grid-cols-2 gap-3">
                @foreach ($itemAttrGroups as $group)
                    <div>
                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">{{ $group['name'] }}</label>
                        <select wire:change="setItemAttribute('{{ $group['id'] }}', $event.target.value)"
                            data-testid="shop-admin-order-item-attr-{{ $group['id'] }}" class="{{ $inputCls }}">
                            @foreach ($group['options'] as $option)
                                <option value="{{ $option['name'] }}" @selected(($itemForm['attributes'][$group['id']] ?? '') === $option['name'])>
                                    {{ $option['name'] }}@if ($option['add_price'] > 0) (+{{ gp247_currency_render($option['add_price'], '', '', '', false) }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-3 flex items-center justify-end gap-2">
            <x-gp247::button variant="secondary" wire:click="newItem">{{ gp247_language_render('admin.cancel') }}</x-gp247::button>
            <x-gp247::button wire:click="saveItem" wire:loading.attr="disabled" data-testid="shop-admin-order-item-submit">
                <i class="fas fa-save"></i> {{ gp247_language_render($editingItemId ? 'admin.update' : 'admin.submit') }}
            </x-gp247::button>
        </div>
    </div>
    @endif
</x-gp247::card>
