<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\HasCustomFields;
use GP247\Core\AdminShell\Infrastructure\HasMultilingualDescriptions;
use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\Models\AdminLanguage;
use GP247\Shop\Admin\Livewire\Concerns\HasProductComposition;
use GP247\Shop\Admin\Livewire\Concerns\HasProductImages;
use GP247\Shop\Admin\Livewire\Concerns\HasProductPricing;
use GP247\Shop\Admin\Livewire\Concerns\HasProductTags;
use GP247\Shop\Admin\Livewire\Concerns\HasProductVariants;
use GP247\Shop\Admin\Models\AdminCategory;
use GP247\Shop\Admin\Models\AdminProduct;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductDescription;
use GP247\Shop\Models\ShopSupplier;
use GP247\Shop\Models\ShopTax;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

/**
 * Product manager (shop-admin Unit, group F, US-SADM-001) — the most complex
 * shop screen, on the core ResourcePanel base + the reusable multilingual (C0)
 * and custom-field (D0) traits. The base route is a store-scoped, filterable
 * LIST; the edit/{id} & create routes are a tabbed FORM (general / descriptions /
 * custom fields …). Mirrors the legacy AdminProductController (rule ui-tailadmin
 * P1): the brownfield product_sku_unique / product_alias_unique validators, the
 * config-driven field set, multilingual descriptions, a category pivot and a
 * scalar store_id owner (1-1)
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
    use HasProductTags;

    protected ?string $permission = 'admin_product';

    /**
     * Keep the list panel (page/keyword/sort) and the just-saved product on
     * screen when editing/saving, instead of remounting via route navigation.
     *
     * @var bool
     * @aidlc-story US-AUI-two-panel-state-preservation
     * @aidlc-adr ADR-admin-shell-rbac-two-panel-state-preservation
     */
    protected bool $keepStateOnSave = true;

    /** Scalar product columns edited on this screen. */
    private const STRING_FIELDS = ['sku', 'alias', 'image', 'brand_id', 'supplier_id', 'tax_id', 'product_type', 'weight_class', 'length_class'];

    /** Numeric product columns. */
    private const NUMERIC_FIELDS = ['price', 'cost', 'stock', 'minimum', 'weight', 'length', 'width', 'height'];

    /**
     * Numeric fields that represent a product *quantity* (as opposed to money or
     * physical dimensions) — gated by `product_qty_decimal` (modification
     * 20260705T093328, ADR-016).
     */
    private const QTY_FIELDS = ['stock', 'minimum'];

    /**
     * Config-gated product fields: `product_config_attribute` key => form field.
     * Single source of truth for the render/validate/persist parity — a field whose
     * gate is turned off in Shop Config must be neither rendered, validated, nor
     * persisted (modification 20260826T093146, ADR shop-admin_product-field-config-scope).
     */
    private const GATED_FIELDS = [
        'product_price' => 'price', 'product_cost' => 'cost', 'product_stock' => 'stock',
        'product_brand' => 'brand_id', 'product_supplier' => 'supplier_id', 'product_type' => 'product_type',
    ];

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
        // WHY: use the model's prefixed table name so the `type` key matches the
        // read/display path (ModelTrait::getCustomFields keys on getTable()); a bare
        // 'shop_product' literal diverges from the prefixed key and never round-trips.
        return (new ShopProduct)->getTable();
    }

    // --- ResourcePanel contract ---------------------------------------------

    /**
     * Store-scoped product query with the optional category filter.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        // WHY: 1-1 ownership — a product belongs to a single store via store_id.
        $storeId = $this->storeId();
        $query = ShopProduct::query()
            ->with(['store.descriptions'])
            ->where('store_id', $storeId);

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
        ];
        foreach (self::STRING_FIELDS as $field) {
            $defaults[$field] = '';
        }
        foreach (self::NUMERIC_FIELDS as $field) {
            $defaults[$field] = 0;
        }

        return array_merge($defaults, $this->pricingDefaults(), $this->tagsDefaults());
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
        ];
        foreach (self::STRING_FIELDS as $field) {
            $form[$field] = (string) ($model->{$field} ?? '');
        }
        foreach (self::NUMERIC_FIELDS as $field) {
            $form[$field] = (float) ($model->{$field} ?? 0);
        }

        return array_merge($form, $this->pricingFormFrom($model), $this->tagsFormFrom($model));
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

        // WHY: content (rich description) is optional for every product kind — the
        // gp247_shop_product_description.content column is nullable, and many simple
        // products need no long description (US-SADM-product-content-optional). Empty
        // content persists as '' via HasMultilingualDescriptions, valid for a nullable
        // column. Kept as a typed rule so a non-string payload is still rejected.
        $rules['desc.*.content'] = 'nullable|string';

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
            // WHY: a gated field turned off in Shop Config is out of the form's scope
            // — not rendered, so it must not be validated either (else a stale DB value
            // like a negative backorder stock trips min:0 and silently blocks the save).
            if ($this->gatedFieldDisabled($field)) {
                continue;
            }
            // WHY: stock/minimum are quantities (gated by product_qty_decimal); the
            // rest are money/dimensions and always stay numeric (ADR-016).
            $rules['form.' . $field] = in_array($field, self::QTY_FIELDS, true)
                ? 'nullable|' . gp247_qty_rule('0', '0')
                : 'nullable|numeric';
        }

        // A still-enabled gated field becomes required when its *_required flag is on.
        if (function_exists('gp247_config_admin')) {
            foreach (self::GATED_FIELDS as $cfg => $field) {
                // WHY: keep parity with the render/persist gate — a field hidden by
                // productFieldEnabled() must never get any rule, not even 'required'.
                if ($this->gatedFieldDisabled($field)) {
                    continue;
                }
                if (gp247_config_admin($cfg) && gp247_config_admin($cfg . '_required')) {
                    $existing = $rules['form.' . $field] ?? 'nullable';
                    $rules['form.' . $field] = 'required|' . ltrim(str_replace('nullable', '', $existing), '|');
                }
            }
        }

        return $rules;
    }

    /**
     * Localized attribute labels for the validator, keyed by the dotted rule path.
     * Reuses the existing product.* language strings (the same keys the form labels
     * render) so validation errors read the field's real name — e.g. "Mã SKU" — and
     * never leak the raw Livewire state path ("form.sku").
     *
     * @return array<string, string>
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-001
     */
    private function attributeLabels(): array
    {
        return [
            'form.sku' => $this->label('product.sku'),
            'form.alias' => $this->label('product.alias'),
            'form.category' => $this->label('product.category'),
            'form.price' => $this->label('product.price'),
            'form.cost' => $this->label('product.cost'),
            'form.stock' => $this->label('product.stock'),
            'form.brand_id' => $this->label('product.brand'),
            'form.supplier_id' => $this->label('product.supplier'),
            'form.product_type' => $this->label('product.tag'),
            'form.sort' => $this->label('product.sort'),
            'form.minimum' => $this->label('product.minimum'),
            'desc.*.name' => $this->label('product.name'),
            'desc.*.keyword' => $this->label('product.keyword'),
            'desc.*.description' => $this->label('product.description'),
            'desc.*.content' => $this->label('product.content'),
        ];
    }

    /**
     * Render a product field label as PLAIN TEXT for use as a validator attribute.
     *
     * WHY: some language strings embed presentational markup for the form label —
     * e.g. product.alias carries a trailing "SEO" icon span — which would leak as
     * raw HTML into the validation message. Strip tags/entities so the message reads
     * the clean field name only. The keys themselves are unchanged (existing i18n).
     *
     * @param string $key Language key (e.g. 'product.alias').
     * @return string Tag-free, trimmed label.
     */
    private function label(string $key): string
    {
        $rendered = (string) gp247_language_render($key);

        return trim(strip_tags(html_entity_decode($rendered, ENT_QUOTES)));
    }

    /**
     * Friendly attribute names for every rule (Livewire hook). Fixes messages of
     * rules we do not override in messages() (max/unique/numeric/…) so they show the
     * localized field name instead of the raw "form.*" path.
     *
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return $this->attributeLabels();
    }

    /**
     * Localized validator messages, built from the shared validation.* language keys
     * exactly like gp247_customer_data_*_mapping(). Every rule used by rules() is
     * routed through gp247_language_render so it reads the DB translation (falling
     * back to the framework message) — otherwise non-required rules would bypass the
     * DB and always render in English. Rule-specific placeholders (:min/:max) are left
     * for the framework's replacers to fill after :attribute is substituted here.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        // Rules referenced by rules()/configRules() that carry a user-facing message.
        $rules = ['required', 'min', 'max', 'numeric', 'array', 'string'];
        $messages = [];
        foreach ($this->attributeLabels() as $field => $label) {
            foreach ($rules as $rule) {
                $messages[$field . '.' . $rule] = gp247_language_render('validation.' . $rule, ['attribute' => $label]);
            }
        }

        return $messages;
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
     * descriptions (C0) → custom fields (D0) → category pivot. Wrapped in a
     * transaction; subsequent groups (images/variants/composition/promotion) extend
     * this.
     *
     * @param array<string, mixed> $data Sanitised form.
     * @return void
     */
    protected function persist(array $data): void
    {
        // WHY: 1-1 ownership — a product may only reference brand/tax/category rows
        // owned by its own store. The dropdowns are already store-scoped, but a
        // crafted Livewire payload could still bind a foreign id, so reject
        // cross-store references server-side (RISK-TECH-store-same-store-ref).
        $this->assertSameStoreRefs($data);

        $connection = defined('GP247_DB_CONNECTION') ? GP247_DB_CONNECTION : null;
        \DB::connection($connection)->transaction(function () use ($data): void {
            $attributes = $this->productAttributes($data);

            if ($this->editingId !== null) {
                $product = ShopProduct::findOrFail($this->editingId);
                $product->update($attributes);
            } else {
                // WHY: 1-1 ownership — a new product is owned by the current admin
                // store (pinned to root in admin); set its scalar store_id on create.
                $attributes['store_id'] = $this->storeId();
                $product = AdminProduct::createProductAdmin($attributes);
            }

            // WHY: keepStateOnSave — expose the persisted id (incl. after create)
            // so ResourcePanel::save() can re-fill the form and keep it on screen.
            $this->editingId = (string) $product->id;

            $this->saveDescriptions($product->id);

            if (function_exists('gp247_custom_field_update')) {
                gp247_custom_field_update($this->customFieldsPayload(), (string) $product->id, $this->customFieldType());
            }

            $product->categories()->sync(array_filter((array) ($data['category'] ?? [])));

            $this->persistImages($product);
            $this->persistVariants($product);
            $this->persistComposition($product);
            $this->persistPricing($product, $data);
            $this->persistTags($product, $data);
        });
    }

    /**
     * Reject a save that references a brand, tax or category owned by another
     * store. Empty values pass (they clear the field); only a non-empty id that
     * resolves to a different store's row is rejected.
     *
     * @param array<string, mixed> $data Sanitised form.
     * @return void
     * @throws ValidationException When any reference belongs to another store.
     *
     * @aidlc-adr multi-store_one-to-one-store-ownership
     */
    private function assertSameStoreRefs(array $data): void
    {
        $storeId = $this->storeId();

        $brandId = (string) ($data['brand_id'] ?? '');
        if ($brandId !== '' && ShopBrand::where('id', $brandId)->where('store_id', $storeId)->doesntExist()) {
            throw ValidationException::withMessages([
                'form.brand_id' => $this->label('product.brand') . ': invalid store reference',
            ]);
        }

        $taxId = (string) ($data['tax_id'] ?? '');
        if ($taxId !== '' && ShopTax::where('id', $taxId)->where('store_id', $storeId)->doesntExist()) {
            throw ValidationException::withMessages([
                'form.tax_id' => $this->label('product.tax') . ': invalid store reference',
            ]);
        }

        $categoryIds = array_values(array_filter((array) ($data['category'] ?? [])));
        if ($categoryIds !== []) {
            $ownedCount = ShopCategory::whereIn('id', $categoryIds)
                ->where('store_id', $storeId)
                ->count();
            if ($ownedCount !== count($categoryIds)) {
                throw ValidationException::withMessages([
                    'form.category' => $this->label('product.category') . ': invalid store reference',
                ]);
            }
        }
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
            // WHY: a gated field turned off in config is not on the form; leaving it out
            // of the update set preserves the real DB value instead of overwriting it
            // with an empty default (lost-update — modification 20260826T093146).
            if ($this->gatedFieldDisabled($field)) {
                continue;
            }
            $attributes[$field] = (string) ($data[$field] ?? '');
        }
        foreach (self::NUMERIC_FIELDS as $field) {
            // WHY: same scope rule as STRING_FIELDS — e.g. an off product_stock must not
            // reset the real stock column (which orders may have changed concurrently).
            if ($this->gatedFieldDisabled($field)) {
                continue;
            }
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
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        // ShopProduct::boot() cascades descriptions/images/attributes/pivots/etc.
        $model = $this->baseQuery()->find($id);
        if ($model !== null) {
            $model->delete();
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

    /**
     * Whether a config-gated product field should be shown in the add/edit form.
     *
     * Mirrors structureTypeEnabled(): a product_config_attribute toggle that is
     * absent (config not yet seeded) is treated as ENABLED so default behaviour is
     * preserved on a fresh install; only an explicit '0'/0 hides the field. This is
     * the single predicate shared by all three gates — render (blade), validate
     * (configRules()) and persist (productAttributes()) — so a field turned off in
     * Shop Config is neither shown, validated, nor written (render/validate/persist
     * parity, US-SADM-product-field-config-scope, ADR shop-admin_product-field-config-scope).
     *
     * @param string $configKey product_config_attribute key (e.g. 'product_price').
     * @return bool
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-001
     */
    public function productFieldEnabled(string $configKey): bool
    {
        if (!function_exists('gp247_config')) {
            return true;
        }
        $value = gp247_config($configKey);

        return $value !== '0' && $value !== 0;
    }

    /**
     * Whether a form field is a config-gated field whose gate is currently OFF.
     *
     * Resolves the field back to its product_config_attribute key via GATED_FIELDS
     * and negates productFieldEnabled(); non-gated fields are never "disabled" here.
     * Used to scope such a field out of both validation and persistence so it matches
     * the blade render gate (modification 20260826T093146).
     *
     * @param string $field shop_product column name (e.g. 'stock', 'brand_id').
     * @return bool True only when $field is gated AND its gate is turned off.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-product-field-config-scope
     * @aidlc-adr shop-admin_product-field-config-scope
     */
    private function gatedFieldDisabled(string $field): bool
    {
        $configKey = array_search($field, self::GATED_FIELDS, true);

        return $configKey !== false && !$this->productFieldEnabled($configKey);
    }

    // --- Render -------------------------------------------------------------

    /**
     * Override render() to feed the store-scoped product list into the view.
     *
     * @return View
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-001
     * @aidlc-adr multi-store_one-to-one-store-ownership
     */
    public function render(): View
    {
        // WHY: 1-1 ownership — a product has a single owning store (pinned to the
        // current admin store), so no multi-store picker context is injected.
        return view($this->panelView(), [
            'rows' => $this->rows(),
        ])->layout('gp247-admin::layouts.admin', ['title' => $this->pageTitle()]);
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
        // WHY: 1-1 ownership — only offer brands owned by the current store so an
        // admin cannot reference another store's brand (RISK-TECH-store-same-store-ref).
        return ShopBrand::where('store_id', $this->storeId())->pluck('name', 'id')->all();
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
        // WHY: 1-1 ownership — only offer taxes owned by the current store so an
        // admin cannot reference another store's tax (RISK-TECH-store-same-store-ref).
        return ShopTax::where('store_id', $this->storeId())->pluck('name', 'id')->all();
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
