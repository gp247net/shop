<?php

namespace GP247\Shop\Services\Catalog;

use GP247\Shop\Models\ShopBrand;

/**
 * Creates and edits a brand with the rules of the admin Brand screen. Form fields: image,
 * name, alias (derived from the name when empty), url, sort, status.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-catalog-write-service
 * @aidlc-adr shop-admin_catalog-write-service
 */
class BrandWriter extends NamedRecordWriter
{
    /**
     * @return class-string<ShopBrand>
     */
    protected function modelClass(): string
    {
        return ShopBrand::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function columns(): array
    {
        return ['image' => '', 'name' => '', 'alias' => '', 'url' => '', 'sort' => 0, 'status' => 1];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldRules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:100'],
            'form.image' => ['required', 'string'],
            'form.url' => ['nullable', 'url', 'max:255'],
            'form.sort' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function labelKeys(): array
    {
        return [
            'form.name' => 'admin.brand.name',
            'form.alias' => 'admin.brand.alias',
            'form.image' => 'admin.brand.image',
            'form.url' => 'admin.brand.url',
            'form.sort' => 'admin.sort',
        ];
    }
}
