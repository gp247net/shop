{{--
    Product attribute option list — Tailwind port of
    vendor/gp247/shop/.../front/common/shop_render_attribute.blade.php. Called
    by $product->renderAttributeDetails() (ShopProduct.php, unchanged) — used
    by screen/shop_product_detail.blade.php.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
@if (!empty($details) && count($details))
<div class="mt-4 space-y-3">
    @foreach ($details as $groupId => $detailsGroup)
    <div>
        <p class="label mb-2">{!! $groups[$groupId] ?? 'Not found' !!}</p>
        <div class="flex flex-wrap gap-2" role="radiogroup">
            @foreach ($detailsGroup as $k => $detail)
            @php
                $valueOption = $detail->name.'__'.$detail->add_price;
            @endphp
            <label class="chip cursor-pointer has-[:checked]:bg-brand-600 has-[:checked]:text-white">
                <input class="sr-only" type="radio" name="form_attr[{{ $groupId }}]" value="{{ $valueOption }}" {{ ($k == 0) ? 'checked' : '' }}>
                {!! gp247_render_option_price($valueOption) !!}
            </label>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endif
