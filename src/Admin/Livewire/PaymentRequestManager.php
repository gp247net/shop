<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Domain\AdminUserContract;
use GP247\Core\AdminShell\Domain\AuthorizationException;
use GP247\Core\AdminShell\Infrastructure\HasValidationLabels;
use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Shop\Payment\Contracts\PaymentGateway;
use GP247\Shop\Payment\GatewayRegistry;
use GP247\Shop\Payment\Gateways\ManualGateway;
use GP247\Shop\Payment\Models\PaymentMovement;
use GP247\Shop\Payment\Models\PaymentRequest;
use GP247\Shop\Payment\PaymentCurrency;
use GP247\Shop\Payment\PaymentRequestService;
use GP247\Shop\Payment\PurposeRegistry;
use GP247\Shop\Payment\Support\RequestRejected;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/**
 * Payment requests — two-panel screen on the ResourcePanel base: the list on the
 * right, the create/edit form on the left, and for the record being edited the
 * movement ledger with a "record by hand" form and a cancel action.
 *
 * Money OUT is gated by a permission of its own (`payment.request.settle_out`,
 * a sibling URI so the screen's wildcard never covers it): Layer-2 only tells
 * reads from writes on the screen URI, so the extra check goes through
 * canAccessUrl() directly (NFR-SEC-payment-request-direction-permission).
 *
 * @aidlc-unit payment-request
 * @aidlc-story US-payment-request-admin-screen
 * @aidlc-adr payment-request_generic-money-request
 */
class PaymentRequestManager extends ResourcePanel
{
    use HasValidationLabels;

    /** URI segment (under the admin prefix) that holds the money-out permission. */
    public const SETTLE_OUT_SEGMENT = 'payment_request_settle_out';

    protected ?string $permission = 'payment.request';

    protected bool $keepStateOnSave = true;

    /** @var array<string, mixed> The "record by hand" form for the open record. */
    public array $manual = ['amount' => '', 'reference' => '', 'paid_at' => '', 'note' => ''];

    /** @var array{movement_id: string, amount: string} Refund-through-the-gateway form (slice S3). */
    public array $refund = ['movement_id' => '', 'amount' => ''];

    /** @var array{index: string, amount: string} Refund-a-source form (slice S4): index into refundSourcesOfOpen(). */
    public array $sourceRefund = ['index' => '', 'amount' => ''];

    /** @var string List filter: '' | in | out */
    public string $filterDirection = '';

    /** @var string List filter: '' | open | partially_settled | settled | cancelled | expired */
    public string $filterStatus = '';

    /** @var string List filter: purpose key or '' */
    public string $filterPurpose = '';

    // --- ResourcePanel contract ---

    protected function baseQuery()
    {
        $query = PaymentRequest::query();
        if (!($this->storeScopeActive() && $this->isRootScope())) {
            $query->where('store_id', (string) $this->storeContext());
        }
        if ($this->filterDirection !== '') {
            $query->where('direction', $this->filterDirection);
        }
        if ($this->filterPurpose !== '') {
            $query->where('purpose', $this->filterPurpose);
        }
        if ($this->filterStatus === PaymentRequest::DISPLAY_EXPIRED) {
            $query->expiredOnly();
        } elseif ($this->filterStatus === PaymentRequest::STATUS_OPEN) {
            $query->openOnly();
        } elseif ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        return $query;
    }

    protected function storeScoped(): ?array
    {
        return ['display' => 'party_name'];
    }

    protected function searchable(): array
    {
        return ['party_name', 'party_email', 'description', 'id', 'subject_id'];
    }

    protected function sortableColumns(): array
    {
        return ['id', 'amount', 'status', 'expires_at', 'created_at'];
    }

    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    protected function formDefaults(): array
    {
        return [
            'direction' => PaymentRequest::DIRECTION_IN,
            'purpose' => 'free',
            'amount' => '',
            'currency' => $this->defaultCurrency(),
            'party_name' => '',
            'party_email' => '',
            'party_phone' => '',
            'description' => '',
            'subject_type' => '',
            'subject_id' => '',
            'expires_at' => now()->addDays(7)->format('Y-m-d'),
        ];
    }

