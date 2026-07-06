{{--
    Product pricing/extras sub-panel (group F, US-SADM-001): promotion price
    (single/bundle), physical dimensions/weight, product tag and the download path
    (when tag = download). Variables: $form, $inputCls.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-001
    @aidlc-adr ADR-006, ADR-007
--}}
@php($labelCls = 'mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200')
<div class="space-y-4">
    {{-- Promotion --}}
    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
        <x-gp247::checkbox :label="gp247_language_render('product.promotion')" wire:model.live="form.promotion_use" value="1" />
        @if (! empty($form['promotion_use']))
            <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                <x-gp247::input type="number" step="0.01" :label="gp247_language_render('product.price_promotion')" name="price_promotion" wire:model="form.price_promotion" />
                <div></div>
                <x-gp247::input type="date" :label="gp247_language_render('product.date_start')" name="price_promotion_start" wire:model="form.price_promotion_start" />
                <x-gp247::input type="date" :label="gp247_language_render('product.date_end')" name="price_promotion_end" wire:model="form.price_promotion_end" />
            </div>
        @endif
    </div>

    {{-- Dimensions / weight --}}
    <div class="grid grid-cols-2 gap-3">
        <x-gp247::input :label="gp247_language_render('product.weight_class')" name="weight_class" wire:model="form.weight_class" />
        <x-gp247::input type="number" step="0.01" :label="gp247_language_render('product.weight')" name="weight" wire:model="form.weight" />
        <x-gp247::input :label="gp247_language_render('product.length_class')" name="length_class" wire:model="form.length_class" />
        <x-gp247::input type="number" step="0.01" :label="gp247_language_render('product.length')" name="length" wire:model="form.length" />
        <x-gp247::input type="number" step="0.01" :label="gp247_language_render('product.width')" name="width" wire:model="form.width" />
        <x-gp247::input type="number" step="0.01" :label="gp247_language_render('product.height')" name="height" wire:model="form.height" />
        <x-gp247::input type="number" :label="gp247_language_render('product.minimum')" name="minimum" wire:model="form.minimum" />
        <x-gp247::input type="date" :label="gp247_language_render('product.date_available')" name="date_available" wire:model="form.date_available" />
    </div>

    {{-- Tag + download --}}
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <div>
            <label class="{{ $labelCls }}">{{ gp247_language_render('product.tag') }}</label>
            <select wire:model.live="form.tag" class="{{ $inputCls }}">
                <option value="">--</option>
                <option value="physical">{{ gp247_language_render('product.tag_physical') }}</option>
                <option value="download">{{ gp247_language_render('product.tag_download') }}</option>
            </select>
        </div>
        @if (($form['tag'] ?? '') === 'download')
            <x-gp247::input :label="gp247_language_render('product.download_path')" name="download_path" wire:model="form.download_path" />
        @endif
    </div>
</div>
