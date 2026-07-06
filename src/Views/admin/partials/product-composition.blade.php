{{--
    Product composition sub-panel (group F, US-SADM-001): for BUILD (bundle) show
    components + quantity; for GROUP show members. Components are added via a
    TailAdmin product search (no Select2/jQuery, P2). Variables: $form (kind),
    $buildItems, $groupItems, $compositionSearch, $inputCls.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-001
    @aidlc-adr ADR-006, ADR-007
--}}
@php($kind = (int) ($form['kind'] ?? 0))
@php($isBuild = $kind === (defined('GP247_PRODUCT_BUILD') ? GP247_PRODUCT_BUILD : 1))
@php($isGroup = $kind === (defined('GP247_PRODUCT_GROUP') ? GP247_PRODUCT_GROUP : 2))

@if (! $isBuild && ! $isGroup)
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.single') }}</p>
@else
    <div class="space-y-3">
        <div class="relative">
            <input type="search" wire:model.live.debounce.300ms="compositionSearch"
                placeholder="{{ gp247_language_render('product.sku') }} / {{ gp247_language_render('admin.core.search') }}" class="{{ $inputCls }}">
            @php($results = $this->compositionResults())
            @if (is_countable($results) && count($results))
                <div class="mt-1 rounded-lg border border-gray-200 dark:border-gray-700">
                    @foreach ($results as $p)
                        <button type="button" wire:click="{{ $isBuild ? 'addBuildItem' : 'addGroupItem' }}('{{ $p->id }}')"
                            class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span class="font-medium">{{ $p->sku }}</span> — {{ $p->alias }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($isBuild)
            @forelse ($buildItems as $index => $item)
                <div class="flex items-center gap-2" wire:key="build-{{ $index }}">
                    <span class="flex-1 text-sm text-gray-700 dark:text-gray-200">{{ $item['product_id'] }}</span>
                    <input type="number" step="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}" min="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}" wire:model="buildItems.{{ $index }}.quantity" class="w-16 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <x-gp247::button size="sm" variant="ghost" wire:click="removeBuildItem({{ $index }})"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.core.no_records') }}</p>
            @endforelse
            @error('buildItems')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @else
            @forelse ($groupItems as $index => $item)
                <div class="flex items-center gap-2" wire:key="group-{{ $index }}">
                    <span class="flex-1 text-sm text-gray-700 dark:text-gray-200">{{ $item['product_id'] }}</span>
                    <x-gp247::button size="sm" variant="ghost" wire:click="removeGroupItem({{ $index }})"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.core.no_records') }}</p>
            @endforelse
            @error('groupItems')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @endif
    </div>
@endif