    /**
     * @param PaymentRequest $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        $this->formStoreId = (string) $model->store_id;
        $this->manual = [
            'amount' => (string) $model->outstanding(),
            'reference' => '',
            'paid_at' => now()->format('Y-m-d'),
            'note' => '',
        ];

        return [
            'direction' => (string) $model->direction,
            'purpose' => (string) $model->purpose,
            'amount' => (string) $model->amount,
            'currency' => (string) $model->currency,
            'party_name' => (string) $model->party_name,
            'party_email' => (string) $model->party_email,
            'party_phone' => (string) $model->party_phone,
            'description' => (string) $model->description,
            'subject_type' => (string) $model->subject_type,
            'subject_id' => (string) $model->subject_id,
            'expires_at' => $model->expires_at ? $model->expires_at->format('Y-m-d') : '',
        ];
    }

    protected function rules(): array
    {
        $rules = [
            'form.direction' => ['required', 'in:in,out'],
            'form.purpose' => ['required', 'string', function (string $attribute, $value, \Closure $fail): void {
                $registry = app(PurposeRegistry::class);
                if (!$registry->has((string) $value)) {
                    $fail(gp247_language_render('admin.payment_request.purpose_unknown'));
                } elseif (!$registry->allowsDirection((string) $value, (string) ($this->form['direction'] ?? ''))) {
                    $fail(gp247_language_render('admin.payment_request.purpose_direction'));
                }
            }],
            'form.party_name' => ['nullable', 'string', 'max:191'],
            'form.party_email' => ['nullable', 'email', 'max:191'],
            'form.party_phone' => ['nullable', 'string', 'max:50'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.subject_type' => ['nullable', 'string', 'max:64'],
            'form.subject_id' => ['nullable', 'string', 'max:64'],
            'form.expires_at' => ['nullable', 'date'],
        ];

        // Amount and currency are locked once money has moved (the form shows them
        // read-only; the service refuses a change anyway).
        if (!$this->moneyLocked()) {
            $rules['form.amount'] = ['required', 'numeric', 'gt:0'];
            $rules['form.currency'] = ['required', 'string', 'size:3', function (string $attribute, $value, \Closure $fail): void {
                if (!PaymentCurrency::isKnown((string) $value)) {
                    $fail(gp247_language_render('admin.payment_request.currency_unknown'));
                }
            }];
        }

        // A purpose that links to a record (an order, a partner…) needs its id, of one of
        // the record types it declares.
        $spec = $this->subjectSpec();
        if ($spec !== null) {
            $rules['form.subject_type'] = ['required', 'in:' . implode(',', array_keys($spec['types']))];
            $rules['form.subject_id'] = ['required', 'string', 'max:64'];
        }

        return $rules;
    }

    /**
     * Validation label: the linked-record field takes the purpose's own name for it
     * (e.g. "Order ID").
     *
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        $attributes = array_map(fn (string $key): string => $this->label($key), $this->attributeLabels());
        $spec = $this->subjectSpec();
        if ($spec !== null) {
            $attributes['form.subject_id'] = $spec['label'];
            $attributes['form.subject_type'] = $spec['label'];
        }

        return $attributes;
    }

    protected function attributeLabels(): array
    {
        return [
            'form.direction' => 'admin.payment_request.direction',
            'form.purpose' => 'admin.payment_request.purpose',
            'form.amount' => 'admin.payment_request.amount',
            'form.currency' => 'admin.payment_request.currency',
            'form.party_name' => 'admin.payment_request.party_name',
            'form.party_email' => 'admin.payment_request.party_email',
            'form.party_phone' => 'admin.payment_request.party_phone',
            'form.description' => 'admin.payment_request.description',
            'form.expires_at' => 'admin.payment_request.expires_at',
            'manual.amount' => 'admin.payment_request.manual_amount',
            'manual.reference' => 'admin.payment_request.manual_reference',
            'manual.paid_at' => 'admin.payment_request.manual_paid_at',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        $service = app(PaymentRequestService::class);
        $payload = [
            'purpose' => $data['purpose'],
            'party_name' => $data['party_name'] ?? null,
            'party_email' => $data['party_email'] ?? null,
            'party_phone' => $data['party_phone'] ?? null,
            'description' => $data['description'] ?? null,
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] . ' 23:59:59' : null,
        ];

        try {
            if ($this->editingId !== null) {
                $request = $this->baseQuery()->findOrFail($this->editingId);
                if (!$this->moneyLocked()) {
                    $payload['direction'] = $data['direction'];
                    $payload['amount'] = $data['amount'];
                    $payload['currency'] = $data['currency'];
                }
                $service->update($request, $payload);
            } else {
                $payload['direction'] = $data['direction'];
                $payload['amount'] = $data['amount'];
                $payload['currency'] = $data['currency'];
                $payload['store_id'] = (string) $this->resolveCreateStore();
                $payload['created_by'] = $this->currentAdminId();
                $created = $service->create($payload);
                $this->editingId = (string) $created->id;
            }
        } catch (RequestRejected $e) {
            // The purpose owner names the field its refusal is about.
            $field = in_array($e->field, ['subject_id', 'subject_type', 'amount', 'currency', 'direction', 'purpose'], true) ? $e->field : 'amount';
            throw ValidationException::withMessages(['form.' . $field => $e->getMessage()]);
        } catch (\InvalidArgumentException | \LogicException $e) {
            throw ValidationException::withMessages(['form.amount' => $e->getMessage()]);
        }
    }

    /**
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        $request = $this->baseQuery()->find($id);
        if ($request === null) {
            return;
        }
        if ($request->hasMovements()) {
            $this->notify('error', gp247_language_render('admin.payment_request.cannot_delete_with_money'));

            return;
        }
        $request->delete();
    }

    protected function panelView(): string
    {
        return 'gp247-shop-admin::livewire.payment-request-manager';
    }

    protected function pageTitle(): string
    {
        return gp247_language_render('admin.payment_request.title');
    }

    protected function baseRoute(): string
    {
        return 'admin.payment_request.index';
    }

    // --- actions ---

    /**
     * Record money moved by hand against the open record. Money OUT needs the
     * dedicated permission on top of the screen's write permission.
     *
     * @return void
     * @throws AuthorizationException
     */
    public function recordManual(): void
    {
        $this->authorizeAction('recordManual');
        $request = $this->openRequest();
        if ($request === null) {
            return;
        }
        if ($request->direction === PaymentRequest::DIRECTION_OUT) {
            $this->authorizeSettleOut();
        }

        $this->validate([
            'manual.amount' => ['required', 'numeric', 'gt:0', function (string $attribute, $value, \Closure $fail) use ($request): void {
                $allowOver = app(PurposeRegistry::class)->allowsOver($request->purpose);
                if (!$allowOver && (float) $value > $request->outstanding() + 0.5 / (10 ** PaymentCurrency::precision($request->currency))) {
                    $fail(gp247_language_render('admin.payment_request.manual_over', ['outstanding' => $request->outstanding()]));
                }
            }],
            'manual.reference' => ['nullable', 'string', 'max:191'],
            'manual.paid_at' => ['nullable', 'date'],
            'manual.note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(PaymentRequestService::class)->recordManual(
                $request,
                $this->manual['amount'],
                gp247_clean((string) ($this->manual['reference'] ?? '')) ?: null,
                !empty($this->manual['paid_at']) ? $this->manual['paid_at'] : null,
                $this->currentAdminId(),
                gp247_clean((string) ($this->manual['note'] ?? '')) ?: null
            );
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['manual.amount' => $e->getMessage()]);
        }

