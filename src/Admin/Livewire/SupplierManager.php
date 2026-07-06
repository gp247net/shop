<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Shop\Models\ShopSupplier;
use Illuminate\Validation\Rule;

/**
 * Supplier manager (shop-admin Unit) — two-panel screen (form left, list right)
 * on the shared core ResourcePanel base, matching the legacy
 * AdminSupplierController layout (rule ui-tailadmin P1). Image (LFM), name, alias
 * (auto, unique), url, email, phone, address, sort; store_id follows the active
 * admin store. Domain unchanged (ShopSupplier). Gated by `admin_supplier`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-002
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class SupplierManager extends ResourcePanel
{
    protected ?string $permission = 'admin_supplier';

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return ShopSupplier::query();
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'email'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'email', 'sort'];
    }

    /**
     * @return array{0: string, 1: string}
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
        return 'gp247-shop-admin::supplier-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.supplier.title');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_supplier.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return ['image' => '', 'name' => '', 'alias' => '', 'url' => '', 'email' => '', 'phone' => '', 'address' => '', 'sort' => 0];
    }

    /**
     * @param ShopSupplier $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        return [
            'image' => (string) $model->image,
            'name' => (string) $model->name,
            'alias' => (string) $model->alias,
            'url' => (string) $model->url,
            'email' => (string) $model->email,
            'phone' => (string) $model->phone,
            'address' => (string) $model->address,
            'sort' => (int) $model->sort,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $table = (new ShopSupplier())->getTable();

        return [
            'form.name' => ['required', 'string', 'max:100'],
            'form.alias' => ['required', 'string', 'max:100', Rule::unique($table, 'alias')->ignore($this->editingId)],
            'form.image' => ['required', 'string'],
            'form.url' => ['nullable', 'url', 'max:255'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.sort' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        $attributes = [
            'image' => $data['image'] ?? '',
            'name' => $data['name'],
            'alias' => $data['alias'],
            'url' => $data['url'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'address' => $data['address'] ?? '',
            'sort' => (int) ($data['sort'] ?? 0),
        ];

        if ($this->editingId !== null) {
            ShopSupplier::findOrFail($this->editingId)->update($attributes);
        } else {
            $attributes['store_id'] = session('adminStoreId', defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : 1);
            ShopSupplier::create($attributes);
        }
    }

    /**
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        $model = $this->baseQuery()->find($id);
        if ($model !== null) {
            $model->delete();
        }
    }

    /**
     * Derive alias from name (brownfield parity) before the secure save pipeline.
     *
     * @return void
     */
    public function save(): void
    {
        if (empty($this->form['alias'])) {
            $this->form['alias'] = $this->form['name'] ?? '';
        }
        $this->form['alias'] = gp247_word_limit(gp247_word_format_url((string) $this->form['alias']), 100);

        parent::save();
    }
}
