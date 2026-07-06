<?php

namespace GP247\Shop\Front\Livewire;

use GP247\Front\Livewire\BaseFrontComponent;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopProduct;

/**
 * Livewire component for storefront product filter and search.
 *
 * Renders the product grid with reactive filtering by sort order, price range,
 * brand, and keyword. All filter state is reflected in the URL query string via
 * Livewire #[Url] attributes so filtered URLs are shareable and bookmark-able.
 *
 * SEO contract (storefront__seo_integration.md): the outer page frame
 * (breadcrumbs, sub-categories, canonical meta) is server-rendered by the
 * controller; only this product-grid region is Livewire-reactive. The initial
 * SSR response includes product HTML for crawlers even before JS runs.
 *
 * Extends BaseFrontComponent (ADR-011): the view is resolved through the
 * active template first, falling back to this package's default view, so a
 * Template Developer can override the HTML without touching this class.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-005
 * @aidlc-adr ADR-011
 */
class ProductFilter extends BaseFrontComponent
{
    use WithPagination;

    /**
     * Category ID pinned by the controller page context.
     * Set via mount(); not user-changeable via URL (page-level context).
     *
     * @var string|null
     */
    public ?string $initialCategory = null;

    /**
     * Sort key reflected in the URL as `filter_sort`.
     * Whitelisted against ALLOWED_SORTS before use.
     *
     * @var string
     */
    #[Url(as: 'filter_sort', keep: false)]
    public string $sort = '';

    /**
     * Price range in "min__max" format (e.g. "100__500").
     * Reflected in URL as `price`.
     *
     * @var string
     */
    #[Url(as: 'price', keep: false)]
    public string $price = '';

    /**
     * Comma-separated brand aliases (e.g. "apple,samsung").
     * Reflected in URL as `brand`.
     *
     * @var string
     */
    #[Url(as: 'brand', keep: false)]
    public string $brand = '';

    /**
     * Free-text search keyword.
     * Reflected in URL as `keyword`.
     *
     * @var string
     */
    #[Url(as: 'keyword', keep: false)]
    public string $keyword = '';

    /**
     * Minimum price input (transient; combined with $priceMax on applyPrice()).
     *
     * @var string
     */
    public string $priceMin = '';

    /**
     * Maximum price input (transient; combined with $priceMin on applyPrice()).
     *
     * @var string
     */
    public string $priceMax = '';

    /** @var array<string, array{string, string}> Whitelisted sort values. */
    private const ALLOWED_SORTS = [
        'price_asc'  => ['price', 'asc'],
        'price_desc' => ['price', 'desc'],
        'sort_asc'   => ['sort', 'asc'],
        'sort_desc'  => ['sort', 'desc'],
        'id_asc'     => ['id', 'asc'],
        'id_desc'    => ['id', 'desc'],
    ];

    /**
     * Mount the component with optional page-context overrides.
     *
     * @param string|null $initialCategory Category ID pinned by the controller.
     * @param string      $initialKeyword  Pre-filled keyword (e.g. search page).
     * @return void
     */
    public function mount(?string $initialCategory = null, string $initialKeyword = ''): void
    {
        $this->initialCategory = $initialCategory;

        // Pre-fill keyword only when URL carries no existing value
        if ($this->keyword === '' && $initialKeyword !== '') {
            $this->keyword = $initialKeyword;
        }

        // Split persisted price string back into transient min/max inputs, converting
        // the stored base-currency value back to the active display currency so the
        // shopper sees the number they originally typed (mirrors applyPrice()'s conversion).
        if ($this->price !== '' && str_contains($this->price, '__')) {
            $rate = (float) gp247_currency_rate();
            $rate = $rate > 0 ? $rate : 1.0;

            [$rawMin, $rawMax] = explode('__', $this->price, 2);
            $this->priceMin = (string) (int) round(((int) $rawMin) * $rate);
            $this->priceMax = (string) (int) round(((int) $rawMax) * $rate);
        }
    }

