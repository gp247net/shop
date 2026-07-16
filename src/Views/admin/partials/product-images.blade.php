{{--
    Product gallery sub-panel (group F, US-SADM-001): the sub-images list, each a
    media picker, with add/remove. The main image lives in the General tab.
    Variables: $gallery, $inputCls.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-001
    @aidlc-adr ADR-006, ADR-007
--}}
<div class="space-y-3">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('product.sub_image') }}</p>

    @forelse ($gallery as $index => $image)
        <div class="flex items-end gap-2" wire:key="gallery-{{ $index }}">
            <div class="flex-1">
                <x-gp247::media-input name="gallery_{{ $index }}" type="product"
                    wire:model="gallery.{{ $index }}" :value="$image" />
            </div>
            <x-gp247::button size="sm" variant="ghost" wire:click="removeGalleryImage({{ $index }})"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.no_records') }}</p>
    @endforelse

    <x-gp247::button size="sm" variant="secondary" wire:click="addGalleryImage"><i class="fas fa-plus"></i> {{ gp247_language_render('product.sub_image') }}</x-gp247::button>
</div>
