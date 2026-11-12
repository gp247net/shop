<?php

namespace GP247\Shop\Services\Catalog;

use GP247\Shop\Admin\Models\AdminProduct;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopCategory;
use GP247\Shop\Models\ShopProduct;
use GP247\Shop\Models\ShopProductDescription;
use GP247\Shop\Models\ShopProductDownload;
use GP247\Shop\Models\ShopProductTag;
use GP247\Shop\Models\ShopTax;
use GP247\Shop\Services\ProductFieldRules;
use GP247\Shop\Services\ShopEvents;

/**
 * Creates and edits a product with the rules of the admin Product screen — the one write
 * path shared by that screen and any other writer (a data import, a CLI run).
 *
 * Payload (every part optional on edit; a part that is absent is left as stored):
 *  - `form`: the product columns as the screen's form holds them — sku, alias, image,
 *    brand_id, supplier_id, tax_id, product_type, weight_class, length_class, price, cost,
 *    stock, minimum, weight, length, width, height, kind, status, approve, sort,
 *    date_available, `category` (list of category ids), `tags` (comma-separated names),
 *    promotion_use / price_promotion / price_promotion_start / price_promotion_end,
 *    download_path. An empty alias is derived from the first language's name.
 *  - `descriptions`: lang => [name, keyword, description, content].
 *  - `custom_fields`: code => value.
 *  - `images`: list of sub-image paths. `variants`: list of [attribute_group_id, name,
 *    add_price, slug, sort]. `composition`: ['build' => [[product_id, quantity]], 'group' => [[product_id]]].
 *
 * Options (besides those of {@see CatalogWriter}):
 *  - `reference_store`: store whose categories / brands / taxes the product may use
 *    (default: {@see referenceStoreFor()} of the owner store).
 *  - `config_store`: store whose Shop Config decides which fields are on (default: the owner store).
 *  - `unique_store`: store in which sku / alias must be unique (default: the owner store).
 *
 * Errors are thrown as a ValidationException keyed like the screen (`form.*`, `desc.*`,
 * `custom_fields.*`). Public contract of gp247/shop — a breaking change is a major release.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-catalog-write-service
 * @aidlc-adr shop-admin_catalog-write-service
 */
class ProductWriter extends CatalogWriter
{
    /** Scalar product columns. */
    public const STRING_FIELDS = ['sku', 'alias', 'image', 'brand_id', 'supplier_id', 'tax_id', 'product_type', 'weight_class', 'length_class'];

    /** Numeric product columns. */
    public const NUMERIC_FIELDS = ['price', 'cost', 'stock', 'minimum', 'weight', 'length', 'width', 'height'];

    /** Numeric fields that are quantities (format follows product_qty_decimal, ADR-016). */
    public const QTY_FIELDS = ['stock', 'minimum'];

    /** Config-gated product fields: `product_config_attribute` key => form field. */
    public const GATED_FIELDS = [
        'product_price' => 'price', 'product_cost' => 'cost', 'product_stock' => 'stock',
        'product_brand' => 'brand_id', 'product_supplier' => 'supplier_id', 'product_type' => 'product_type',
    ];

    /** Multilingual text fields; `content` keeps its HTML. */
    public const TEXT_FIELDS = ['name', 'keyword', 'description', 'content'];

