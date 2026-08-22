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
        'email', 'first_name', 'last_name', 'phone', 'company', 'country',
        'postcode', 'city', 'district', 'address1', 'address2', 'address3',
        'comment', 'payment_method', 'shipping_method',
    ];

    /**
     * Header columns saveOrderInfo() may write — email is deliberately absent
     * (read-only, parity with the legacy screen), and the whitelist is fixed so
     * no extra form key can ever reach the UPDATE (US-SADM-order-info-edit).
     * The full address block (city/district/address1-3/postcode) is editable so
     * an admin can correct any part of the shipping snapshot (QĐ-6).
     */
    private const HEADER_FIELDS = [
        'first_name', 'last_name', 'phone', 'company', 'city', 'district',
        'address1', 'address2', 'address3', 'country', 'postcode',
        'comment', 'payment_method', 'shipping_method',
    ];

    /**
     * shop_order_total codes editable via updateTotalRow() — the derived rows
     * (subtotal/tax/total) are recomputed from line items, never set directly.
     */
    private const EDITABLE_TOTAL_CODES = ['shipping', 'discount', 'other_fee', 'received'];

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
        // WHY: one ResourcePanel serves both faces; branch on $editingId so the
        // LIST shows "order list" instead of the detail title (fixed mislabel).
        return $this->editingId
            ? gp247_language_render('order.order_detail')
            : gp247_language_render('admin.order.list');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        // WHY: use the cutover (PA-1) clean route space `admin_order.*` at
        // `/order/...` as canonical — consistent with the admin menu and the
        // invoice link (`admin_order.invoice`). The ResourcePanel only uses this
        // to redirect back to the list after save/delete; RBAC is driven by the
        // separate `$permission = 'admin_order'`, so this does not affect access.
        return 'admin_order.index';
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
            // Canonical address order: city -> district -> address1/2/3.
            // array_filter drops empty parts so an order stored before city/district
            // existed still renders exactly as before.
            'address' => implode(' ', array_filter([
                (string) ($model->city ?? ''), (string) ($model->district ?? ''),
                (string) $model->address1, (string) $model->address2, (string) $model->address3,
            ])),
            'city' => (string) ($model->city ?? ''),
            'district' => (string) ($model->district ?? ''),
            'address1' => (string) $model->address1,
            'address2' => (string) $model->address2,
            'address3' => (string) $model->address3,
            'postcode' => (string) $model->postcode,
            'country' => (string) $model->country,
            'currency' => (string) $model->currency,
            // Exchange rate snapshotted at checkout, surfaced read-only on the
            // order-detail totals card so the admin sees the order's currency unit
            // and the rate used. US-SADM-order-currency-display.
            'exchange_rate' => (float) $model->exchange_rate,
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

    // --- Header / totals editing (US-SADM-order-info-edit) ------------------

    /**
     * Persist the editable order-header fields (customer/shipping info, note,
     * payment/shipping method) from the form, logging one history row per
     * changed field — parity with the legacy postOrderUpdate inline edits.
     * Email is read-only by design and never written.
     *
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     * @throws \Illuminate\Validation\ValidationException When a field is invalid.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-info-edit
     */
    public function saveOrderInfo(): void
    {
        $this->authorizeAction('update');

        $order = $this->currentOrder();
        if ($order === null) {
            return;
        }

        // WHY: required set is first_name/phone/address1/country only — the
        // legacy screen required all six inline fields, but a whole-form save
        // must not force values into company/last_name that are legitimately
        // empty on existing orders (deliberate deviation, see the ADR).
        //
        // The detail form now hides a customer field when its admin config
        // toggle is off (parity with the create-order screen, order-detail.blade.php).
        // A field the UI hides must not stay hard-required here, or an order
        // placed under a leaner config could never be saved. So phone/address1/
        // country keep their required rule only while their toggle shows them,
        // and drop to a lenient nullable rule when hidden — mirroring how
        // AdminOrderController::postCreate() gates the same fields by config.
        $this->validate([
            'form.first_name' => 'required|string|max:100',
            'form.last_name' => 'nullable|string|max:100',
            'form.phone' => gp247_config_admin('customer_phone') ? 'required|string|max:20' : 'nullable|string|max:20',
            'form.company' => 'nullable|string|max:100',
            // City/district are nullable on a whole-form save: an order placed
            // before these were enabled must not be forced to backfill them.
            'form.city' => 'nullable|string|max:100',
            'form.district' => 'nullable|string|max:100',
            'form.address1' => gp247_config_admin('customer_address1') ? 'required|string|max:100' : 'nullable|string|max:100',
            // address2/3 and postcode are additional, always-optional parts of
            // the snapshot the admin may correct (QĐ-6).
            'form.address2' => 'nullable|string|max:100',
            'form.address3' => 'nullable|string|max:100',
            'form.postcode' => 'nullable|string|max:20',
            'form.country' => gp247_config_admin('customer_country') ? 'required|string|max:10' : 'nullable|string|max:10',
            'form.comment' => 'nullable|string|max:300',
            'form.payment_method' => 'nullable|string|max:100',
            'form.shipping_method' => 'nullable|string|max:100',
        ]);

        $clean = gp247_clean($this->form);

        $changes = [];
        foreach (self::HEADER_FIELDS as $field) {
            $new = (string) ($clean[$field] ?? '');
            if ($new !== (string) ($order->{$field} ?? '')) {
                $changes[$field] = $new;
            }
        }

        if ($changes === []) {
            $this->notify('success', gp247_language_render('action.update_success'));

            return;
        }

        $old = $order->only(array_keys($changes));
        $order->update($changes);

        $status = (int) $order->fresh()->status;
        foreach ($changes as $field => $new) {
            // WHY: history renders raw HTML — escape the admin-supplied values.
            $content = 'Change <b>' . $field . "</b> from '" . e((string) ($old[$field] ?? ''))
                . "' to '" . e($new) . "'";
            $this->logHistory($content, $status);
        }

        $this->refreshOrder();
        $this->notify('success', gp247_language_render('action.update_success'));
    }

    /**
     * Map each editable order-header field to its localized label, REUSING the
     * existing customer.* and order.* language keys already shown on the form — no
     * new keys are invented. Used to name fields in validation messages so the
     * user sees e.g. "Address" instead of the raw "form.address1" path.
     *
     * @return array<string, string>
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-info-edit
     */
    private function attributeLabels(): array
    {
        return [
            'form.first_name' => $this->label('customer.first_name'),
            'form.last_name' => $this->label('customer.last_name'),
            'form.phone' => $this->label('customer.phone'),
            'form.company' => $this->label('customer.company'),
            'form.city' => $this->label('customer.city'),
            'form.district' => $this->label('customer.district'),
            'form.address1' => $this->label('customer.address1'),
            'form.address2' => $this->label('customer.address2'),
            'form.address3' => $this->label('customer.address3'),
            'form.postcode' => $this->label('customer.postcode'),
            'form.country' => $this->label('customer.country'),
            'form.comment' => $this->label('order.note'),
            'form.payment_method' => $this->label('order.payment_method'),
            'form.shipping_method' => $this->label('order.shipping_method'),
        ];
    }

    /**
     * Render an order field label as PLAIN TEXT for use as a validator attribute.
     *
     * WHY: some language strings embed presentational markup for the form label,
     * which would leak as raw HTML into the validation message. Strip tags/entities
     * so the message reads the clean field name only. The keys are unchanged (existing i18n).
     *
     * @param string $key Language key (e.g. 'customer.address1').
     * @return string Tag-free, trimmed label.
     */
    private function label(string $key): string
    {
        $rendered = (string) gp247_language_render($key);

        return trim(strip_tags(html_entity_decode($rendered, ENT_QUOTES)));
    }

    /**
     * Friendly attribute names for every rule (Livewire hook). Makes messages of
     * rules we do not override in messages() (max/string/…) show the localized
     * field name instead of the raw "form.*" path.
     *
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return $this->attributeLabels();
    }

    /**
     * Localized validator messages, built from the shared validation.* language keys
     * (parity with ProductManager). Every rule used by saveOrderInfo() is routed
     * through gp247_language_render so it reads the DB translation (falling back to
     * the framework message) — otherwise non-required rules would bypass the DB and
     * always render in English. Rule-specific placeholders (:max) are left for the
     * framework's replacers to fill after :attribute is substituted here.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        // Rules referenced by saveOrderInfo() that carry a user-facing message.
        $rules = ['required', 'string', 'max'];
        $messages = [];
        foreach ($this->attributeLabels() as $field => $label) {
            foreach ($rules as $rule) {
                $messages[$field . '.' . $rule] = gp247_language_render('validation.' . $rule, ['attribute' => $label]);
            }
        }

        return $messages;
    }

    /**
     * Update one editable shop_order_total row (shipping/discount/other_fee/
     * received) and re-sum the order total/balance via the legacy domain method.
     * The row must belong to the order being edited — the legacy postOrderUpdate
     * accepted any pk (IDOR), this action scopes it. Raw signed values are kept
     * (discount/received are stored negative, "(-)" hint in the UI — D2).
     *
     * @param int|string $rowId shop_order_total row id.
     * @param mixed      $value New raw value (numeric, may be negative).
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-order-info-edit
     */
    public function updateTotalRow($rowId, $value): void
    {
        $this->authorizeAction('update');

        if ($this->editingId === null) {
            return;
        }

        $row = AdminOrder::getRowOrderTotal($rowId);
        if ($row === null
            || (string) $row->order_id !== (string) $this->editingId
            || !in_array((string) $row->code, self::EDITABLE_TOTAL_CODES, true)
        ) {
            $this->notify('error', gp247_language_render('admin.display.data_not_found_detail', ['msg' => '#' . $rowId]));

            return;
        }

        if (!is_numeric($value)) {
            $this->notify('error', gp247_language_render('validation.numeric', [
                'attribute' => gp247_language_render('order.totals.' . $row->code),
            ]));

            return;
        }

        $old = (float) $row->value;
        $new = (float) $value;
        if ($old === $new) {
            return;
        }

        $order = $this->currentOrder();
        AdminOrder::updateRowOrderTotal([
            'id' => $row->id,
            'code' => $row->code,
            'value' => $new,
            'text' => gp247_currency_render_symbol($new, (string) ($order->currency ?? '')),
        ]);

        $this->logHistory(
            'Change <b>' . $row->code . "</b> from '" . $old . "' to '" . $new . "'",
            (int) ($order->status ?? 0),
        );

        $this->refreshOrder();
        $this->notify('success', gp247_language_render('action.update_success'));
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

        if ($this->editingId === null) {
            return;
        }

        // WHY: mirror the guard inside gp247_order_process_after_success() so the
        // toast tells the truth. That helper silently sends nothing when the master
        // mail switch is off or no order-success recipient is enabled, yet this
        // action previously always reported success — misleading the admin (see the
        // "Send mail" tab in Shop configuration).
        if (!gp247_config('email_action_mode')) {
            $this->notify('warning', gp247_language_render('admin.shop.order_email_off'));

            return;
        }
        if (!gp247_config('order_success_to_admin') && !gp247_config('order_success_to_customer')) {
            $this->notify('warning', gp247_language_render('admin.shop.order_email_none_enabled'));

            return;
        }

        if (function_exists('gp247_order_process_after_success')) {
            gp247_order_process_after_success($this->editingId);
        }
        $this->notify('success', gp247_language_render('admin.shop.order_email_resent'));
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
     * Payment-extension options (id => label) for the header edit form — the
     * same source the legacy detail screen used (active:false so the method an
     * old order was placed with still resolves).
     *
     * @return array<string, string>
     */
    public function paymentMethodOptions(): array
    {
        return $this->extensionOptions('payment');
    }

    /**
     * Shipping-extension options (id => label) for the header edit form.
     *
     * @return array<string, string>
     */
    public function shippingMethodOptions(): array
    {
        return $this->extensionOptions('shipping');
    }

    /**
     * Extension list of a group as select options (id => rendered label).
     *
     * @param string $code Extension group code (payment|shipping).
     * @return array<string, string>
     */
    private function extensionOptions(string $code): array
    {
        $options = [];
        foreach (gp247_extension_get_via_code(code: $code, active: false) as $key => $value) {
            $options[$key] = (string) gp247_language_render($value->detail);
        }

        return $options;
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
