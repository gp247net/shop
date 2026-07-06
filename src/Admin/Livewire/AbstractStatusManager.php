<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared base for the three shop status managers (order / payment / shipping
 * status) — two-panel screen (add/edit form left, list right) on the core
 * ResourcePanel base (rule ui-tailadmin P1/P3): each status row is just a name,
 * and a fixed set of built-in ids is protected from deletion (mirroring the
 * legacy `statusProtected()` guard). Concrete managers supply the model, the
 * protected-id map, the route and title. Domain unchanged.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-003
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
abstract class AbstractStatusManager extends ResourcePanel
{
    /**
     * @return class-string<Model> The status model class.
     */
    abstract protected function statusModelClass(): string;

    /**
     * Built-in status ids that must not be deleted, mapped to a readable label
     * (mirrors the legacy controller's statusProtected()).
     *
     * @return array<int|string, string>
     */
    abstract protected function protectedMap(): array;

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $class = $this->statusModelClass();

        return $class::query();
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'name'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return ['name' => ''];
    }

    /**
     * @param Model $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        return ['name' => (string) $model->name];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        $class = $this->statusModelClass();

        if ($this->editingId !== null) {
            $class::findOrFail($this->editingId)->update(['name' => $data['name']]);
        } else {
            $class::create(['name' => $data['name']]);
        }
    }

    /**
     * Delete a status row unless it is a protected built-in (then warn + skip).
     *
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        if (array_key_exists((string) $id, $this->normalisedProtectedMap())) {
            $this->notify('warning', gp247_language_render('admin.shop_status.protected'));

            return;
        }

        $this->baseQuery()->find($id)?->delete();
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::status-manager';
    }

    /**
     * Protected-id map keyed by string id (for safe array lookups).
     *
     * @return array<string, string>
     */
    private function normalisedProtectedMap(): array
    {
        $map = [];
        foreach ($this->protectedMap() as $id => $label) {
            $map[(string) $id] = (string) $label;
        }

        return $map;
    }

    /**
     * Protected built-in ids (exposed to the view to hide the delete action).
     *
     * @return array<int, string>
     */
    public function protectedIds(): array
    {
        return array_keys($this->normalisedProtectedMap());
    }

    /**
     * Base route name (exposed to the shared view).
     *
     * @return string
     */
    public function routeBaseName(): string
    {
        return $this->baseRoute();
    }

    /**
     * The edit route name derived from baseRoute().
     * Strips a trailing ".index" suffix so "admin_x.index" → "admin_x.edit".
     *
     * @return string
     */
    public function routeEditName(): string
    {
        return preg_replace('/\.index$/', '', $this->baseRoute()) . '.edit';
    }

    /**
     * Screen title (exposed to the shared view).
     *
     * @return string
     */
    public function titleText(): string
    {
        return $this->pageTitle();
    }
}