    /**
     * Create ($id null) or edit a product.
     *
     * @param WriteContext $ctx Owner store (on edit: the product's own store) and actor.
     * @param array<string, mixed> $payload See the class doc.
     * @param string|null $id Product to edit.
     * @param array<string, mixed> $options See the class doc.
     * @return ShopProduct
     * @throws \Illuminate\Validation\ValidationException When a rule fails or a reference belongs to another store.
     */
    public function save(WriteContext $ctx, array $payload, ?string $id = null, array $options = []): ShopProduct
    {
        $current = $id !== null ? $this->ownedOrFail(ShopProduct::class, $id, $ctx) : null;
        $given = $this->formOf($payload, $options);
        $payload['form'] = $given;

        if (!($options['validated'] ?? false)) {
            $payload = $this->prepare($payload, $current);
            $given = $payload['form'];
            $errors = $this->errors($ctx, $payload, $current, $options);
            if ($errors !== []) {
                $this->fail($errors);
            }
        }

        $form = $this->mergedForm($given, $current);
        $this->assertSameStoreRefs($form, $this->referenceStore($ctx, $options));

        return $this->transaction(function () use ($ctx, $payload, $given, $form, $current, $options): ShopProduct {
            $attributes = $this->attributes($form, $current === null ? null : array_keys($given), $this->configStore($ctx, $options));
            if ($current !== null) {
                $product = $current;
                $product->update($attributes);
            } else {
                $attributes['store_id'] = $ctx->storeId;
                $product = AdminProduct::createProductAdmin($attributes);
            }

            if (array_key_exists('descriptions', $payload)) {
                $changed = $this->writeDescriptions(ShopProductDescription::class, 'product_id', $product->id, self::TEXT_FIELDS, ['content'], (array) $payload['descriptions']);
                if ($changed) {
                    // New texts move updated_at so "changed since" lists show the product (US-api-admin-write-shop-events).
                    $product->touch();
                    if ($current !== null) {
                        ShopEvents::productTouched($product, ['descriptions']);
                    }
                }
            }
            if (array_key_exists('custom_fields', $payload) && function_exists('gp247_custom_field_update')) {
                gp247_custom_field_update((array) $payload['custom_fields'], (string) $product->id, $this->customFieldType());
            }
            if ($current === null || array_key_exists('category', $given)) {
                $product->categories()->sync(array_filter((array) ($form['category'] ?? [])));
            }
            $this->writeParts($product, $payload, $given, $form, $current === null, $this->configStore($ctx, $options));

            return $product;
        });
    }

    /**
     * Check a payload without writing anything (a data import's review step).
     *
     * @param WriteContext $ctx
     * @param array<string, mixed> $payload
     * @param string|null $id
     * @param array<string, mixed> $options
     * @return array<string, array<int, string>> Errors keyed like the screen; empty when the row would save.
     */
    public function validate(WriteContext $ctx, array $payload, ?string $id = null, array $options = []): array
    {
        $current = $id !== null ? ShopProduct::find($id) : null;
        if ($id !== null && ($current === null || (string) $current->store_id !== $ctx->storeId)) {
            return ['store_id' => [(string) gp247_language_render('admin.store.related_wrong_store')]];
        }
        $payload['form'] = $this->formOf($payload, $options);
        $payload = $this->prepare($payload, $current);
        $errors = $this->errors($ctx, $payload, $current, $options);
        if ($errors !== []) {
            return $errors;
        }
        try {
            $this->assertSameStoreRefs($this->mergedForm($payload['form'], $current), $this->referenceStore($ctx, $options));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $e->errors();
        }

        return [];
    }

