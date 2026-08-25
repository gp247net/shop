{{--
    Product variants/attributes sub-panel (group F, US-SADM-001) — SINGLE products:
    option rows under an attribute group (name + add_price + slug), with add/remove.
    Variables: $variants, $inputCls, $labelCls.

    Layout: each option is a bordered card with a labelled field per input, so a
    filled row stays self-explanatory (once a value replaces the placeholder, the
    label is the only cue for what "0" / "ab-ok" mean). The group select spans the
    card width (it identifies the row); name + add_price share a responsive 2-col
    grid; slug spans full width for its datalist combobox; the remove control sits
    in a divider footer. Full-width fields are plain blocks (not `col-span`) so the
    markup relies only on safelisted grid/gap utilities (core Tailwind safelist —
    `sm:col-span-*` is not generated for cross-package blades; gp247.md §3a).

    Slug (mod 20260825T135923, US-SADM-product-attribute-slug): an <input list>
    combobox — pick an existing per-group slug from the <datalist> or type a new
    one. Options are resolved server-side per the row's attribute_group_id, so no
    jQuery is needed (ui-tailadmin P2); the value is normalized to kebab-case on save.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-001
    @aidlc-story US-SADM-product-attribute-slug
    @aidlc-adr ADR-006, ADR-007, shop-admin_product-attribute-slug
--}}
<div class="space-y-3">
    @forelse ($variants as $index => $variant)
        <div class="space-y-3 rounded-lg border border-gray-200 p-4 dark:border-gray-700" wire:key="variant-{{ $index }}">
            <div>
                <label class="{{ $labelCls }}">{{ gp247_language_render('product.group') }}</label>
                <select wire:model.live="variants.{{ $index }}.attribute_group_id" class="{{ $inputCls }}">
                    <option value="">--</option>
                    @foreach ($this->attributeGroupOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('product.name') }}</label>
                    <input type="text" wire:model="variants.{{ $index }}.name" placeholder="{{ gp247_language_render('product.name') }}" class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ gp247_language_render('product.price') }} <span class="font-normal text-gray-400">{{ gp247_money_hint() }}</span></label>
                    <input type="number" step="0.01" wire:model="variants.{{ $index }}.add_price" placeholder="0" class="{{ $inputCls }}">
                </div>
            </div>
            <div>
                <label class="{{ $labelCls }}">Slug</label>
                <input type="text" list="slug-opts-{{ $index }}" wire:model="variants.{{ $index }}.slug" placeholder="Slug" class="{{ $inputCls }}" data-testid="shop-admin-product-variant-slug" autocomplete="off">
                <datalist id="slug-opts-{{ $index }}">
                    @foreach ($this->attributeSlugOptions($variant['attribute_group_id'] ?? '') as $slugOption)<option value="{{ $slugOption }}"></option>@endforeach
                </datalist>
            </div>
            <div class="flex items-center justify-end border-t border-gray-200 pt-3 dark:border-gray-700">
                <x-gp247::button size="sm" variant="ghost" wire:click="removeVariant({{ $index }})"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.no_records') }}</p>
    @endforelse

    <x-gp247::button size="sm" variant="secondary" wire:click="addVariant"><i class="fas fa-plus"></i> {{ gp247_language_render('admin.product_attribute_group.list') }}</x-gp247::button>
</div>
