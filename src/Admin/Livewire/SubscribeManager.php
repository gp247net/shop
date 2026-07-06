<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Shop\Admin\Models\AdminSubscribe;

/**
 * Subscribe (newsletter) manager (shop-admin Unit) — two-panel screen (add/edit
 * form left, list right) on the core ResourcePanel base, matching the legacy
 * AdminSubscribeController (rule ui-tailadmin P1): email + status, scoped to the
 * current admin store. Domain unchanged (FrontSubscribe). Gated by
 * `admin_subscribe`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-004
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class SubscribeManager extends ResourcePanel
{
    protected ?string $permission = 'admin_subscribe';

    /**
     * Current admin store id (falls back to the root store, mirroring the other
     * store-scoped shop managers).
     *
     * @return int|string
     */
    private function storeId()
    {
        return session('adminStoreId', defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : 1);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return AdminSubscribe::query()->where('store_id', $this->storeId());
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['email'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['email', 'status', 'id'];
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::subscribe-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.subscribe.list');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_subscribe.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return ['email' => '', 'status' => 1];
    }

    /**
     * @param AdminSubscribe $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        return [
            'email' => (string) $model->email,
            'status' => (int) $model->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        $attributes = [
            'email' => $data['email'],
            'status' => empty($data['status']) ? 0 : 1,
            'store_id' => $this->storeId(),
        ];

        if ($this->editingId !== null) {
            AdminSubscribe::findOrFail($this->editingId)->update($attributes);
        } else {
            AdminSubscribe::create($attributes);
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
}