    /**
     * The validation rules of the product form (`form.*`, `desc.*`) for a product of the
     * owner store — the same set the admin screen validates with.
     *
     * @param WriteContext $ctx
     * @param string|null $id Product being edited.
     * @param array<string, mixed> $options `config_store`, `unique_store`.
     * @return array<string, mixed>
     */
    public function rules(WriteContext $ctx, ?string $id, array $options = []): array
    {
        $unique = ($id ?? '') . ',' . ($options['unique_store'] ?? $ctx->storeId);
        $rules = [
            'form.sku' => 'required|string|max:50|product_sku_unique:' . $unique,
            'form.alias' => 'required|string|max:120|product_alias_unique:' . $unique,
            'form.category' => 'required|array|min:1',
            'form.sort' => 'nullable|numeric|min:0',
            'form.minimum' => 'nullable|numeric|min:0',
            'desc.*.name' => 'required|string|max:200',
            'desc.*.keyword' => 'nullable|string|max:200',
            'desc.*.description' => 'nullable|string|max:500',
            // WHY: content is optional for every product kind (US-SADM-product-content-optional).
            'desc.*.content' => 'nullable|string',
        ];

        $configStore = $this->configStore($ctx, $options);
        foreach (self::NUMERIC_FIELDS as $field) {
            // WHY: a gated field turned off in Shop Config is out of the form's scope — not
            // rendered, so not validated (a stale value must not block the save).
            if ($this->gatedFieldDisabled($field, $configStore)) {
                continue;
            }
            $rules['form.' . $field] = in_array($field, self::QTY_FIELDS, true)
                ? 'nullable|' . gp247_qty_rule('0', '0')
                : 'nullable|numeric';
        }
        if (function_exists('gp247_config')) {
            foreach (self::GATED_FIELDS as $cfg => $field) {
                if ($this->gatedFieldDisabled($field, $configStore)) {
                    continue;
                }
                if (gp247_config($cfg . '_required', $configStore)) {
                    $existing = $rules['form.' . $field] ?? 'nullable';
                    $rules['form.' . $field] = 'required|' . ltrim(str_replace('nullable', '', $existing), '|');
                }
            }
        }

        return $rules;
    }

    /**
     * Localised field labels keyed by rule path (plain text).
     *
     * @return array<string, string>
     */
    public function attributeLabels(): array
    {
        $keys = [
            'form.sku' => 'product.sku', 'form.alias' => 'product.alias', 'form.category' => 'product.category',
            'form.price' => 'product.price', 'form.cost' => 'product.cost', 'form.stock' => 'product.stock',
            'form.brand_id' => 'product.brand', 'form.supplier_id' => 'product.supplier', 'form.product_type' => 'product.tag',
            'form.sort' => 'product.sort', 'form.minimum' => 'product.minimum', 'desc.*.name' => 'product.name',
            'desc.*.keyword' => 'product.keyword', 'desc.*.description' => 'product.description', 'desc.*.content' => 'product.content',
        ];

        return array_map(fn (string $key): string => $this->label($key), $keys);
    }

    /**
     * Localised validator messages for {@see rules()}.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->localizedMessages($this->attributeLabels(), ['required', 'min', 'max', 'numeric', 'array', 'string']);
    }

    /**
     * Whether a config-gated field (by its config key) is on for the store.
     *
     * @param string $configKey e.g. 'product_price'.
     * @param string|null $store
     * @return bool
     */
    public function fieldEnabled(string $configKey, ?string $store): bool
    {
        return ProductFieldRules::enabled($configKey, $store ?: null);
    }

    /**
     * The store whose taxonomy a product of $owner uses: asks the resolvers registered in
     * `gp247-config.shop.product_reference_store_resolvers` (first non-empty answer wins, a
     * failing resolver is reported and skipped), else $owner.
     *
     * @param int|string|null $owner
     * @return int|string|null
     *
     * @aidlc-story US-SADM-store-single-owner
     * @aidlc-adr shop-admin_product-reference-store
     */
    public static function referenceStoreFor($owner)
    {
        $resolvers = config('gp247-config.shop.product_reference_store_resolvers', []);
        if (!is_array($resolvers) || $resolvers === []) {
            return $owner;
        }
        foreach ($resolvers as $resolver) {
            $callback = $resolver['callback'] ?? null;
            if (!is_callable($callback)) {
                continue;
            }
            try {
                $reference = $callback((string) $owner);
            } catch (\Throwable $e) {
                // A broken extension must not take the product write down.
                gp247_report(msg: 'Product reference store resolver failed (' . ($resolver['key'] ?? '?') . '): ' . $e->getMessage(), channel: null);
                continue;
            }
            if ($reference !== null && $reference !== '') {
                return (string) $reference;
            }
        }

        return $owner;
    }

