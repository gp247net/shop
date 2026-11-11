<?php

namespace GP247\Shop\Services\Catalog;

use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopCategoryDescription;
use Illuminate\Validation\Rule;

/**
 * Creates and edits a category with the rules of the admin Category screen.
 *
 * Payload: `form` (image, alias, parent, top, status, sort — an empty alias is derived from
 * the first language's title) and `descriptions` (lang => [name, keyword, description]).
 * On edit a part or field that is absent is left as stored.
 *
 * Options (besides those of {@see CatalogWriter}):
 *  - `strict_store` (bool, default true): the parent must belong to the same store. The
 *    screen passes false on a single-store site, where it never checked.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-catalog-write-service
 * @aidlc-adr shop-admin_catalog-write-service
 */
class CategoryWriter extends CatalogWriter
{
    /** Multilingual text fields. */
    public const TEXT_FIELDS = ['name', 'keyword', 'description'];

    /** Columns written from the form. */
    private const COLUMNS = ['image', 'alias', 'parent', 'top', 'status', 'sort'];

    /**
     * @param WriteContext $ctx Owner store (on edit: the category's own store).
     * @param array<string, mixed> $payload
     * @param string|null $id Category to edit.
     * @param array<string, mixed> $options
     * @return ShopCategory
     * @throws \Illuminate\Validation\ValidationException
     */
    public function save(WriteContext $ctx, array $payload, ?string $id = null, array $options = []): ShopCategory
    {
        $current = $id !== null ? $this->ownedOrFail(ShopCategory::class, $id, $ctx) : null;
        $payload['form'] = $this->formOf($payload, $options);
        if (!($options['validated'] ?? false)) {
            $payload = $this->prepare($payload, $current);
            $errors = $this->errors($payload, $current);
            if ($errors !== []) {
                $this->fail($errors);
            }
        }
        $given = $payload['form'];
        $form = array_merge($current !== null ? $this->storedForm($current) : ['image' => '', 'parent' => '', 'top' => 0, 'status' => 1, 'sort' => 0], $given);
        if ($options['strict_store'] ?? true) {
            $this->assertParentSameStore($form['parent'] ?? null, $ctx->storeId);
        }

        return $this->transaction(function () use ($ctx, $payload, $given, $form, $current): ShopCategory {
            $attributes = [
                'image' => $form['image'] ?? '',
                'alias' => $form['alias'],
                'parent' => empty($form['parent']) ? null : $form['parent'],
                'top' => empty($form['top']) ? 0 : 1,
                'status' => empty($form['status']) ? 0 : 1,
                'sort' => (int) ($form['sort'] ?? 0),
            ];
            if ($current !== null) {
                $category = $current;
                $category->update(array_intersect_key($attributes, array_flip(array_intersect(self::COLUMNS, array_keys($given)))));
            } else {
                $attributes['store_id'] = $ctx->storeId;
                $category = ShopCategory::create($attributes);
            }
            if (array_key_exists('descriptions', $payload)
                && $this->writeDescriptions(ShopCategoryDescription::class, 'category_id', $category->id, self::TEXT_FIELDS, [], (array) $payload['descriptions'])) {
                // New texts move updated_at so "changed since" lists show the category (US-api-admin-write-shop-events).
                $category->touch();
            }
            // WHY: keep the category title cache coherent with the legacy controller.
            if (function_exists('gp247_cache_clear')) {
                gp247_cache_clear('cache_category');
            }

            return $category;
        });
    }

    /**
     * Check a payload without writing.
     *
     * @param WriteContext $ctx
     * @param array<string, mixed> $payload
     * @param string|null $id
     * @param array<string, mixed> $options
     * @return array<string, array<int, string>>
     */
    public function validate(WriteContext $ctx, array $payload, ?string $id = null, array $options = []): array
    {
        $current = $id !== null ? ShopCategory::find($id) : null;
        if ($id !== null && ($current === null || (string) $current->store_id !== $ctx->storeId)) {
            return ['store_id' => [(string) gp247_language_render('admin.store.related_wrong_store')]];
        }
        $payload['form'] = $this->formOf($payload, $options);
        $payload = $this->prepare($payload, $current);
        $errors = $this->errors($payload, $current);
        if ($errors === [] && ($options['strict_store'] ?? true)) {
            try {
                $parent = array_key_exists('parent', $payload['form']) ? $payload['form']['parent'] : $current?->parent;
                $this->assertParentSameStore($parent, $ctx->storeId);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return $e->errors();
            }
        }

        return $errors;
    }

    /**
     * Validation rules of the category form, as the screen validates.
     *
     * @param string|null $id Category being edited.
     * @return array<string, mixed>
     */
    public function rules(?string $id): array
    {
        return [
            'form.alias' => ['required', 'string', 'max:100', Rule::unique((new ShopCategory())->getTable(), 'alias')->ignore($id)],
            'form.sort' => ['nullable', 'numeric', 'min:0'],
            'desc.*.name' => ['required', 'string', 'max:200'],
            'desc.*.keyword' => ['nullable', 'string', 'max:200'],
            'desc.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Language keys of the field labels, keyed by rule path.
     *
     * @return array<string, string>
     */
    public function labelKeys(): array
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
     * Reject a parent owned by another store. Empty passes.
     *
     * @param int|string|null $parentId
     * @param string $storeId
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assertParentSameStore($parentId, string $storeId): void
    {
        if (empty($parentId)) {
            return;
        }
        if (!ShopCategory::whereKey($parentId)->where('store_id', $storeId)->exists()) {
            $this->fail(['form.parent' => (string) gp247_language_render('admin.store.related_wrong_store')]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param ShopCategory|null $current
     * @return array<string, mixed>
     */
    private function prepare(array $payload, ?ShopCategory $current): array
    {
        if ($current === null || array_key_exists('alias', $payload['form'])) {
            $first = $this->languageCodes()[0] ?? null;
            $name = $first !== null ? (string) ($payload['descriptions'][$first]['name'] ?? '') : '';
            if ($name === '' && $current !== null && $first !== null) {
                $name = (string) ShopCategoryDescription::where('category_id', $current->id)->where('lang', $first)->value('name');
            }
            $payload['form']['alias'] = self::alias((string) ($payload['form']['alias'] ?? ''), $name);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param ShopCategory|null $current
     * @return array<string, array<int, string>>
     */
    private function errors(array $payload, ?ShopCategory $current): array
    {
        $rules = $this->rules($current !== null ? (string) $current->id : null);
        $data = ['form' => array_merge($current !== null ? $this->storedForm($current) : [], $payload['form'])];
        if (array_key_exists('descriptions', $payload)) {
            $data['desc'] = $current === null
                ? (array) $payload['descriptions']
                : $this->descriptionsForCheck(ShopCategoryDescription::class, 'category_id', $current->id, self::TEXT_FIELDS, (array) $payload['descriptions']);
        }
        if ($current === null) {
            $rules['desc'] = 'required|array';
        }
        $labels = array_map(fn (string $key): string => $this->label($key), $this->labelKeys());

        return $this->check($data, $rules, $this->localizedMessages($labels, ['required', 'min', 'max', 'numeric', 'string']), $labels);
    }

    /**
     * @param ShopCategory $category
     * @return array<string, mixed>
     */
    private function storedForm(ShopCategory $category): array
    {
        return [
            'image' => (string) $category->image, 'alias' => (string) $category->alias, 'parent' => (string) ($category->parent ?? ''),
            'top' => (int) $category->top, 'status' => (int) $category->status, 'sort' => (int) $category->sort,
        ];
    }
}
