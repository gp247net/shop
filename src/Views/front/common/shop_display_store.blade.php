{{--
    Vendor/store link (multi-partner mode only) — Tailwind port of
    vendor/gp247/shop/.../front/common/shop_display_store.blade.php. Called
    by $product->displayVendor() (ShopProduct.php, unchanged) when
    gp247_store_check_multi_partner_installed() is true.

    @aidlc-unit frontend-template-dev
    @aidlc-story US-TPL-009
    @aidlc-adr ADR-014
--}}
<a href="{{ $vendorUrl }}" class="text-xs text-ink-400 hover:text-brand-600">{{ $vendorCode }}</a>