    /**
     * Reject a brand, tax or category owned by another store than $store. Empty / "0"
     * (legacy "no reference") passes.
     *
     * @param array<string, mixed> $form
     * @param int|string|null $store
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     *
     * @aidlc-adr multi-store_one-to-one-store-ownership
     */
    public function assertSameStoreRefs(array $form, $store): void
    {
        $brandId = (string) ($form['brand_id'] ?? '');
        if ($brandId !== '' && $brandId !== '0' && ShopBrand::where('id', $brandId)->where('store_id', $store)->doesntExist()) {
            $this->fail(['form.brand_id' => $this->label('product.brand') . ': invalid store reference']);
        }
        $taxId = (string) ($form['tax_id'] ?? '');
        if ($taxId !== '' && $taxId !== '0' && ShopTax::where('id', $taxId)->where('store_id', $store)->doesntExist()) {
            $this->fail(['form.tax_id' => $this->label('product.tax') . ': invalid store reference']);
        }
        $categoryIds = array_values(array_filter((array) ($form['category'] ?? [])));
        if ($categoryIds !== [] && ShopCategory::whereIn('id', $categoryIds)->where('store_id', $store)->count() !== count($categoryIds)) {
            $this->fail(['form.category' => $this->label('product.category') . ': invalid store reference']);
        }
    }

    /**
     * Fill an empty alias from the first language's name, and the descriptions on create.
     *
     * @param array<string, mixed> $payload
     * @param ShopProduct|null $current
     * @return array<string, mixed>
     */
    private function prepare(array $payload, ?ShopProduct $current): array
    {
        if ($current === null || array_key_exists('alias', $payload['form'])) {
            $first = $this->languageCodes()[0] ?? null;
            $name = $first !== null ? (string) ($payload['descriptions'][$first]['name'] ?? '') : '';
            if ($name === '' && $current !== null && $first !== null) {
                $name = (string) ShopProductDescription::where('product_id', $current->id)->where('lang', $first)->value('name');
            }
            $payload['form']['alias'] = self::alias((string) ($payload['form']['alias'] ?? ''), $name);
        }

        return $payload;
    }

    /**
     * Validation errors of a prepared payload.
     *
     * @param WriteContext $ctx
     * @param array<string, mixed> $payload
     * @param ShopProduct|null $current
     * @param array<string, mixed> $options
     * @return array<string, array<int, string>>
     */
    private function errors(WriteContext $ctx, array $payload, ?ShopProduct $current, array $options): array
    {
        $rules = $this->rules($ctx, $current?->id !== null ? (string) $current->id : null, $options);
        $data = ['form' => $this->mergedForm($payload['form'], $current)];
        if (array_key_exists('descriptions', $payload)) {
            $data['desc'] = $current === null
                ? (array) $payload['descriptions']
                : $this->descriptionsForCheck(ShopProductDescription::class, 'product_id', $current->id, self::TEXT_FIELDS, (array) $payload['descriptions']);
        }
        if ($current === null) {
            $rules['desc'] = 'required|array';
        }
        if ($current === null || array_key_exists('custom_fields', $payload)) {
            $data['custom_fields'] = (array) ($payload['custom_fields'] ?? []);
            foreach ($this->customFieldDefs() as $field) {
                if (!empty($field->required)) {
                    $rules['custom_fields.' . $field->code] = ['required'];
                }
            }
        }
        if (array_key_exists('composition', $payload)) {
            $data['composition'] = (array) $payload['composition'];
            $rules = array_merge($rules, $this->compositionRules((int) ($data['form']['kind'] ?? 0)));
        }

        return $this->check($data, $rules, $this->messages(), $this->attributeLabels());
    }

