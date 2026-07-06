<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\Models\AdminCountry;
use GP247\Shop\Admin\Livewire\Concerns\HasOrderItems;
use GP247\Shop\Admin\Models\AdminOrder;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopPaymentStatus;
use GP247\Shop\Models\ShopShippingStatus;
use Illuminate\Support\Facades\Route;

/**
 * Order manager (shop-admin Unit, group E, US-SADM-003) on the core ResourcePanel
 * base. The base route is a store-scoped, filterable order LIST; the edit/{id}
 * route is a bespoke order DETAIL (customer info, line items, total breakdown,
 * status history) — the view renders one or the other on $editingId.
 *
 * Everything reuses the legacy backend so the domain is untouched (MC-008, rule
 * ui-tailadmin P1): AdminOrder::getOrderListAdmin/getOrderAdmin for read, the
 * status columns + ShopOrderHistory for the workflow (parity with
 * AdminOrderController::postOrderUpdate), the HasOrderItems trait for line-item
 * editing, and gp247_order_process_after_success + the legacy invoice route for
 * email/print. Gated by `admin_order` (Layer-2 on every mutating action).
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-003
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class OrderManager extends ResourcePanel
{
    use HasOrderItems;

    protected ?string $permission = 'admin_order';

    /** Editable header/status fields surfaced on the detail screen. */
    private const FIELDS = [
        'email', 'first_name', 'last_name', 'phone', 'country', 'postcode',
        'address1', 'comment', 'payment_method', 'shipping_method',
    ];

    /** @var string Order-status filter (list). */
    public string $filterStatus = '';

    /** @var string From-date filter, Y-m-d (list). */
    public string $filterFrom = '';

    /** @var string To-date filter, Y-m-d (list). */
    public string $filterTo = '';

    /** @var array<string, mixed> Read-only snapshot of the editing order (detail view). */
    public array $order = [];

    /** @var array<int, array<string, mixed>> Order total breakdown rows (detail view). */
    public array $totals = [];

    /** @var array<int, array<string, mixed>> Order status-change history (detail view). */
    public array $history = [];

    /**
     * Current admin store id (falls back to the root store).
     *
     * @return int|string
     */
    private function storeId()
    {
        return session('adminStoreId', defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : 1);
    }

    /**
     * Store-scoped order query with the list filters (status + date range)
     * applied when set — parity with AdminOrder::getOrderListAdmin.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $query = AdminOrder::query()->where('store_id', $this->storeId());

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterFrom !== '') {
            $query->where('created_at', '>=', $this->filterFrom . ' 00:00:00');
        }
        if ($this->filterTo !== '') {
            $query->where('created_at', '<=', $this->filterTo . ' 23:59:59');
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['id', 'email', 'first_name', 'last_name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'total', 'status', 'created_at'];
    }

    /**
     * @return array<int, string>
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
        return 'gp247-shop-admin::order-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('order.order_detail');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'gp247.shop-admin.order';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        $defaults = ['status' => 0, 'payment_status' => 1, 'shipping_status' => 1];
        foreach (self::FIELDS as $field) {
            $defaults[$field] = '';
        }

        return $defaults;
    }

    /**
     * Reset the detail sub-state alongside the base form.
     *
     * @return void
     */
    public function resetForm(): void
    {
        parent::resetForm();
        $this->order = [];
        $this->totals = [];
        $this->history = [];
        $this->items = [];
        $this->resetItemForm();
    }

    /**
     * Load an order's header into the form and its items/totals/history into the
     * detail sub-state.
     *
     * @param \GP247\Shop\Models\ShopOrder $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        $this->loadOrderState($model);
        $this->resetItemForm();

        $form = [
            'status' => (int) $model->status,
            'payment_status' => (int) $model->payment_status,
            'shipping_status' => (int) $model->shipping_status,
        ];
        foreach (self::FIELDS as $field) {
            $form[$field] = (string) ($model->{$field} ?? '');
        }

        return $form;
    }

    /**
     * Populate the read-only order snapshot, line items, totals and history.
     *
     * @param \GP247\Shop\Models\ShopOrder $model
     * @return void
     */
    private function loadOrderState($model): void
    {
        $this->order = [
            'id' => (string) $model->id,
            'email' => (string) $model->email,
            'name' => trim($model->first_name . ' ' . $model->last_name),
            'phone' => (string) $model->phone,
            'address' => trim($model->address1 . ' ' . $model->address2 . ' ' . $model->address3),
            'country' => (string) $model->country,
            'currency' => (string) $model->currency,
            'subtotal' => (float) $model->subtotal,
            'tax' => (float) $model->tax,
            'shipping' => (float) $model->shipping,
            'discount' => (float) $model->discount,
            'other_fee' => (float) $model->other_fee,
            'total' => (float) $model->total,
            'received' => (float) $model->received,
            'balance' => (float) $model->balance,
            'payment_method' => (string) $model->payment_method,
            'shipping_method' => (string) $model->shipping_method,
            'created_at' => (string) $model->created_at,
        ];

        $this->refreshItems();
        $this->totals = AdminOrder::getOrderTotal($model->id);
        $this->history = $model->history()->orderBy('add_date', 'desc')->get()->toArray();
    }

    /**
     * The current editing order (store-scoped), or null.
     *
     * @return \GP247\Shop\Models\ShopOrder|null
     */
    private function currentOrder()
    {
        if ($this->editingId === null) {
            return null;
        }

        return AdminOrder::getOrderAdmin($this->editingId, $this->storeId());
    }

    /**
     * Reload the detail sub-state after a mutation.
     *
     * @return void
     */
    private function refreshOrder(): void
    {
        $model = $this->currentOrder();
        if ($model === null) {
            return;
        }

        $this->loadOrderState($model);
        $this->form['status'] = (int) $model->status;
        $this->form['payment_status'] = (int) $model->payment_status;
        $this->form['shipping_status'] = (int) $model->shipping_status;
    }

    // --- Status workflow (parity with AdminOrderController::postOrderUpdate) ---

    /**
     * Change the order status (+ the optional success-finish/unfinish hooks).
     *
     * @param int|string $value New order-status id.
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     */
    public function changeOrderStatus($value): void
    {
        $this->authorizeAction('update');
        $this->applyStatusChange('status', (int) $value);
    }

    /**
     * Change the payment status.
     *
     * @param int|string $value New payment-status id.
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     */
    public function changePaymentStatus($value): void
    {
        $this->authorizeAction('update');
        $this->applyStatusChange('payment_status', (int) $value);
    }

    /**
     * Change the shipping status.
     *
     * @param int|string $value New shipping-status id.
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     */
    public function changeShippingStatus($value): void
    {
        $this->authorizeAction('update');
        $this->applyStatusChange('shipping_status', (int) $value);
    }

    /**
     * Persist a status-column change, fire the success hooks on the order-status
     * transition to/from "done" (5), log the change and refresh.
     *
     * @param string $column One of status|payment_status|shipping_status.
     * @param int    $value  New status id.
     * @return void
     */
    private function applyStatusChange(string $column, int $value): void
    {
        $order = $this->currentOrder();
        if ($order === null) {
            return;
        }

        $old = (int) $order->{$column};
        if ($old === $value) {
            return;
        }

        $order->update([$column => $value]);

        if ($column === 'status') {
            // WHY: legacy fires optional template/plugin hooks on the done(5)
            // transition; call them only when defined (function_exists guard).
            if ($old !== 5 && $value === 5 && function_exists('gp247_order_success_finish')) {
                gp247_order_success_finish($this->editingId);
            }
            if ($old === 5 && $value !== 5 && function_exists('gp247_order_success_unfinish')) {
                gp247_order_success_unfinish($this->editingId);
            }
        }

        $content = 'Change <b>' . $column . "</b> from '" . $old . "' to '" . $value . "'";
        $this->logHistory($content, (int) $order->fresh()->status);

        $this->refreshOrder();
        $this->notify('success', gp247_language_render('action.update_success'));
    }

    /**
     * Append an order-history audit row (admin id from the legacy admin guard).
     *
     * @param string $content     Change description (may contain markup).
     * @param int    $orderStatus Order-status snapshot at the time of the change.
     * @return void
     */
    private function logHistory(string $content, int $orderStatus): void
    {
        (new AdminOrder)->addOrderHistory([
            'order_id' => $this->editingId,
            'content' => $content,
            'admin_id' => $this->adminId(),
            'order_status_id' => $orderStatus,
        ]);
    }

    /**
     * Current admin user id (0 when unavailable, e.g. in tests).
     *
     * @return int|string
     */
    private function adminId()
    {
        if (function_exists('admin') && admin()->user()) {
            return admin()->user()->id;
        }

        return 0;
    }

    // --- Invoice / email (reuse existing helpers) --------------------------

    /**
     * Re-run the order-success email side effect (config-gated helper; no-op when
     * email is disabled). Reused verbatim from the shop helper layer.
     *
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     */
    public function resendEmail(): void
    {
        $this->authorizeAction('update');
        if ($this->editingId !== null && function_exists('gp247_order_process_after_success')) {
            gp247_order_process_after_success($this->editingId);
        }
        $this->notify('success', gp247_language_render('action.update_success'));
    }

    /**
     * Printable-invoice URL — reuses the legacy invoice route (no new view).
     *
     * @return string
     */
    public function invoiceUrl(): string
    {
        if ($this->editingId !== null && Route::has('admin_order.invoice')) {
            // WHY: AdminOrderController::invoice() reads request('order_id'), not 'id'.
            return route('admin_order.invoice', ['order_id' => $this->editingId]);
        }

        return '#';
    }

    // --- ResourcePanel contract (order create/update is action-based) -------

    /**
     * Validation rules — none for the generic form; status/items use actions.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * No-op: the order header is not saved via the generic form (status and line
     * items are mutated through dedicated, individually-logged actions).
     *
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        // No-op — see changeOrderStatus()/saveItem().
    }

    /**
     * Delete an order (ShopOrder::boot cascades details/totals/history + stock).
     *
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

    // --- View option helpers ------------------------------------------------

    /**
     * @return array<int|string, string> Order-status options (id => name).
     */
    public function orderStatusOptions(): array
    {
        return (array) ShopOrderStatus::getIdAll();
    }

    /**
     * @return array<int|string, string> Payment-status options (id => name).
     */
    public function paymentStatusOptions(): array
    {
        return (array) ShopPaymentStatus::getIdAll();
    }

    /**
     * @return array<int|string, string> Shipping-status options (id => name).
     */
    public function shippingStatusOptions(): array
    {
        return (array) ShopShippingStatus::getIdAll();
    }

    /**
     * Country options (code => name) for the read-only address display.
     *
     * @return array<string, string>
     */
    public function countryOptions(): array
    {
        return (array) (new AdminCountry())->getCodeAll();
    }

    /**
     * Map a legacy order-status style to a <x-gp247::badge> colour.
     *
     * @param int|string $statusId Order-status id.
     * @return string Badge colour token.
     */
    public function statusBadgeColor($statusId): string
    {
        $map = [
            '1' => 'blue',   // new (info)
            '2' => 'blue',   // processing (primary)
            '3' => 'amber',  // hold (warning)
            '4' => 'red',    // canceled (danger)
            '5' => 'green',  // done (success)
            '6' => 'gray',   // failed (default)
        ];

        return $map[(string) $statusId] ?? 'gray';
    }
}
