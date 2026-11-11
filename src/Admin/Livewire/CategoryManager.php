<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\HasMultilingualDescriptions;
use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\Models\AdminLanguage;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
use GP247\Shop\Admin\Models\AdminCategory;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopCategoryDescription;
use GP247\Shop\Services\Catalog\CategoryWriter;
use GP247\Shop\Services\Catalog\WriteContext;
use Illuminate\Contracts\View\View;

/**
 * Category manager (shop-admin Unit) — two-panel screen (add/edit form left, list
 * right) on the core ResourcePanel base plus the reusable multilingual trait
 * (C0), matching the legacy AdminCategoryController (rule ui-tailadmin P1):
 * per-language title/keyword/description, alias (auto from the first language's
 * title, unique), parent, image (LFM), top/status/sort. Domain unchanged
 * (ShopCategory). Gated by `admin_category`.
 *
 * Intentional Phase-C simplifications (parity with Phase 1, documented in the
 * results doc): custom fields (type shop_category) are not yet surfaced; the
 * legacy screen remains available (strangler). List search is by alias (title
 * lives in the description table). Store ownership is 1-1 (scalar store_id).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-002
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class CategoryManager extends ResourcePanel
{
    use HasMultilingualDescriptions;
    use HasValidationLabels;

    protected ?string $permission = 'admin_category';

    /**
     * Keep the list panel (page/keyword/sort) and the just-saved category on
     * screen when editing/saving, instead of remounting via route navigation.
     *
     * @var bool
     * @aidlc-story US-AUI-two-panel-state-preservation
     * @aidlc-adr ADR-admin-shell-rbac-two-panel-state-preservation
     */
    protected bool $keepStateOnSave = true;

    /**
     * @return array<int, string>
     */
    protected function multilingualFields(): array
    {
        return ['name', 'keyword', 'description'];
    }

    /**
     * @return class-string
     */
    protected function descriptionModelClass(): string
    {
        return ShopCategoryDescription::class;
    }

    /**
     * @return string
     */
    protected function descriptionForeignKey(): string
    {
        return 'category_id';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        // WHY: 1-1 ownership — eager-load the single owning store (store.descriptions).
        // Store-scoped at root admin shows EVERY store's categories (each row labelled
        // by its store); a scoped context (store-admin/switcher) or a single-store
        // install filters to the own store — parity with ProductManager so both screens
        // agree on what the current store context sees (ADR admin-shell_store-scoped-resource-panel).
        $query = ShopCategory::query()->with(['store.descriptions']);
        if (!($this->storeScopeActive() && $this->isRootScope())) {
            $query->where('store_id', $this->storeContext());
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['alias'];
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
            'alias' => 'admin.search.col_alias',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['alias', 'sort', 'id'];
    }

    /**
     * @return array<int, string>
     */
    protected function defaultSort(): array
    {
        return ['sort', 'asc'];
    }

    /**
     * Store-scoped: pick a store on create (root admin), show it in the list, lock
     * it on edit; the list labels each row by its category name.
     *
     * @return array<string, mixed>|null
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-store-content-assignment
     * @aidlc-adr admin-shell_store-scoped-resource-panel
     */
    protected function storeScoped(): ?array
    {
        // 'reset': form fields cleared when the create picker changes store (the
        // parent category is store-scoped, so a stale cross-store parent must go).
        return ['display' => 'name', 'reset' => ['parent']];
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::category-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.category.list');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_category.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return ['image' => '', 'alias' => '', 'parent' => '', 'top' => 0, 'status' => 1, 'sort' => 0];
    }

    /**
     * Reset both the scalar form and the per-language description state.
     *
     * @return void
     */
    public function resetForm(): void
    {
        parent::resetForm();
        $this->initDescriptions();
    }

    /**
     * @param ShopCategory $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        $this->fillDescriptions($model->descriptions);

        // Store is immutable on edit — expose it for the read-only display + to scope
        // related option lists to the record's own store.
        $this->formStoreId = (string) $model->store_id;

        return [
            'image' => (string) $model->image,
            'alias' => (string) $model->alias,
            'parent' => (string) ($model->parent ?? ''),
            'top' => (int) $model->top,
            'status' => (int) $model->status,
            'sort' => (int) $model->sort,
        ];
    }

    /**
     * The category form rules, owned by the shared write service (US-SADM-catalog-write-service).
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return (new CategoryWriter)->rules($this->editingId);
    }

    /**
     * Reuse the existing v1 category label keys (the per-language title maps to
     * admin.category.title, matching the form label).
     *
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return (new CategoryWriter)->labelKeys();
    }

    /**
     * Derive alias from the first language's title when left blank (brownfield
     * parity) before the secure save pipeline.
     *
     * @return void
     */
    public function save(): void
    {
        $firstLang = $this->firstDescriptionLanguage();
        $this->form['alias'] = CategoryWriter::alias(
            (string) ($this->form['alias'] ?? ''),
            $firstLang !== null ? (string) ($this->desc[$firstLang]['name'] ?? '') : ''
        );

        parent::save();
    }

    /**
     * Persist through the shared write service; the form was validated by save() with the
     * service's own rules. The parent's store is checked only when store scoping is on
     * (as the screen always did).
     *
     * @param array<string, mixed> $data Sanitised form.
     * @return void
     *
     * @aidlc-story US-SADM-002, US-SADM-catalog-write-service
     * @aidlc-adr shop-admin_catalog-write-service
     */
    protected function persist(array $data): void
    {
        $editing = $this->editingId !== null && $this->editingId !== '';
        // Store is immutable on edit (ADR 1-1): the record's own store, read from the DB.
        $store = $editing ? (string) ShopCategory::whereKey($this->editingId)->value('store_id') : (string) $this->resolveCreateStore();
        $category = (new CategoryWriter)->save(
            new WriteContext($store !== '' ? $store : (string) $this->storeContext()),
            ['form' => $data, 'descriptions' => $this->desc],
            $editing ? $this->editingId : null,
            ['validated' => true, 'clean' => false, 'strict_store' => $this->storeScopeActive()]
        );

        // WHY: keepStateOnSave — expose the persisted id (incl. after create) so
        // ResourcePanel::save() can re-fill the form and keep it on screen.
        $this->editingId = (string) $category->id;
    }

    /**
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        // ShopCategory::boot() cascades descriptions / pivots / custom fields.
        $model = $this->baseQuery()->find($id);
        if ($model !== null) {
            $model->delete();
            if (function_exists('gp247_cache_clear')) {
                gp247_cache_clear('cache_category');
            }
        }
    }

    /**
     * Override render() to feed the category list into the view.
     *
     * @return View
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-002
     * @aidlc-adr multi-store_one-to-one-store-ownership
     */
    public function render(): View
    {
        // WHY: 1-1 ownership — a category has a single owning store (pinned to the
        // current admin store), so no multi-store picker context is injected.
        return view($this->panelView(), [
            'rows' => $this->rows(),
        ])->layout('gp247-admin::layouts.admin', ['title' => $this->pageTitle()]);
    }

    /**
     * Parent-category options (id => indented title) for the form select.
     *
     * @return array<string, string>
     */
    public function parentOptions(): array
    {
        if (!$this->storeScopeActive()) {
            return (new AdminCategory())->getTreeCategoriesAdmin();
        }

        // Store-scoped: parent candidates are categories of the SAME store (the picked
        // store on create, or the record's store on edit); none until a store is chosen.
        $store = $this->currentStore();
        if ($store === null || $store === '') {
            return [];
        }

        $catTable = (new ShopCategory)->getTable();
        $descTable = (new ShopCategoryDescription)->getTable();

        return ShopCategory::query()
            ->where($catTable . '.store_id', $store)
            ->when($this->editingId !== null && $this->editingId !== '', function ($q) use ($catTable) {
                $q->where($catTable . '.id', '!=', $this->editingId);
            })
            ->join($descTable, $descTable . '.category_id', $catTable . '.id')
            ->where($descTable . '.lang', gp247_get_locale())
            ->pluck($descTable . '.name', $catTable . '.id')
            ->all();
    }

    /**
     * Active languages (code => language model) for the description tabs.
     *
     * @return array<string, mixed>
     */
    public function languages(): array
    {
        return AdminLanguage::getListActive()->all();
    }
}
