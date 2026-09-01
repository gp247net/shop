<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\HasMultilingualDescriptions;
use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\Models\AdminLanguage;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
use GP247\Shop\Admin\Models\AdminCategory;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopCategoryDescription;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;

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
        return ShopCategory::query()->with(['store.descriptions']);
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['alias'];
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
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $table = (new ShopCategory())->getTable();

        return [
            'form.alias' => ['required', 'string', 'max:100', Rule::unique($table, 'alias')->ignore($this->editingId)],
            'form.sort' => ['nullable', 'numeric', 'min:0'],
            'desc.*.name' => ['required', 'string', 'max:200'],
            'desc.*.keyword' => ['nullable', 'string', 'max:200'],
            'desc.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Reuse the existing v1 category label keys (the per-language title maps to
     * admin.category.title, matching the form label).
     *
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return [
            'form.alias' => 'admin.category.alias',
            'form.sort' => 'admin.category.sort',
            'desc.*.name' => 'admin.category.title',
            'desc.*.keyword' => 'admin.category.keyword',
            'desc.*.description' => 'admin.category.description',
        ];
    }

    /**
     * Derive alias from the first language's title when left blank (brownfield
     * parity) before the secure save pipeline.
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
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        $attributes = [
            'image' => $data['image'] ?? '',
            'alias' => $data['alias'],
            'parent' => empty($data['parent']) ? null : $data['parent'],
            'top' => empty($data['top']) ? 0 : 1,
            'status' => empty($data['status']) ? 0 : 1,
            'sort' => (int) ($data['sort'] ?? 0),
        ];

        if ($this->editingId !== null) {
            // Store is immutable on edit — do NOT touch store_id (ADR 1-1).
            $store = (string) ShopCategory::whereKey($this->editingId)->value('store_id');
            $this->assertParentSameStore($attributes['parent'] ?? null, $store);
            $category = ShopCategory::findOrFail($this->editingId);
            $category->update($attributes);
        } else {
            // WHY: 1-1 ownership — a new category is owned by the store picked on create
            // (root admin) or the current scoped store (store-admin / switcher).
            // ADR admin-shell_store-scoped-resource-panel.
            $store = $this->resolveCreateStore();
            $this->assertParentSameStore($attributes['parent'] ?? null, $store);
            $attributes['store_id'] = $store;
            $category = ShopCategory::create($attributes);
        }

        // WHY: keepStateOnSave — expose the persisted id (incl. after create) so
        // ResourcePanel::save() can re-fill the form and keep it on screen.
        $this->editingId = (string) $category->id;

        $this->saveDescriptions($category->id);

        // WHY: keep the category title cache coherent with the legacy controller.
        if (function_exists('gp247_cache_clear')) {
            gp247_cache_clear('cache_category');
        }
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
     * Reject a parent category that belongs to another store (same-store integrity,
     * server-side — NFR-SEC-store-scoped-related-integrity). No-op when not store-scoped.
     *
     * @param int|string|null $parentId
     * @param int|string      $storeId
     * @return void
     */
    private function assertParentSameStore($parentId, $storeId): void
    {
        if (!$this->storeScopeActive() || empty($parentId)) {
            return;
        }
        $ok = ShopCategory::whereKey($parentId)->where('store_id', $storeId)->exists();
        if (!$ok) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'form.parent' => gp247_language_render('admin.store.related_wrong_store'),
            ]);
        }
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
