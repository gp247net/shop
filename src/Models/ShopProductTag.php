<?php
namespace GP247\Shop\Models;

use GP247\Shop\Models\ShopProduct;
use GP247\Core\Models\AdminStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Keyword tag for products (US-CMP-product-tag-schema). A first-class, normalized
 * taxonomy entity — distinct from ShopProduct::product_type (delivery type). Products
 * link to tags many-to-many via shop_product_tag_pivot; `alias` is the business key
 * used for find-or-create on save and for the storefront /tag/<alias> route.
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-product-tag-schema
 * @aidlc-adr shop-admin_product-tag-storage
 */
class ShopProductTag extends Model
{
    use \GP247\Core\Models\ModelTrait;

    public $table = GP247_DB_PREFIX.'shop_product_tag';
    protected $connection = GP247_DB_CONNECTION;

    /**
     * WHY explicit $fillable (never $guarded=[]): the tag name/alias is a user-supplied,
     * self-add input surface. Whitelisting the four owned columns blocks mass-assignment
     * of id/timestamps or any injected key (NFR-SEC-product-tag-input).
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'alias', 'status', 'sort'];

    /**
     * Detach every product link when a tag is deleted so no orphan pivot rows survive
     * (the pivot has no DB foreign key). Deleting a tag only unlinks products — it never
     * deletes the products themselves.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($tag) {
            $tag->products()->detach();
        });
    }

    /**
     * Products carrying this tag (many-to-many through the pivot).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products()
    {
        return $this->belongsToMany(
            ShopProduct::class,
            GP247_DB_PREFIX.'shop_product_tag_pivot',
            'tag_id',
            'product_id'
        );
    }

    /**
     * The store that owns this tag (1-1 ownership).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     *
     * @aidlc-unit compat-foundation
     * @aidlc-story US-CMP-store-1to1-schema
     * @aidlc-adr multi-store_one-to-one-store-ownership
     */
    public function store()
    {
        return $this->belongsTo(AdminStore::class, 'store_id', 'id');
    }

    /**
     * Limit a query to active (status=1) tags.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where($this->getTable().'.status', 1);
    }

    /**
     * Normalize a raw tag name into its canonical alias (URL-safe slug). Shared by the
     * product-save find-or-create path and the tag manager so a self-added "New Arrival"
     * and a managed "new-arrival" resolve to the SAME tag (prevents duplicates — T1/T3).
     *
     * @param string $name Raw tag name as typed by the admin.
     * @return string Canonical alias, or '' when the name has no slug-able characters.
     */
    public static function normalizeAlias(string $name): string
    {
        return gp247_word_limit(gp247_word_format_url(trim($name)), 120);
    }

    /**
     * Storefront URL listing every product carrying this tag.
     *
     * @param string|null $lang Locale override; defaults to the active locale.
     * @return string
     */
    public function getUrl($lang = null)
    {
        return gp247_route_front('shop.tag', ['alias' => $this->alias, 'lang' => $lang ?? app()->getLocale()]);
    }
}
