@if (count($arrVendor))
<div class="store-url">
    @foreach ($arrVendor as $vendorCode => $vendorUrl)
        <a href="{{ $vendorUrl }}"><span class="fa fa-shopping-bag" aria-hidden="true"></span> {{ $vendorCode }}</a><br>
    @endforeach
</div>
@endif
