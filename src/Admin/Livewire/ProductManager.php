<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\HasCustomFields;
use GP247\Core\AdminShell\Infrastructure\HasMultilingualDescriptions;
use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\Models\AdminLanguage;
use GP247\Shop\Admin\Livewire\Concerns\HasProductComposition;
use GP247\Shop\Admin\Livewire\Concerns\HasProductImages;
use GP247\Shop\Admin\Livewire\Concerns\HasProductPricing;
use GP247\Shop\Admin\Livewire\Concerns\HasProductVariants;
use GP247\Shop\Admin\Models\AdminCategory;
use GP247\Shop\Admin\Models\AdminProduct;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductDescription;
use GP247\Shop\Models\ShopSupplier;
use GP247\Shop\Models\ShopTax;

/**
 * Product manager (shop-admin Unit, group F, US-SADM-001) — the most complex
 * shop screen, on the core ResourcePanel base + the reusable multilingual (C0)
 * and custom-field (D0) traits. The base route is a store-scoped, filterable
 * LIST; the edit/{id} & create routes are a tabbed FORM (general / descriptions /
 * custom fields …). Mirrors the legacy AdminProductController (rule ui-tailadmin
 * P1): the brownfield product_sku_unique / product_alias_unique validators, the
 * config-driven field set, multilingual descriptions and pivots (category/store)
 * stay identical to legacy. Domain/schema unchanged. Gated by `admin_product`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-001
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class ProductManager extends ResourcePanel
{
    use HasMultilingualDescriptions;
    use HasCustomFields;
    use HasProductImages;
    use HasProductVariants;
    use HasProductComposition;
    use HasProductPricing;

    protected ?string $permission = 'admin_product';

    /** Scalar product columns edited on this screen. */
    private const STRING_FIELDS = ['sku', 'alias', 'image', 'brand_id', 'supplier_id', 'tax_id', 'tag', 'weight_class', 'length_class'];

    /** Numeric product columns. */
    private const NUMERIC_FIELDS = ['price', 'cost', 'stock', 'minimum', 'weight', 'length', 'width', 'height'];

    /**
     * Numeric fields that represent a product *quantity* (as opposed to money or
     * physical dimensions) — gated by `product_qty_decimal` (modification
     * 20260705T093328, ADR-016).
     */
    private const QTY_FIELDS = ['stock', 'minimum'];

    /** @var string Category filter (list). */
    public string $filterCategory = '';

    /**
     * Current admin store id (falls back to the root store).
     *
     * @return int|string
     */
    private function storeId()
    {
        return session('adminStoreId', defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : 1);
    }

    // --- C0 / D0 contracts ---------------------------------------------------

    /**
     * @return array<int, string>
     */
    protected function multilingualFields(): array
    {
        return ['name', 'keyword', 'description', 'content'];
    }

    /**
     * @return array<int, string>
     */
    protected function richDescriptionFields(): array
    {
        return ['content'];
    }

    /**
     * @return class-string
     */
    protected function descriptionModelClass(): string
    {
        return ShopProductDescription::class;
    }

    /**
     * @return string
     */
    protected function descriptionForeignKey(): string
    {
        return 'product_id';
    }

    /**
     * @return string
     */
    protected function customFieldType(): string
    {
        return 'shop_product';
    }

    // --- ResourcePanel contract ---------------------------------------------

    /**
     * Store-scoped product query with the optional category filter.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $storeId = $this->storeId();
        $query = ShopProduct::query()
            ->whereHas('stores', static fn ($q) => $q->where('store_id', $storeId));

        if ($this->filterCategory !== '') {
            $category = $this->filterCategory;
            $query->whereHas('categories', static fn ($q) => $q->where('category_id', $category));
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['sku', 'alias'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['sku', 'price', 'sort', 'created_at'];
    }

    /**
     * @return array<int, string>
     */
    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::product-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.product.list');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_product.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        $defaults = [
            'kind' => defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0,
            'status' => 1,
            'approve' => 1,
            'sort' => 0,
            'minimum' => 0,
            'date_available' => '',
            'category' => [],
            'store' => [$this->storeId()],
        ];
        foreach (self::STRING_FIELDS as $field) {
            $defaults[$field] = '';
        }
        foreach (self::NUMERIC_FIELDS as $field) {
            $defaults[$field] = 0;
        }

        return array_merge($defaults, $this->pricingDefaults());
    }

    /**
     * Reset descriptions + custom-field state alongside the base form.
     *
     * @return void
     */
    public function resetForm(): void
    {
        parent::resetForm();
        $this->initDescriptions();
        $this->initCustomFields();
        $this->resetGallery();
        $this->resetVariants();
        $this->resetComposition();
    }

    /**
     * @param ShopProduct $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        $this->fillDescriptions($model->descriptions);
        $this->loadCustomFields($model->id);
        $this->fillGallery($model);
        $this->fillVariants($model);
        $this->fillComposition($model);

        $form = [
            'kind' => (int) $model->kind,
            'status' => (int) $model->status,
            'approve' => (int) $model->approve,
            'sort' => (int) $model->sort,
            'minimum' => (float) $model->minimum,
            'date_available' => $model->date_available ? (string) $model->date_available : '',
            'category' => $model->categories()->pluck('category_id')->map(static fn ($id) => (string) $id)->all(),
            'store' => $model->stores()->pluck('store_id')->map(static fn ($id) => (string) $id)->all(),
        ];
        foreach (self::STRING_FIELDS as $field) {
            $form[$field] = (string) ($model->{$field} ?? '');
        }
        foreach (self::NUMERIC_FIELDS as $field) {
            $form[$field] = (float) ($model->{$field} ?? 0);
        }

        return array_merge($form, $this->pricingFormFrom($model));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $skuUnique = $this->editingId !== null ? 'product_sku_unique:' . $this->editingId : 'product_sku_unique';
        $aliasUnique = $this->editingId !== null ? 'product_alias_unique:' . $this->editingId : 'product_alias_unique';

        $rules = [
            'form.sku' => 'required|string|max:50|' . $skuUnique,
            'form.alias' => 'required|string|max:120|' . $aliasUnique,
            'form.category' => 'required|array|min:1',
            'form.sort' => 'nullable|numeric|min:0',
            'form.minimum' => 'nullable|numeric|min:0',
            'desc.*.name' => 'required|string|max:200',
            'desc.*.keyword' => 'nullable|string|max:200',
            'desc.*.description' => 'nullable|string|max:500',
        ];

        // WHY: content is required for single/bundle but not for a group (parity).
        // When structure type is disabled, effective kind is always SINGLE — require content.
        $useStructureType = self::structureTypeEnabled();
        $effectiveKind = $useStructureType ? (int) ($this->form['kind'] ?? 0) : (defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0);
        if ($effectiveKind !== (defined('GP247_PRODUCT_GROUP') ? GP247_PRODUCT_GROUP : 2)) {
            $rules['desc.*.content'] = 'required|string';
        }

        return array_merge($rules, $this->configRules(), $this->customFieldRules(), $this->compositionRules());
    }

    /**
     * Config-driven field rules, mirroring AdminProductController::validateAttribute:
     * numeric fields are always numeric|nullable; a gated field becomes required
     * when its product_* config (and *_required flag) is on.
     *
     * @return array<string, mixed>
     */
    private function configRules(): array
    {
        $rules = [];
        foreach (self::NUMERIC_FIELDS as $field) {
            // WHY: stock/minimum are quantities (gated by product_qty_decimal); the
            // rest are money/dimensions and always stay numeric (ADR-016).
            $rules['form.' . $field] = in_array($field, self::QTY_FIELDS, true)
                ? 'nullable|' . gp247_qty_rule('0', '0')
                : 'nullable|numeric';
        }

        // config key => form field (required-by-config when both flags are truthy).
        $gated = [
            'product_price' => 'price', 'product_cost' => 'cost', 'product_stock' => 'stock',
            'product_brand' => 'brand_id', 'product_supplier' => 'supplier_id', 'product_tag' => 'tag',
        ];
        if (function_exists('gp247_config_admin')) {
            foreach ($gated as $cfg => $field) {
                if (gp247_config_admin($cfg) && gp247_config_admin($cfg . '_required')) {
                    $existing = $rules['form.' . $field] ?? 'nullable';
                    $rules['form.' . $field] = 'required|' . ltrim(str_replace('nullable', '', $existing), '|');
                }
            }
        }

        return $rules;
    }

    /**
     * Auto-derive the alias from the first language's name when empty, then run the
     * standard validate/persist/redirect.
     *
     * @return void
     */
    public function save(): void
    {
        if (empty($this->form['alias'])) {
            $firstLang = $this->firstDescriptionLanguage();
            $this->form['alias'] = $firstLang !== null ? ($this->desc[$firstLang]['name'] ?? '') : '';
        }
        $this->form['alias'] = gp247_word_limit(gp247_word_format_url((string) $this->form['alias']), 100);

        parent::save();
    }

    /**
     * Persist the product (mirroring AdminProductController): main row →
     * descriptions (C0) → custom fields (D0) → category/store pivots. Wrapped in a
     * transaction; subsequent groups (images/variants/composition/promotion) extend
     * this.
     *
     * @param array<string, mixed> $data Sanitised form.
     * @return void
     */
    protected function persist(array $data): void
    {
        $connection = defined('GP247_DB_CONNECTION') ? GP247_DB_CONNECTION : null;
        \DB::connection($connection)->transaction(function () use ($data): void {
            $attributes = $this->productAttributes($data);

            if ($this->editingId !== null) {
                $product = ShopProduct::findOrFail($this->editingId);
                $product->update($attributes);
            } else {
                $product = AdminProduct::createProductAdmin($attributes);
            }

            $this->saveDescriptions($product->id);

            if (function_exists('gp247_custom_field_update')) {
                gp247_custom_field_update($this->customFieldsPayload(), (string) $product->id, 'shop_product');
            }

            $product->categories()->sync(array_filter((array) ($data['category'] ?? [])));
            $product->stores()->sync($this->resolveStores($data));

            $this->persistImages($product);
            $this->persistVariants($product);
            $this->persistComposition($product);
            $this->persistPricing($product, $data);

            if (function_exists('gp247_cache_clear')) {
                gp247_cache_clear('cache_product');
            }
        });
    }

    /**
     * Map the sanitised form to the shop_product column set.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function productAttributes(array $data): array
    {
        $attributes = [];
        foreach (self::STRING_FIELDS as $field) {
            $attributes[$field] = (string) ($data[$field] ?? '');
        }
        foreach (self::NUMERIC_FIELDS as $field) {
            $attributes[$field] = (float) ($data[$field] ?? 0);
        }
        // WHY: when "Use STRUCTURE TYPE" is disabled, ignore submitted kind and always persist SINGLE.
        $attributes['kind'] = self::structureTypeEnabled()
            ? (int) ($data['kind'] ?? 0)
            : (defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0);
        $attributes['sort'] = (int) ($data['sort'] ?? 0);
        $attributes['status'] = empty($data['status']) ? 0 : 1;
        $attributes['approve'] = empty($data['approve']) ? 0 : 1;
        $attributes['date_available'] = !empty($data['date_available']) ? $data['date_available'] : null;

        return $attributes;
    }

    /**
     * The store ids to sync (defaults to the current admin store when none chosen).
     *
     * @param array<string, mixed> $data
     * @return array<int, int|string>
     */
    private function resolveStores(array $data): array
    {
        $stores = array_filter((array) ($data['store'] ?? []));

        return $stores !== [] ? $stores : [$this->storeId()];
    }

    /**
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        // ShopProduct::boot() cascades descriptions/images/attributes/pivots/etc.
        $model = $this->baseQuery()->find($id);
        if ($model !== null) {
            $model->delete();
            if (function_exists('gp247_cache_clear')) {
                gp247_cache_clear('cache_product');
            }
        }
    }

    // --- Config helpers -----------------------------------------------------

    /**
     * Return true when the "Use STRUCTURE TYPE" Shop Config is on (or not yet seeded).
     * Treats null (config absent) as enabled so default behaviour is preserved before
     * first install/seed; treats '0'/0 as explicitly disabled.
     *
     * @return bool
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-001
     */
    private static function structureTypeEnabled(): bool
    {
        if (!function_exists('gp247_config')) {
            return true;
        }
        $value = gp247_config('product_kind');
        // WHY: null means the key hasn't been seeded yet — treat as enabled (default=1 in seeder).
        return $value !== '0' && $value !== 0;
    }

    // --- View option helpers ------------------------------------------------

    /**
     * @return array<int|string, string>
     */
    public function categoryOptions(): array
    {
        return (array) (new AdminCategory())->getTreeCategoriesAdmin();
    }

    /**
     * @return array<int|string, string>
     */
    public function brandOptions(): array
    {
        return ShopBrand::pluck('name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function supplierOptions(): array
    {
        return ShopSupplier::pluck('name', 'id')->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function taxOptions(): array
    {
        return ShopTax::pluck('name', 'id')->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function languages(): array
    {
        return AdminLanguage::getListActive()->all();
    }

    /**
     * Active custom-field definitions (exposed to the view).
     *
     * @return iterable<mixed>
     */
    public function customFieldList(): iterable
    {
        return $this->customFieldDefs();
    }
}