    /**
     * Reset pagination when sort changes.
     *
     * @return void
     */
    public function updatedSort(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when brand changes.
     *
     * @return void
     */
    public function updatedBrand(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when keyword changes.
     *
     * @return void
     */
    public function updatedKeyword(): void
    {
        $this->resetPage();
    }

    /**
     * Toggle a brand alias in/out of the CSV brand filter.
     *
     * @param string $alias Brand alias to toggle.
     * @return void
     */
    public function toggleBrand(string $alias): void
    {
        $cleanAlias = gp247_clean(data: $alias, hight: true);
        if ($cleanAlias === '') {
            return;
        }

        $aliases = $this->brand !== '' ? explode(',', $this->brand) : [];
        $aliases = array_filter($aliases, fn(string $a): bool => $a !== '');

        if (in_array($cleanAlias, $aliases, true)) {
            $aliases = array_values(array_filter($aliases, fn(string $a): bool => $a !== $cleanAlias));
        } else {
            $aliases[] = $cleanAlias;
        }

        $this->brand = implode(',', $aliases);
        $this->resetPage();
    }

    /**
     * Combine priceMin/priceMax into the URL-reflected $price string.
     * Clears the price filter if both inputs are empty.
     *
     * The inputs are typed by the shopper in the storefront's currently
     * selected display currency (e.g. VND), but `ShopProduct::price` is
     * always stored in the store's base currency and queried raw
     * (whereBetween, see ShopProduct::getData()). Without converting back
     * by the active exchange rate here, "500 - 700" typed while browsing in
     * VND would be compared against base-currency prices and silently
     * return the wrong products.
     *
     * @return void
     */
    public function applyPrice(): void
    {
        $rate = (float) gp247_currency_rate();
        $rate = $rate > 0 ? $rate : 1.0;

        $min = (int) round(((int) gp247_clean(data: $this->priceMin, hight: true)) / $rate);
        $max = (int) round(((int) gp247_clean(data: $this->priceMax, hight: true)) / $rate);

        if ($min <= 0 && $max <= 0) {
            $this->price = '';
        } elseif ($max > 0 && $min > $max) {
            // Swap if min > max to avoid empty results
            $this->price = $max . '__' . $min;
        } else {
            $this->price = $min . '__' . $max;
        }

        $this->resetPage();
    }

    /**
     * Clear all active filters and reset pagination.
     *
     * @return void
     */
    public function clearFilters(): void
    {
        $this->sort     = '';
        $this->price    = '';
        $this->priceMin = '';
        $this->priceMax = '';
        $this->brand    = '';
        $this->keyword  = '';
        $this->resetPage();
    }

    /**
     * Build the ShopProduct query with all active filters applied.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function buildProductQuery(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $builder = (new ShopProduct)
            ->setLimit((int) gp247_config('product_list'))
            ->setPaginate();

        // Sort — whitelist enforced
        $cleanSort = gp247_clean(data: $this->sort, hight: true);
        if (isset(self::ALLOWED_SORTS[$cleanSort])) {
            $builder->setSort(self::ALLOWED_SORTS[$cleanSort]);
        }

        // Price range ("min__max")
        $cleanPrice = gp247_clean(data: $this->price, hight: true);
        if ($cleanPrice !== '') {
            $builder->setRangePrice($cleanPrice);
        }

        // Brand filter (CSV aliases → resolved IDs)
        $cleanBrand = gp247_clean(data: $this->brand, hight: true);
        if ($cleanBrand !== '') {
            $aliases  = array_filter(explode(',', $cleanBrand), fn(string $a): bool => $a !== '');
            $brandIds = ShopBrand::whereIn('alias', $aliases)->pluck('id')->toArray();
            if (!empty($brandIds)) {
                $builder->getProductToBrand($brandIds);
            }
        }

        // Category: pinned by page context (includes sub-categories 2 levels deep)
        if ($this->initialCategory !== null) {
            $arrCate = (new ShopCategory)->getListSub($this->initialCategory);
            $builder->getProductToCategory($arrCate);
        }

        // Keyword search
        $cleanKeyword = gp247_clean(data: $this->keyword, hight: true);
        if ($cleanKeyword !== '') {
            $builder->setKeyword($cleanKeyword);
        }

        return $builder->getData();
    }

    /**
     * View key resolved through the active template (ADR-011).
     *
     * @return string
     */
    protected function templateViewKey(): string
    {
        return 'livewire.shop_product-filter';
    }

    /**
     * Default package view namespace, used when the active template has no override.
     *
     * @return string
     */
    protected function defaultViewNamespace(): string
    {
        return 'gp247-shop-front';
    }

    /**
     * Data passed to the resolved product-filter view.
     *
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $products = $this->buildProductQuery();
        $brands   = ShopBrand::orderBy('name')->get();

        $selectedBrandAliases = $this->brand !== ''
            ? array_filter(explode(',', $this->brand), fn(string $a): bool => $a !== '')
            : [];

        return [
            'products'             => $products,
            'brands'               => $brands,
            'selectedBrandAliases' => array_values($selectedBrandAliases),
        ];
    }
}