    /**
     * Composition rules of a BUILD / GROUP product.
     *
     * @param int $kind
     * @return array<string, string>
     */
    private function compositionRules(int $kind): array
    {
        if ($kind === (defined('GP247_PRODUCT_BUILD') ? GP247_PRODUCT_BUILD : 1)) {
            return ['composition.build' => 'required|array|min:1', 'composition.build.*.quantity' => 'required|' . gp247_qty_rule()];
        }
        if ($kind === (defined('GP247_PRODUCT_GROUP') ? GP247_PRODUCT_GROUP : 2)) {
            return ['composition.group' => 'required|array|min:1'];
        }

        return [];
    }

    /**
     * The form as it would stand after the write: the stored product (or the create
     * defaults) overlaid with the given fields.
     *
     * @param array<string, mixed> $given
     * @param ShopProduct|null $current
     * @return array<string, mixed>
     */
    private function mergedForm(array $given, ?ShopProduct $current): array
    {
        return array_merge($current !== null ? $this->storedForm($current) : $this->defaults(), $given);
    }

    /**
     * Create defaults (the screen's empty form).
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $defaults = [
            'kind' => defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0,
            'status' => 1, 'approve' => 1, 'sort' => 0, 'minimum' => 0, 'date_available' => '', 'category' => [],
            'promotion_use' => 0, 'price_promotion' => 0, 'price_promotion_start' => '', 'price_promotion_end' => '',
            'download_path' => '', 'tags' => '',
        ];
        foreach (self::STRING_FIELDS as $field) {
            $defaults[$field] = '';
        }
        foreach (self::NUMERIC_FIELDS as $field) {
            $defaults[$field] = 0;
        }

        return $defaults;
    }

    /**
     * The stored product as form values.
     *
     * @param ShopProduct $product
     * @return array<string, mixed>
     */
    private function storedForm(ShopProduct $product): array
    {
        $form = [
            'kind' => (int) $product->kind, 'status' => (int) $product->status, 'approve' => (int) $product->approve,
            'sort' => (int) $product->sort, 'date_available' => $product->date_available ? (string) $product->date_available : '',
            'category' => $product->categories()->pluck('category_id')->map(static fn ($id) => (string) $id)->all(),
        ];
        foreach (self::STRING_FIELDS as $field) {
            $form[$field] = (string) ($product->{$field} ?? '');
        }
        foreach (self::NUMERIC_FIELDS as $field) {
            $form[$field] = (float) ($product->{$field} ?? 0);
        }

        return $form;
    }

    /**
     * Map the form to shop_product columns. On edit only the given fields are written.
     *
     * @param array<string, mixed> $form Merged form.
     * @param array<int, string>|null $givenKeys Fields given on edit; null on create (all).
     * @param string|null $configStore
     * @return array<string, mixed>
     */
    private function attributes(array $form, ?array $givenKeys, ?string $configStore): array
    {
        $attributes = [];
        foreach (self::STRING_FIELDS as $field) {
            // WHY: a gated field turned off in config is not on the form; leaving it out
            // preserves the real DB value (lost-update — modification 20260826T093146).
            if (!$this->gatedFieldDisabled($field, $configStore)) {
                $attributes[$field] = (string) ($form[$field] ?? '');
            }
        }
        foreach (self::NUMERIC_FIELDS as $field) {
            if (!$this->gatedFieldDisabled($field, $configStore)) {
                $attributes[$field] = (float) ($form[$field] ?? 0);
            }
        }
        // WHY: when "Use STRUCTURE TYPE" is disabled, ignore the submitted kind and persist SINGLE.
        $attributes['kind'] = self::structureTypeEnabled()
            ? (int) ($form['kind'] ?? 0)
            : (defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0);
        $attributes['sort'] = (int) ($form['sort'] ?? 0);
        $attributes['status'] = empty($form['status']) ? 0 : 1;
        $attributes['approve'] = empty($form['approve']) ? 0 : 1;
        $attributes['date_available'] = !empty($form['date_available']) ? $form['date_available'] : null;

        return $givenKeys === null ? $attributes : array_intersect_key($attributes, array_flip($givenKeys));
    }

