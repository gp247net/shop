<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\HasMultilingualDescriptions;
use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\Models\AdminLanguage;
use GP247\Core\Models\AdminStore;
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
 * lives in the description table). Multi-store assignment is now implemented.
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

    /** @var array<int, int|string> Assigned store ids for the current form. */
    public array $stores = [];

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
        return ShopCategory::query()->with(['stores.descriptions']);
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
        $this->stores = [];
        $this->initDescriptions();
    }

    /**
     * @param ShopCategory $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        $this->fillDescriptions($model->descriptions);
        $this->stores = $model->stores()->pluck('store_id')->map(fn ($v): string => (string) $v)->all();

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
            $category = ShopCategory::findOrFail($this->editingId);
            $category->update($attributes);
        } else {
            $category = ShopCategory::create($attributes);
        }

        // WHY: keepStateOnSave — expose the persisted id (incl. after create) so
        // ResourcePanel::save() can re-fill the form and keep it on screen.
        $this->editingId = (string) $category->id;

        $this->saveDescriptions($category->id);
        $category->stores()->sync($this->stores);

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
     * Override render() to inject multi-store context into the view.
     *
     * @return View
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-002
     */
    public function render(): View
    {
        $multiStore = gp247_store_check_multi_partner_installed() || gp247_store_check_multi_store_installed();

        return view($this->panelView(), [
            'rows'       => $this->rows(),
            'multiStore' => $multiStore,
            'storeList'  => $multiStore ? AdminStore::getListTitle() : [],
        ])->layout('gp247-admin::layouts.admin', ['title' => $this->pageTitle()]);
    }

    /**
     * Parent-category options (id => indented title) for the form select.
     *
     * @return array<string, string>
     */
    public function parentOptions(): array
    {
        return (new AdminCategory())->getTreeCategoriesAdmin();
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
