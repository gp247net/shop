<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
use GP247\Shop\Models\ShopCurrency;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Currency manager (shop-admin Unit) — two-panel screen (form left, list right)
 * on the shared core ResourcePanel base, matching the legacy
 * AdminCurrencyController layout (rule ui-tailadmin P1). Name, code (unique),
 * symbol, exchange rate, precision, symbol-first, thousands, status, sort. Domain
 * unchanged (ShopCurrency). Gated by `admin_currency`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-005
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class CurrencyManager extends ResourcePanel
{
    use HasValidationLabels;

    protected ?string $permission = 'admin_currency';

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
     * Code of the currency the admin wants to promote to base (rebase modal).
     *
     * @var string
     */
    public string $rebaseTarget = '';

    /**
     * New exchange_rate to give the OUTGOING base once it loses base status.
     * Pre-filled with the value-preserving suggestion (1 / target's current rate)
     * when a target is picked; the admin may override it deliberately.
     *
     * @var string
     */
    public string $rebaseOldRate = '';

    /**
     * Whether the admin has ticked "I understand the impact" — gates the submit.
     *
     * @var bool
     */
    public bool $rebaseConfirmed = false;

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        return ShopCurrency::query();
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'code'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'code', 'exchange_rate', 'sort', 'status'];
    }

    /**
     * @return string
     */
    protected function panelView(): string
    {
        return 'gp247-shop-admin::currency-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.currency.title');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_currency.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        return [
            'name' => '', 'code' => '', 'symbol' => '', 'exchange_rate' => 1,
            'precision' => 2, 'symbol_first' => 0, 'thousands' => ',', 'status' => 1, 'sort' => 0,
        ];
    }

    /**
     * @param ShopCurrency $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        return [
            'name' => (string) $model->name,
            'code' => (string) $model->code,
            'symbol' => (string) $model->symbol,
            'exchange_rate' => (float) $model->exchange_rate,
            'precision' => (int) $model->precision,
            'symbol_first' => (int) $model->symbol_first,
            'thousands' => (string) $model->thousands,
            'status' => (int) $model->status,
            'sort' => (int) $model->sort,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $table = (new ShopCurrency())->getTable();

        return [
            'form.name' => ['required', 'string', 'max:100'],
            'form.code' => ['required', 'string', 'max:10', Rule::unique($table, 'code')->ignore($this->editingId)],
            'form.symbol' => ['required', 'string', 'max:20'],
            'form.exchange_rate' => ['required', 'numeric', 'gt:0'],
            'form.precision' => ['required', 'integer', 'min:0', 'max:8'],
            'form.symbol_first' => ['required', 'in:0,1'],
            'form.thousands' => ['required', 'string', 'max:2'],
            'form.sort' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Reuse the existing v1 currency label keys.
     *
     * @return array<string, string>
     */
    protected function attributeLabels(): array
    {
        return [
            'form.name' => 'admin.currency.name',
            'form.code' => 'admin.currency.code',
            'form.symbol' => 'admin.currency.symbol',
            'form.exchange_rate' => 'admin.currency.exchange_rate',
            'form.precision' => 'admin.currency.precision',
            'form.symbol_first' => 'admin.currency.symbol_first',
            'form.thousands' => 'admin.currency.thousands',
            'form.sort' => 'admin.sort',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        $attributes = [
            'name' => $data['name'],
            'code' => $data['code'],
            'symbol' => $data['symbol'],
            'exchange_rate' => $data['exchange_rate'],
            'precision' => (int) $data['precision'],
            'symbol_first' => (int) $data['symbol_first'],
            'thousands' => $data['thousands'],
            'status' => empty($data['status']) ? 0 : 1,
            'sort' => (int) ($data['sort'] ?? 0),
        ];

        if ($this->editingId !== null) {
            ShopCurrency::findOrFail($this->editingId)->update($attributes);
        } else {
            ShopCurrency::create($attributes);
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
     * Delete a currency, first enforcing the deletion invariant and surfacing a
     * concrete reason as an error toast instead of the base success feedback.
     *
     * WHY override delete() rather than deleteModel(): the base ResourcePanel
     * unconditionally shows a success notice after deleteModel() returns, so a
     * silently-skipped delete would still look successful. Intercepting here
     * lets a blocked delete report why and skip the success path entirely; the
     * model boot guard remains the defense-in-depth backstop.
     *
     * @param int|string $id
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-currency-delete-guard
     * @aidlc-adr ADR-007
     */
    public function delete($id): void
    {
        $this->authorizeAction('delete');

        $model = $this->baseQuery()->find($id);
        if ($model !== null && ($reason = $model->deleteBlockReason()) !== null) {
            $this->notify('error', gp247_language_render('admin.currency.delete_blocked_' . $reason));

            return;
        }

        parent::delete($id);
    }

    /**
     * Load a record into the edit form, but never the base currency: the base is
     * locked from editing (its rate/status are invariant and it is changed only
     * via the rebase flow). Mirrors the hidden Edit control in the list; the model
     * saving() guard is the deeper backstop.
     *
     * @param int|string $id
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-CMP-base-currency-explicit
     * @aidlc-adr currency-base-system-scope
     */
    public function editRow($id): void
    {
        $model = $this->baseQuery()->find($id);
        if ($model !== null && (bool) $model->is_base === true) {
            $this->notify('error', gp247_language_render('admin.currency.base_locked'));

            return;
        }

        parent::editRow($id);
    }

    /**
     * Persist the form, but refuse to save changes to the base currency even when
     * an edit form was reached directly by URL — the base is edit-locked (only the
     * rebase flow may change it). Non-base saves proceed unchanged.
     *
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-CMP-base-currency-explicit
     * @aidlc-adr currency-base-system-scope
     */
    public function save(): void
    {
        if ($this->editingId !== null) {
            $model = $this->baseQuery()->find($this->editingId);
            if ($model !== null && (bool) $model->is_base === true) {
                $this->notify('error', gp247_language_render('admin.currency.base_locked'));

                return;
            }
        }

        parent::save();
    }

    /**
     * When the rebase target changes, pre-fill the outgoing base's new rate with
     * the value-preserving suggestion: 1 / (target's current rate). Because the
     * current base has rate 1, this equals oldRate(OLD)/oldRate(NEW) and keeps
     * every displayed price unchanged when accepted (ADR currency-rebase-value-preserving).
     *
     * @param string $value The newly selected target currency code.
     * @return void
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-currency-rebase-ui
     * @aidlc-adr currency-rebase-value-preserving
     */
    public function updatedRebaseTarget($value): void
    {
        $this->rebaseConfirmed = false;

        $target = ShopCurrency::where('code', $value)->where('is_base', 0)->first();
        if ($target === null || (float) $target->exchange_rate <= 0) {
            $this->rebaseOldRate = '';

            return;
        }

        // Round to the exchange_rate column scale (6) so the suggestion is a value
        // the column can store exactly, then render it as a PLAIN decimal string:
        // (string) on a small float yields scientific notation ("4.0E-5" for a VND
        // target), which reads wrong to the admin and is fragile in a number input.
        // sprintf('%.6F', ...) forces fixed-point, and the rtrim drops the padding
        // zeros so "0.000040" shows as "0.00004".
        $suggested = round(1 / (float) $target->exchange_rate, 6);
        $this->rebaseOldRate = rtrim(rtrim(sprintf('%.6F', $suggested), '0'), '.');
    }

    /**
     * Change the base currency (value-preserving), driven by the rebase modal.
     *
     * Validates the target and the outgoing base's new rate, requires the explicit
     * "I understand" confirmation, then delegates the atomic rescale to
     * ShopCurrency::rebase(). A domain validation error is surfaced as a toast
     * without leaving the screen; success flashes and reloads so the list, form
     * and currency hints all read the new base.
     *
     * @return void
     * @throws \Illuminate\Validation\ValidationException When the modal input is invalid.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-currency-rebase-ui
     * @aidlc-adr currency-rebase-value-preserving
     */
    public function rebase(): void
    {
        $this->authorizeAction('update');

        $table = (new ShopCurrency())->getTable();
        $validated = $this->validate(
            [
                // Target must be an existing, active, non-base currency.
                'rebaseTarget' => ['required', 'string', Rule::exists($table, 'code')->where('status', 1)->where('is_base', 0)],
                // The outgoing base must be given a real rate (> 0 and != 1).
                'rebaseOldRate' => ['required', 'numeric', 'gt:0', 'not_in:1'],
                'rebaseConfirmed' => ['accepted'],
            ],
            [],
            [
                'rebaseTarget' => gp247_language_render('admin.currency.rebase_target'),
                'rebaseOldRate' => gp247_language_render('admin.currency.rebase_old_rate'),
                'rebaseConfirmed' => gp247_language_render('admin.currency.rebase_confirm'),
            ]
        );

        try {
            ShopCurrency::rebase($validated['rebaseTarget'], (float) $validated['rebaseOldRate']);
        } catch (\InvalidArgumentException $e) {
            // WHY log: the message is a developer-facing invariant breach; the admin
            // only needs a generic failure notice, not the internal reason.
            Log::warning('[GP247 currency] rebase rejected: ' . $e->getMessage());
            $this->notify('error', gp247_language_render('admin.currency.rebase_failed'));

            return;
        }

        session()->flash('gp247_admin_success', gp247_language_render('admin.currency.rebase_success'));
        $this->redirect(route($this->baseRoute()), navigate: true);
    }

    /**
     * Inject the current base currency and the eligible rebase targets (active,
     * non-base) into the panel view for the "Change base" modal.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return parent::render()->with([
            'baseCurrency' => ShopCurrency::where('is_base', 1)->first(),
            'rebaseCandidates' => ShopCurrency::where('is_base', 0)->where('status', 1)->sort()->get(),
        ]);
    }
}