    /**
     * Gallery, variants, composition, promotion, download and tags — each written only
     * on create or when its part was given.
     *
     * @param ShopProduct $product
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $given
     * @param array<string, mixed> $form
     * @param bool $creating
     * @param string|null $configStore
     * @return void
     */
    private function writeParts(ShopProduct $product, array $payload, array $given, array $form, bool $creating, ?string $configStore): void
    {
        if (array_key_exists('images', $payload)) {
            $product->images()->delete();
            foreach ((array) $payload['images'] as $image) {
                $image = trim((string) $image);
                if ($image !== '') {
                    $product->images()->create(['image' => gp247_text_plain($image)]);
                }
            }
        }
        if (array_key_exists('variants', $payload)) {
            $this->writeVariants($product, (array) $payload['variants']);
        }
        if (array_key_exists('composition', $payload)) {
            $this->writeComposition($product, (array) $payload['composition']);
        }
        if ($creating || array_key_exists('promotion_use', $given)) {
            $this->writePromotion($product, $form);
        }
        if ($creating || array_key_exists('download_path', $given)) {
            ShopProductDownload::where('product_id', $product->id)->delete();
            $downloadType = defined('GP247_PRODUCT_TYPE_DOWNLOAD') ? GP247_PRODUCT_TYPE_DOWNLOAD : 'download';
            if (($form['product_type'] ?? '') === $downloadType && !empty($form['download_path'])) {
                // WHY: ShopProductDownload declares a composite primaryKey array, so create()
                // breaks; insert() with an explicit uuid bypasses it (legacy parity).
                ShopProductDownload::insert(gp247_text_plain(['id' => gp247_uuid(), 'product_id' => $product->id, 'path' => $form['download_path']]));
            }
        }
        if (($creating || array_key_exists('tags', $given)) && $this->fieldEnabled('product_tags', $configStore)) {
            $product->tags()->sync($this->tagIds((string) ($form['tags'] ?? '')));
        }
    }

    /**
     * @param ShopProduct $product
     * @param array<int, array<string, mixed>> $variants
     * @return void
     */
    private function writeVariants(ShopProduct $product, array $variants): void
    {
        $product->attributes()->delete();
        if ((int) $product->kind !== (int) (defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0)) {
            return;
        }
        foreach ($variants as $variant) {
            $groupId = $variant['attribute_group_id'] ?? '';
            $name = trim((string) ($variant['name'] ?? ''));
            if ($groupId === '' || $name === '') {
                continue;
            }
            $product->attributes()->create(gp247_text_plain([
                'attribute_group_id' => $groupId,
                'name' => $name,
                'add_price' => (float) ($variant['add_price'] ?? 0),
                // WHY: cart options encode a variant as "name__add_price__slug"; fold "_" first
                // so the stored slug can never contain the "__" separator (NFR-SEC-product-attribute-slug).
                'slug' => gp247_word_format_url(str_replace('_', '-', trim((string) ($variant['slug'] ?? '')))),
                'sort' => (int) ($variant['sort'] ?? 0),
            ]));
        }
    }

    /**
     * @param ShopProduct $product
     * @param array<string, mixed> $composition
     * @return void
     */
    private function writeComposition(ShopProduct $product, array $composition): void
    {
        $product->builds()->delete();
        $product->groups()->delete();
        $kind = (int) $product->kind;
        if ($kind === (defined('GP247_PRODUCT_BUILD') ? GP247_PRODUCT_BUILD : 1)) {
            foreach ((array) ($composition['build'] ?? []) as $item) {
                if (($item['product_id'] ?? '') !== '') {
                    $product->builds()->create(gp247_text_plain(['product_id' => $item['product_id'], 'quantity' => (float) ($item['quantity'] ?? 1)]));
                }
            }
        } elseif ($kind === (defined('GP247_PRODUCT_GROUP') ? GP247_PRODUCT_GROUP : 2)) {
            foreach ((array) ($composition['group'] ?? []) as $item) {
                if (($item['product_id'] ?? '') !== '') {
                    $product->groups()->create(gp247_text_plain(['product_id' => $item['product_id']]));
                }
            }
        }
    }

