<article class="product wow fadeInRight">
    <div class="product-body">
      <div class="product-figure">
          <a href="{{ $item->getUrl() }}">
          <img src="{{ gp247_file($item->getThumb()) }}" alt="{{ $item->name ?? $item->title ?? '' }}"/>
          </a>
      </div>
      <h5 class="product-title"><a href="{{ $item->getUrl() }}">{{ $item->name ?? $item->title ?? '' }}</a></h5>
    </div>
</article>