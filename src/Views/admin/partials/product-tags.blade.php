{{--
    Product keyword-tags sub-panel (shop-admin, US-SADM-product-tags): a single
    comma-separated input bound to form.tags. Applies to EVERY product kind and is
    gated by the `product_tags` config. Existing tags render as clickable chips that
    append into the field (pick-or-add UX, ADR shop-admin_product-tag-input-ux); the
    server parses/normalizes/find-or-creates on save. Distinct from product_type
    (delivery type). Variables: $form, $inputCls.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-product-tags
    @aidlc-adr shop-admin_product-tag-storage, shop-admin_product-tag-input-ux
--}}
@if ($this->productFieldEnabled('product_tags'))
    @php($tagLabelCls = 'mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200')
    @php($tagSuggestions = $this->tagSuggestions())
    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
        <label class="{{ $tagLabelCls }}" for="product-tags-input">{{ gp247_language_render('product.tags') }}</label>
        <input type="text" id="product-tags-input" data-testid="shop-admin-product-tags-input"
            wire:model.live="form.tags" list="product-tags-suggestions"
            placeholder="{{ gp247_language_render('product.tags_placeholder') }}" class="{{ $inputCls }}" />
        <datalist id="product-tags-suggestions">
            @foreach ($tagSuggestions as $tagName)
                <option value="{{ $tagName }}"></option>
            @endforeach
        </datalist>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.tags_help') }}</p>

        @if (! empty($tagSuggestions))
            {{-- WHY: a datalist only autocompletes the whole field; these chips let the
                 admin append an existing tag onto the comma-separated list in one click,
                 avoiding accidental duplicates from re-typing (Alpine, no jQuery — P2). --}}
            <div class="mt-2 flex flex-wrap gap-1.5" x-data>
                @foreach ($tagSuggestions as $tagName)
                    <button type="button" data-testid="shop-admin-product-tags-suggestion"
                        class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs text-gray-600 hover:bg-brand-50 hover:text-brand-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        x-on:click="
                            const parts = ($wire.get('form.tags') || '').split(',').map(s => s.trim()).filter(Boolean);
                            if (! parts.includes(@js($tagName))) { parts.push(@js($tagName)); $wire.set('form.tags', parts.join(', ')); }
                        ">{{ $tagName }}</button>
                @endforeach
            </div>
        @endif
    </div>
@endif