    /**
     * @param ShopProduct $product
     * @param array<string, mixed> $form
     * @return void
     */
    private function writePromotion(ShopProduct $product, array $form): void
    {
        $product->promotionPrice()->delete();
        $applies = in_array((int) $product->kind, [
            (int) (defined('GP247_PRODUCT_SINGLE') ? GP247_PRODUCT_SINGLE : 0),
            (int) (defined('GP247_PRODUCT_BUILD') ? GP247_PRODUCT_BUILD : 1),
        ], true);
        if (!empty($form['promotion_use']) && $applies) {
            $product->promotionPrice()->create(gp247_text_plain([
                'price_promotion' => (float) ($form['price_promotion'] ?? 0),
                'date_start' => !empty($form['price_promotion_start']) ? $form['price_promotion_start'] : null,
                'date_end' => !empty($form['price_promotion_end']) ? $form['price_promotion_end'] : null,
                'status_promotion' => 1,
            ]));
        }
    }

    /**
     * Tag ids for a comma-separated list of names, creating missing tags; names are
     * de-duplicated by their canonical alias, slug-less fragments dropped.
     *
     * @param string $raw
     * @return array<int, int>
     */
    private function tagIds(string $raw): array
    {
        $ids = [];
        foreach (explode(',', $raw) as $fragment) {
            $name = trim($fragment);
            $alias = $name === '' ? '' : ShopProductTag::normalizeAlias($name);
            if ($alias === '') {
                continue;
            }
            // WHY: firstOrCreate keys on the unique alias so repeated names converge on one row.
            $tag = ShopProductTag::firstOrCreate(['alias' => $alias], gp247_text_plain(['name' => $name, 'status' => 1, 'sort' => 0]));
            $ids[$tag->id] = $tag->id;
        }

        return array_values($ids);
    }

    /**
     * @param string $field
     * @param string|null $store
     * @return bool True when $field is gated and its gate is off for $store.
     */
    private function gatedFieldDisabled(string $field, ?string $store): bool
    {
        $configKey = array_search($field, self::GATED_FIELDS, true);

        return $configKey !== false && !$this->fieldEnabled($configKey, $store);
    }

    /**
     * "Use STRUCTURE TYPE" on (or not yet seeded).
     *
     * @return bool
     */
    private static function structureTypeEnabled(): bool
    {
        if (!function_exists('gp247_config')) {
            return true;
        }
        $value = gp247_config('product_kind');

        return $value !== '0' && $value !== 0;
    }

    /**
     * @return iterable<mixed> Active custom-field definitions of products.
     */
    private function customFieldDefs(): iterable
    {
        return function_exists('gp247_custom_field_list') ? gp247_custom_field_list($this->customFieldType()) : [];
    }

    /**
     * @return string Custom-field type of products (the prefixed table name).
     */
    private function customFieldType(): string
    {
        return (new ShopProduct)->getTable();
    }

    /**
     * @param WriteContext $ctx
     * @param array<string, mixed> $options
     * @return int|string|null
     */
    private function referenceStore(WriteContext $ctx, array $options)
    {
        return array_key_exists('reference_store', $options) ? $options['reference_store'] : self::referenceStoreFor($ctx->storeId);
    }

    /**
     * @param WriteContext $ctx
     * @param array<string, mixed> $options
     * @return string|null
     */
    private function configStore(WriteContext $ctx, array $options): ?string
    {
        if (!array_key_exists('config_store', $options)) {
            return $ctx->storeId;
        }

        return $options['config_store'] === null || $options['config_store'] === '' ? null : (string) $options['config_store'];
    }
}
