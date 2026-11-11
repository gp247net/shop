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
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductDescription;
use GP247\Shop\Models\ShopSupplier;
use GP247\Shop\Models\ShopTax;
use GP247\Shop\Services\Catalog\ProductWriter;
use GP247\Shop\Services\Catalog\WriteContext;
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

    /**
     * Store-scoped: pick a store on create (root admin), show it in the list, lock
     * it on edit; related option lists (category/brand/tax) filter to that store.
     *
     * @return array<string, mixed>|null
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-store-content-assignment
     * @aidlc-adr admin-shell_store-scoped-resource-panel
     */
    protected function storeScoped(): ?array
    {
        // 'reset': store-scoped form fields cleared when the create picker changes
        // store, so a stale cross-store category/brand/tax reference cannot linger.
        return ['display' => 'name', 'reset' => ['category', 'brand_id', 'tax_id']];
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
        // Store-scoped at root admin shows EVERY store's products (so each store's
        // catalog can be managed from one admin, with the store shown per row);
        // a scoped context (store-admin/switcher) or single-store still filters to
        // the own store (ADR admin-shell_store-scoped-resource-panel).
        $query = ShopProduct::query()->with(['store.descriptions']);
        if (!($this->storeScopeActive() && $this->isRootScope())) {
            $query->where('store_id', $this->storeId());
        }

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
     * Language keys naming the searched columns (shown in the search box).
     *
     * @return array<string, string>
     *
     * @aidlc-story US-admin-shell-list-search-scope-hint
     */
    protected function searchLabels(): array
    {
        return [
            'sku' => 'product.sku',
            'alias' => 'admin.search.col_alias',
        ];
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
        foreach (ProductWriter::STRING_FIELDS as $field) {
            $defaults[$field] = '';
        }
        foreach (ProductWriter::NUMERIC_FIELDS as $field) {
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

        // Store is immutable on edit — expose it for the read-only display + to scope
        // related option lists (category/brand/tax) to the record's own store.
        $this->formStoreId = (string) $model->store_id;

        $form = [
            'kind' => (int) $model->kind,
            'status' => (int) $model->status,
            'approve' => (int) $model->approve,
            'sort' => (int) $model->sort,
            'minimum' => (float) $model->minimum,
            'date_available' => $model->date_available ? (string) $model->date_available : '',
            'category' => $model->categories()->pluck('category_id')->map(static fn ($id) => (string) $id)->all(),
        ];
        foreach (ProductWriter::STRING_FIELDS as $field) {
            $form[$field] = (string) ($model->{$field} ?? '');
        }
        foreach (ProductWriter::NUMERIC_FIELDS as $field) {
            $form[$field] = (float) ($model->{$field} ?? 0);
        }

        return array_merge($form, $this->pricingFormFrom($model), $this->tagsFormFrom($model));
    }

    /**
     * The product form rules — owned by the shared write service so the screen and every
     * other writer validate alike (US-SADM-catalog-write-service) — plus the rules of the
     * screen-only state (custom fields, BUILD/GROUP items).
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = $this->writer()->rules($this->writeContext(), $this->editingId, $this->writeOptions());

        return array_merge($rules, $this->customFieldRules(), $this->compositionRules());
    }

    /**
     * Friendly attribute names for every rule (Livewire hook) — the localised field name
     * instead of the raw "form.*" path.
     *
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return $this->writer()->attributeLabels();
    }

    /**
     * Localised validator messages (shared validation.* keys, DB translations).
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return $this->writer()->messages();
    }

    /**
     * Auto-derive the alias from the first language's name when empty, then run the
     * standard validate/persist/redirect.
     *
     * @return void
     */
    public function save(): void
    {
        $firstLang = $this->firstDescriptionLanguage();
        $this->form['alias'] = ProductWriter::alias(
            (string) ($this->form['alias'] ?? ''),
            $firstLang !== null ? (string) ($this->desc[$firstLang]['name'] ?? '') : ''
        );

        parent::save();
    }

    /**
     * Persist the product through the shared write service (main row, texts, custom
     * fields, categories, gallery, variants, composition, promotion, download, tags in one
     * transaction). The form was validated by save() with the service's own rules.
     *
     * @param array<string, mixed> $data Sanitised form.
     * @return void
     * @throws ValidationException When a reference belongs to another store.
     *
     * @aidlc-story US-SADM-001, US-SADM-catalog-write-service
     * @aidlc-adr shop-admin_catalog-write-service
     */
    protected function persist(array $data): void
    {
        $product = $this->writer()->save($this->writeContext(), [
            'form' => $data,
            'descriptions' => $this->desc,
            'custom_fields' => $this->customFieldsPayload(),
            'images' => $this->gallery,
            'variants' => $this->variants,
            'composition' => ['build' => $this->buildItems, 'group' => $this->groupItems],
        ], $this->editingId, array_merge($this->writeOptions(), ['validated' => true, 'clean' => false]));

        // WHY: keepStateOnSave — expose the persisted id (incl. after create)
        // so ResourcePanel::save() can re-fill the form and keep it on screen.
        $this->editingId = (string) $product->id;
    }

    /**
     * @return ProductWriter
     */
    private function writer(): ProductWriter
    {
        return new ProductWriter;
    }

    /**
     * Who writes and into which store: the product's own store on edit (read from the DB,
     * never the client-tamperable form), the server-authoritative create store otherwise.
     *
     * @return WriteContext
     */
    private function writeContext(): WriteContext
    {
        $store = $this->editingId !== null && $this->editingId !== ''
            ? ShopProduct::whereKey($this->editingId)->value('store_id')
            : $this->resolveCreateStore();
        if ($store === null || (string) $store === '') {
            // Building the rules before a store is picked (root admin): the store-pick rule
            // of ResourcePanel::save() rejects the save, so any store will do here.
            $store = $this->storeContext();
        }
        $admin = function_exists('admin') ? admin()->user() : null;

        return new WriteContext((string) $store, WriteContext::ACTOR_ADMIN, $admin !== null ? (string) $admin->id : null);
    }

    /**
     * Screen-specific write options: the reference store from referenceStoreId() (a
     * marketplace screen may override it), field gating by the product's own store,
     * sku / alias uniqueness in the admin session's store (as the screen always checked).
     *
     * @return array<string, mixed>
     */
    private function writeOptions(): array
    {
        return [
            'reference_store' => $this->referenceStoreId(),
            'config_store' => $this->formStoreId !== '' ? $this->formStoreId : null,
            'unique_store' => '',
        ];
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
     * Whether a config-gated product field should be shown in the add/edit form.
     *
     * Mirrors structureTypeEnabled(): a product_config_attribute toggle that is
     * absent (config not yet seeded) is treated as ENABLED so default behaviour is
     * preserved on a fresh install; only an explicit '0'/0 hides the field. This is
     * the single predicate shared by all three gates — render (blade), validate
     * (ProductWriter::rules()) and persist (ProductWriter::save()) — so a field turned off in
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
        // WHY $this->formStoreId (mod 20260902T080541): field gating follows the product's
        // OWN store, not the admin session — so a root admin editing a sub-store's product
        // sees that store's field config. Empty store falls back to GLOBAL (unchanged for
        // single-store / stores with no per-store override). ADR admin-shell_lfm-working-dir
        // extends the working-store principle to config resolution. Shared with the admin
        // API (US-api-admin-write-product-fields).
        return \GP247\Shop\Services\ProductFieldRules::enabled($configKey, $this->formStoreId ?: null);
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
     * The store whose categories, brands and taxes a product may reference — the
     * dropdowns list it and assertSameStoreRefs() checks against it. It defaults to
     * the store that OWNS the record, which is what root admin and MultiStore mean
     * (1-1 ownership). A screen whose products are filed under another store's
     * taxonomy says so through a resolver — a shared marketplace files vendor
     * products under the marketplace (root) categories while the product stays
     * owned by the vendor. Only the reference store changes; ownership
     * (currentStore()) never does.
     *
     * WHY a resolver registry, not a screen override: which taxonomy a product uses
     * is a property of the marketplace model, and several screens edit the same
     * products (the vendor's and the marketplace admin's). Registration mirrors
     * the price resolvers:
     *
     *   config(['gp247-config.shop.product_reference_store_resolvers' => [
     *       ['key' => 'MyPlugin', 'callback' => [MyTaxonomy::class, 'referenceStore']],
     *   ]]);
     *
     * The callback receives the owner store id (string) and returns ?string; the
     * first non-null answer wins, and a failing resolver is reported and skipped.
     *
     * @return string|int|null
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-store-single-owner
     * @aidlc-adr shop-admin_product-reference-store
     */
    protected function referenceStoreId()
    {
        return ProductWriter::referenceStoreFor($this->currentStore());
    }

    /**
     * @return array<int|string, string>
     */
    public function categoryOptions(): array
    {
        if (!$this->storeScopeActive()) {
            return (array) (new AdminCategory())->getTreeCategoriesAdmin();
        }
        // Store-scoped: only the reference store's categories (none until picked).
        $store = $this->referenceStoreId();
        if ($store === null || $store === '') {
            return [];
        }
        $catTable = (new ShopCategory)->getTable();
        $descTable = (new \GP247\Shop\Models\ShopCategoryDescription)->getTable();

        return ShopCategory::query()
            ->where($catTable . '.store_id', $store)
            ->join($descTable, $descTable . '.category_id', $catTable . '.id')
            ->where($descTable . '.lang', gp247_get_locale())
            ->pluck($descTable . '.name', $catTable . '.id')
            ->all();
    }

    /**
     * Category options for the LIST filter. At root admin (store-scoped) the list
     * shows every store's products, so the filter offers categories across all
     * stores; otherwise it mirrors the (store-scoped) form options.
     *
     * @return array<int|string, string>
     */
    public function filterCategoryOptions(): array
    {
        if (!($this->storeScopeActive() && $this->isRootScope())) {
            return $this->categoryOptions();
        }
        $catTable = (new ShopCategory)->getTable();
        $descTable = (new \GP247\Shop\Models\ShopCategoryDescription)->getTable();

        return ShopCategory::query()
            ->join($descTable, $descTable . '.category_id', $catTable . '.id')
            ->where($descTable . '.lang', gp247_get_locale())
            ->pluck($descTable . '.name', $catTable . '.id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function brandOptions(): array
    {
        // WHY: 1-1 ownership — only offer brands owned by the record's store so an
        // admin cannot reference another store's brand (RISK-TECH-store-same-store-ref).
        $store = $this->referenceStoreId();
        if ($this->storeScopeActive() && ($store === null || $store === '')) {
            return [];
        }
        return ShopBrand::where('store_id', $store)->pluck('name', 'id')->all();
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
        // WHY: 1-1 ownership — only offer taxes owned by the record's store so an
        // admin cannot reference another store's tax (RISK-TECH-store-same-store-ref).
        $store = $this->referenceStoreId();
        if ($this->storeScopeActive() && ($store === null || $store === '')) {
            return [];
        }
        return ShopTax::where('store_id', $store)->pluck('name', 'id')->all();
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
