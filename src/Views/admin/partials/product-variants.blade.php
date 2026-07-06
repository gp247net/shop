{{--
    Product variants/attributes sub-panel (group F, US-SADM-001) — SINGLE products:
    option rows under an attribute group (name + add_price), with add/remove.
    Variables: $variants, $inputCls.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-001
    @aidlc-adr ADR-006, ADR-007
--}}
<div class="space-y-3">
    @forelse ($variants as $index => $variant)
        <div class="grid grid-cols-2 gap-2" wire:key="variant-{{ $index }}">
            <select wire:model="variants.{{ $index }}.attribute_group_id" class="{{ $inputCls }}">
                <option value="">--</option>
                @foreach ($this->attributeGroupOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <input type="text" wire:model="variants.{{ $index }}.name" placeholder="{{ gp247_language_render('product.name') }}" class="{{ $inputCls }}">
            <input type="number" step="0.01" wire:model="variants.{{ $index }}.add_price" placeholder="{{ gp247_language_render('product.price') }}" class="{{ $inputCls }}">
            <div class="flex items-center justify-end">
                <x-gp247::button size="sm" variant="ghost" wire:click="removeVariant({{ $index }})"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.core.no_records') }}</p>
    @endforelse

    <x-gp247::button size="sm" variant="secondary" wire:click="addVariant"><i class="fas fa-plus"></i> {{ gp247_language_render('admin.product_attribute_group.list') }}</x-gp247::button>
</div>