        $this->form = $this->fillForm($request->fresh());
        $this->notify('success', gp247_language_render('admin.payment_request.manual_recorded'));
    }

    /**
     * Cancel the open record (only while no money has moved).
     *
     * @return void
     * @throws AuthorizationException
     */
    public function cancelRequest(): void
    {
        $this->authorizeAction('cancelRequest');
        $request = $this->openRequest();
        if ($request === null) {
            return;
        }

        try {
            app(PaymentRequestService::class)->cancel($request, $this->currentAdminId());
        } catch (\DomainException $e) {
            $this->notify('error', gp247_language_render('admin.payment_request.cannot_cancel_with_money'));

            return;
        }

        $this->form = $this->fillForm($request->fresh());
        $this->notify('success', gp247_language_render('admin.payment_request.cancelled'));
    }

    /**
     * Issue — or re-issue, killing the old link — the payment link of the open record.
     * A write on the screen (logged like every action), not money out.
     *
     * @return void
     * @throws AuthorizationException
     */
    public function issuePublicLink(): void
    {
        $this->authorizeAction('issuePublicLink');
        $request = $this->openRequest();
        if ($request === null || !$this->canIssueLink()) {
            return;
        }

        try {
            app(PaymentRequestService::class)->issuePublicLink($request);
        } catch (\DomainException $e) {
            $this->notify('error', gp247_language_render('admin.payment_request.link_not_payable'));

            return;
        }

        $this->notify('success', gp247_language_render('admin.payment_request.link_issued'));
    }

    /**
     * Open the refund form for one gateway collection of the open record, pre-filled
     * with what is still refundable on it.
     *
     * @param int|string $movementId
     * @return void
     */
    public function openRefund($movementId): void
    {
        $movement = $this->openMovement((string) $movementId);
        if ($movement === null || !$this->canRefund($movement)) {
            $this->refund = ['movement_id' => '', 'amount' => ''];

            return;
        }
        $this->resetErrorBag('refund.amount');
        $this->refund = [
            'movement_id' => (string) $movement->id,
            'amount' => number_format($this->refundable($movement), PaymentCurrency::precision($movement->currency), '.', ''),
        ];
    }

    /**
     * Give money back through the gateway that collected it. Money going out: needs the
     * money-out permission on top of the screen's write permission.
     *
     * @return void
     * @throws AuthorizationException
     *
     * @aidlc-story US-payment-request-gateway-refund
     */
    public function refundViaGateway(): void
    {
        $this->authorizeAction('refundViaGateway');
        $this->authorizeSettleOut();
        $request = $this->openRequest();
        $movement = $this->openMovement((string) ($this->refund['movement_id'] ?? ''));
        if ($request === null || $movement === null || !$this->canRefund($movement)) {
            return;
        }
        $refundable = $this->refundable($movement);

        $this->validate([
            'refund.amount' => ['required', 'numeric', 'gt:0', function (string $attribute, $value, \Closure $fail) use ($refundable, $movement): void {
                if ((float) $value > $refundable + 0.5 / (10 ** PaymentCurrency::precision($movement->currency))) {
                    $fail(gp247_language_render('admin.payment_request.refund_over', ['refundable' => $refundable]));
                }
            }],
        ]);

        try {
            app(PaymentRequestService::class)->refundViaGateway($request, $movement, $this->refund['amount'], $this->currentAdminId());
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['refund.amount' => $e->getMessage()]);
        } catch (\Throwable $e) {
            gp247_report('[payment-request] refund of movement #' . $movement->id . ' through "' . $movement->gateway . '" failed: ' . $e->getMessage());
            $this->notify('error', gp247_language_render('admin.payment_request.refund_failed'));

            return;
        }

        $this->refund = ['movement_id' => '', 'amount' => ''];
        $this->form = $this->fillForm($request->fresh());
        $this->notify('success', gp247_language_render('admin.payment_request.refund_recorded'));
    }

    /**
     * Open the form to refund one refund source of the open money-out record, pre-filled
     * with the most it can take.
     *
     * @param int|string $index Position in refundSourcesOfOpen().
     * @return void
     */
    public function openSourceRefund($index): void
    {
        $request = $this->openRequest();
        $source = $this->refundSourcesOfOpen()[(int) $index] ?? null;
        if ($request === null || $source === null || !$this->canSettleOut()) {
            $this->sourceRefund = ['index' => '', 'amount' => ''];

            return;
        }
        $this->resetErrorBag('sourceRefund.amount');
        $this->sourceRefund = [
            'index' => (string) (int) $index,
            'amount' => number_format(min($request->outstanding(), $source->refundable), PaymentCurrency::precision($request->currency), '.', ''),
        ];
    }

    /**
     * Pay the open money-out record by refunding a refund source through its gateway.
     *
     * @return void
     * @throws AuthorizationException
     *
     * @aidlc-story US-payment-request-refund-source
     */
    public function refundFromSource(): void
    {
        $this->authorizeAction('refundFromSource');
        $this->authorizeSettleOut();
        $request = $this->openRequest();
        $index = (string) ($this->sourceRefund['index'] ?? '');
        $source = $index === '' ? null : ($this->refundSourcesOfOpen()[(int) $index] ?? null);
        if ($request === null || $source === null) {
            return;
        }

        $this->validate(['sourceRefund.amount' => ['required', 'numeric', 'gt:0']]);

        try {
            app(PaymentRequestService::class)->refundFromSource($request, $source->gateway, $source->gatewayRef, $this->sourceRefund['amount'], $this->currentAdminId());
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['sourceRefund.amount' => $e->getMessage()]);
        } catch (\Throwable $e) {
            gp247_report('[payment-request] refund of source ' . $source->gatewayRef . ' through "' . $source->gateway . '" failed: ' . $e->getMessage());
            $this->notify('error', gp247_language_render('admin.payment_request.refund_failed'));

            return;
        }

        $this->sourceRefund = ['index' => '', 'amount' => ''];
        $this->form = $this->fillForm($request->fresh());
        $this->notify('success', gp247_language_render('admin.payment_request.refund_recorded'));
    }

    /**
     * Keep the linked-record type in line with the chosen purpose: the declared type
     * (the first one when several) for a purpose that links to a record, none otherwise.
     *
     * @return void
     */
    public function updatedFormPurpose(): void
    {
        $spec = $this->subjectSpec();
        $current = (string) ($this->form['subject_type'] ?? '');
        if ($spec === null) {
            $this->form['subject_type'] = '';
        } elseif (!array_key_exists($current, $spec['types'])) {
            $this->form['subject_type'] = (string) array_key_first($spec['types']);
        }
        $this->resetErrorBag(['form.subject_id', 'form.subject_type']);
    }

    /**
     * Who created the open record and when (and who cancelled it), for the form header.
     *
     * @return array{created_by: string, created_at: string, updated_at: ?string, cancelled_by: ?string, cancelled_at: ?string}|null
     */
    public function auditInfo(): ?array
    {
        $request = $this->openRequest();
        if ($request === null) {
            return null;
        }
        $updated = $request->updated_at && $request->created_at && $request->updated_at->ne($request->created_at)
            ? $request->updated_at->format('Y-m-d H:i')
            : null;

        return [
            'created_by' => $this->adminName($request->created_by),
            'created_at' => $request->created_at ? $request->created_at->format('Y-m-d H:i') : '—',
            'updated_at' => $updated,
            'cancelled_by' => $request->cancelled_at ? $this->adminName($request->cancelled_by) : null,
            'cancelled_at' => $request->cancelled_at ? $request->cancelled_at->format('Y-m-d H:i') : null,
        ];
    }

    /**
     * Display names of the admins who created the listed requests, in one query.
     *
     * @param iterable<int, PaymentRequest> $rows
     * @return array<string, string> Admin id => name.
     */
    private function creatorNames(iterable $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (!empty($row->created_by)) {
                $ids[(string) $row->created_by] = true;
            }
        }
        if ($ids === []) {
            return [];
        }
        try {
            return \GP247\Core\Models\AdminUser::whereIn('id', array_keys($ids))->get(['id', 'name', 'username'])
                ->mapWithKeys(fn ($user) => [(string) $user->id => (string) ($user->name ?: $user->username)])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param string|null $adminId
     * @return string The admin's display name, the raw id when the account is gone, "—" when none.
     */
    private function adminName(?string $adminId): string
    {
        if ($adminId === null || $adminId === '') {
            return '—';
        }
        try {
            $user = \GP247\Core\Models\AdminUser::find($adminId);
        } catch (\Throwable $e) {
            $user = null;
        }

        return $user ? (string) ($user->name ?: $user->username) : $adminId;
    }

    /**
     * The record the form's purpose must be linked to, if any.
     *
     * @return array{types: array<string, string>, label: string, help: string}|null
     */
    public function subjectSpec(): ?array
    {
        $purpose = (string) ($this->form['purpose'] ?? '');

        return $purpose === '' ? null : app(PurposeRegistry::class)->subject($purpose);
    }

    public function updatedFilterDirection(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPurpose(): void
    {
        $this->resetPage();
    }

    // --- view helpers ---

    /**
     * Whether the current admin may record money going out.
     *
     * @return bool
     */
    public function canSettleOut(): bool
    {
        /** @var AdminUserContract $user */
        $user = app(AdminUserContract::class);
        if ($user->isAdministrator()) {
            return true;
        }
        $prefix = defined('GP247_ADMIN_PREFIX') ? GP247_ADMIN_PREFIX : 'gp247_admin';

        return $user->canAccessUrl($prefix . '/' . self::SETTLE_OUT_SEGMENT, 'POST');
    }

    /**
     * Whether the "record by hand" form applies to the open record: it takes money,
     * its purpose is still reachable, and the admin holds the right permission.
     *
     * @return bool
     */
    public function canRecordManual(): bool
    {
        $request = $this->openRequest();
        if ($request === null || !$request->acceptsMoney() || $request->isSettled()) {
            return false;
        }
        if (!app(PurposeRegistry::class)->isSettleable($request->purpose)) {
            return false;
        }

        return $request->direction === PaymentRequest::DIRECTION_OUT ? $this->canSettleOut() : true;
    }

    /**
     * Whether a movement can be refunded through its gateway by the current admin.
     *
     * @param PaymentMovement $movement
     * @return bool
     */
    public function canRefund(PaymentMovement $movement): bool
    {
        return $movement->type === PaymentMovement::TYPE_COLLECT
            && $movement->gateway !== ManualGateway::KEY
            && app(GatewayRegistry::class)->supports((string) $movement->gateway, PaymentGateway::CAP_REFUND)
            && $this->refundable($movement) > 0
            && $this->canSettleOut();
    }

    /**
     * Refund sources of the open record (money out, still payable), for the admin.
     *
     * @return array<int, \GP247\Shop\Payment\Support\RefundSource>
     */
    public function refundSourcesOfOpen(): array
    {
        $request = $this->openRequest();
        if ($request === null || !$request->acceptsMoney() || $request->isSettled()) {
            return [];
        }

        return app(PaymentRequestService::class)->refundSources($request);
    }

    /**
     * @param PaymentMovement $movement
     * @return float What is still refundable on a collection.
     */
    public function refundable(PaymentMovement $movement): float
    {
        return app(PaymentRequestService::class)->refundableAmount($movement);
    }

    /**
     * @param string $movementId
     * @return PaymentMovement|null A movement of the open record (never another record's).
     */
    private function openMovement(string $movementId): ?PaymentMovement
    {
        $request = $this->openRequest();
        if ($request === null || $movementId === '') {
            return null;
        }

        return PaymentMovement::where('request_id', $request->id)->find($movementId);
    }

    /**
     * @return bool Whether amount/currency/direction of the open record are locked.
     */
    public function moneyLocked(): bool
    {
        $request = $this->openRequest();

        return $request !== null && $request->hasMovements();
    }

    /**
     * @return PaymentRequest|null The record being edited, scoped to what the admin may see.
     */
    public function openRequest(): ?PaymentRequest
    {
        if ($this->editingId === null || $this->editingId === '') {
            return null;
        }

        return $this->baseQuery()->find($this->editingId);
    }

    /**
     * @return array<string, string> Purpose key => label for the direction on the form.
     */
    public function purposeOptions(): array
    {
        return app(PurposeRegistry::class)->optionsFor((string) ($this->form['direction'] ?? PaymentRequest::DIRECTION_IN));
    }

    /**
     * @param string $key
     * @return string
     */
    public function purposeLabel(string $key): string
    {
        return app(PurposeRegistry::class)->label($key);
    }

    /**
     * What the purpose owner says about the open record's subject.
     *
     * @return array<string, mixed>|null
     */
    public function subjectInfo(): ?array
    {
        $request = $this->openRequest();
        if ($request === null) {
            return null;
        }
        $resolver = app(PurposeRegistry::class)->resolver($request->purpose);
        if ($resolver === null) {
            return null;
        }
        try {
            return $resolver->describeSubject($request);
        } catch (\Throwable $e) {
            gp247_report('[payment-request] describeSubject failed for #' . $request->id . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @return bool Whether the public pay page (slice S2, gp247/front) is routed.
     */
    public function publicLinkAvailable(): bool
    {
        return Route::has(PaymentRequestService::PAY_ROUTE);
    }

    /**
     * Whether a payment link can be issued for the open record (money in, still payable,
     * pay page routed).
     *
     * @return bool
     */
    public function canIssueLink(): bool
    {
        $request = $this->openRequest();

        return $request !== null
            && $this->publicLinkAvailable()
            && app(PaymentRequestService::class)->isPayable($request);
    }

    /**
     * @return string|null The open record's current payment link.
     */
    public function publicUrl(): ?string
    {
        $request = $this->openRequest();

        return $request === null ? null : app(PaymentRequestService::class)->publicUrl($request);
    }

    /**
     * @param string $status Stored or display status.
     * @return string Badge colour name (kept on the Tailwind safelist).
     */
    public function statusColor(string $status): string
    {
        return match ($status) {
            PaymentRequest::STATUS_SETTLED => 'green',
            PaymentRequest::STATUS_PARTIAL => 'amber',
            PaymentRequest::STATUS_CANCELLED => 'gray',
            PaymentRequest::DISPLAY_EXPIRED => 'red',
            default => 'blue',
        };
    }

    /**
     * @param float|string $amount
     * @param string       $currency
     * @return string Amount formatted to the currency precision, with its code.
     */
    public function money($amount, string $currency): string
    {
        return number_format((float) $amount, PaymentCurrency::precision($currency)) . ' ' . strtoupper($currency);
    }

    /**
     * @return array<string, string> Gateways usable for collecting (slice S2/S3 list them on the public page).
     */
    public function collectGateways(): array
    {
        return app(GatewayRegistry::class)->withCapability('collect');
    }

    /**
     * Currencies the admin picks from (the system's list), keeping the record's own code
     * selectable even if it has since left the list.
     *
     * @return array<string, string>|null Code => label, or null when there is no list (free text).
     */
    public function currencyOptions(): ?array
    {
        $options = PaymentCurrency::options();
        if ($options === null) {
            return null;
        }
        $current = PaymentCurrency::normalize((string) ($this->form['currency'] ?? ''));
        if ($current !== '' && !array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    private function defaultCurrency(): string
    {
        $catalog = PaymentCurrency::catalog();
        if ($catalog !== null && $catalog !== []) {
            // The shop's base currency when there is one, else the first of the list.
            $base = function_exists('gp247_base_currency_code') ? strtoupper((string) gp247_base_currency_code()) : '';

            return $base !== '' && array_key_exists($base, $catalog) ? $base : (string) array_key_first($catalog);
        }

        return 'USD';
    }

    private function currentAdminId(): ?string
    {
        try {
            $user = auth()->guard('admin')->user();

            return $user ? (string) $user->getAuthIdentifier() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return void
     * @throws AuthorizationException
     */
    private function authorizeSettleOut(): void
    {
        if (!$this->canSettleOut()) {
            throw AuthorizationException::fromReason('payment_request.settle_out');
        }
    }

    /**
     * Open a record by id only once the tables exist (see render()).
     *
     * @param mixed $id
     * @return void
     */
    public function mount($id = null): void
    {
        parent::mount(PaymentRequestService::ready() ? $id : null);
    }

    public function render(): View
    {
        // The code can be newer than the database (composer update without gp247:shop-update).
        if (!PaymentRequestService::ready()) {
            return view('gp247-shop-admin::livewire.payment-request-not-ready')
                ->layout('gp247-admin::layouts.admin', ['title' => $this->pageTitle()]);
        }

        $rows = $this->rows();

        return view($this->panelView(), [
            'rows' => $rows,
            'creatorNames' => $this->creatorNames($rows),
            'open' => $this->openRequest(),
            'movements' => $this->openRequest()?->movements()->get() ?? collect(),
            'purposeOptions' => $this->purposeOptions(),
            'allPurposes' => app(PurposeRegistry::class)->all(),
            'subject' => $this->subjectInfo(),
            'payUrl' => $this->publicUrl(),
            'refundSources' => $this->refundSourcesOfOpen(),
            'currencyOptions' => $this->currencyOptions(),
            'subjectSpec' => $this->subjectSpec(),
            'audit' => $this->auditInfo(),
        ])->layout('gp247-admin::layouts.admin', ['title' => $this->pageTitle()]);
    }
}
