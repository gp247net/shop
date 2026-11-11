<?php

namespace GP247\Shop\Services\Catalog;

use GP247\Shop\Models\ShopSupplier;

/**
 * Creates and edits a supplier with the rules of the admin Supplier screen. Form fields:
 * image, name, alias (derived from the name when empty), url, email, phone, address, sort.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-catalog-write-service
 * @aidlc-adr shop-admin_catalog-write-service
 */
class SupplierWriter extends NamedRecordWriter
{
    /**
     * @return class-string<ShopSupplier>
     */
    protected function modelClass(): string
    {
        return ShopSupplier::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function columns(): array
    {
        return ['image' => '', 'name' => '', 'alias' => '', 'url' => '', 'email' => '', 'phone' => '', 'address' => '', 'sort' => 0];
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
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.sort' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function labelKeys(): array
    {
        return [
            'form.name' => 'admin.supplier.name',
            'form.alias' => 'admin.supplier.alias',
            'form.image' => 'admin.supplier.image',
            'form.url' => 'admin.supplier.url',
            'form.email' => 'admin.supplier.email',
            'form.sort' => 'admin.sort',
        ];
    }
}
