<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
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
    use HasValidationLabels;

    protected ?string $permission = 'admin_subscribe';

    /**
     * Keep list state (page/keyword/sort) and the edited record on screen when
     * editing/saving, instead of remounting via route navigation.
     *
     * @var bool
     * @aidlc-story US-AUI-two-panel-state-preservation
     * @aidlc-adr ADR-admin-shell-rbac-two-panel-state-preservation
     */
    protected bool $keepStateOnSave = true;

    /**
     * Opt into the ResourcePanel store-scope UI. A subscriber belongs to the single
     * store where they subscribed on the storefront (1-1). Exposes the store picker
     * on create-at-root and a per-row store label; no dependent field, so `reset`
     * is empty. Active only when a multi-store/multi-vendor plugin is installed.
     *
     * @return array<string, mixed>|null
     * @aidlc-story US-SADM-store-content-assignment
     * @aidlc-adr admin-shell_store-scoped-resource-panel
     */
    protected function storeScoped(): ?array
    {
        return ['display' => 'email', 'reset' => []];
    }

    /**
     * Store-scoped subscribe query: root admin sees every store's subscribers (each
     * row labelled by its store); a scoped context (store-admin) or a single-store
     * install filters to the own store.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $query = AdminSubscribe::query();
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
        // Store is immutable on edit — expose it for the read-only display.
        $this->formStoreId = (string) $model->store_id;

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
     * Reuse the existing v1 subscribe label key.
     *
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return [
            'form.email' => 'admin.subscribe.email',
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
        ];

        if ($this->editingId !== null) {
            // Store is immutable on edit — do NOT touch store_id (ADR 1-1).
            AdminSubscribe::findOrFail($this->editingId)->update($attributes);
        } else {
            // Owned by the store picked on create (root) / the scoped store.
            $attributes['store_id'] = $this->resolveCreateStore();
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
