<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\HasCustomFields;
use GP247\Core\AdminShell\Infrastructure\ResourcePanel;
use GP247\Core\Models\AdminCountry;
use GP247\Shop\Admin\Models\AdminCustomer;
use GP247\Shop\Models\ShopCustomer;
use GP247\Shop\Models\ShopCustomerAddress;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Customer manager (shop-admin Unit) — two-panel screen (add/edit form left, list
 * right) on the core ResourcePanel base + the reusable custom-fields trait (D0),
 * matching the legacy AdminCustomerController (rule ui-tailadmin P1). Validation
 * and persistence reuse the brownfield helpers verbatim
 * (gp247_customer_data_insert_mapping / _edit_mapping + ShopCustomer::createCustomer
 * / updateInfo), so password hashing, the config-driven field set, email
 * uniqueness, the default address and custom fields stay identical to legacy.
 * Scoped to the current admin store. Gated by `admin_customer`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-004
 * @aidlc-adr ADR-001, ADR-005, ADR-006, ADR-007
 */
class CustomerManager extends ResourcePanel
{
    use HasCustomFields;

    protected ?string $permission = 'admin_customer';

    /**
     * Keep list state (page/keyword/sort) and the edited record on screen when
     * editing/saving, instead of remounting via route navigation.
     *
     * @var bool
     * @aidlc-story US-AUI-two-panel-state-preservation
     * @aidlc-adr ADR-admin-shell-rbac-two-panel-state-preservation
     */
    protected bool $keepStateOnSave = true;

    /** Scalar customer fields edited on this screen (config-driven visibility). */
    private const FIELDS = [
        'email', 'first_name', 'last_name', 'phone', 'country', 'postcode',
        'company', 'sex', 'birthday', 'group', 'first_name_kana', 'last_name_kana',
        'city', 'district', 'address1', 'address2', 'address3',
    ];

    /** Address fields (1:N), config-driven, mirroring the legacy address screen. */
    private const ADDRESS_FIELDS = [
        'first_name', 'last_name', 'city', 'district', 'address1', 'address2', 'address3',
        'phone', 'country', 'postcode',
    ];

    /** @var array<int, array<string, mixed>> The editing customer's addresses. */
    public array $addresses = [];

    /** @var array<string, mixed> Add/edit address form state. */
    public array $addressForm = [];

    /** @var string|null Id of the address being edited (null = adding). */
    public ?string $editingAddressId = null;

    /** @var string|null The customer's default address id. */
    public ?string $defaultAddressId = null;

    /**
     * @return string
     */
    protected function customFieldType(): string
    {
        // WHY: use the model's prefixed table name so the `type` key matches the
        // read/display path (ModelTrait::getCustomFields keys on getTable()); a bare
        // 'shop_customer' literal diverges from the prefixed key and never round-trips.
        return (new ShopCustomer)->getTable();
    }

    /**
     * Opt into the ResourcePanel store-scope UI. Customers own a single store
     * (the storefront where they registered — 1-1, like the catalog entities), so
     * this exposes the store picker on create-at-root and a per-row store label.
     * No form field depends on the store, so `reset` is empty. Active only when a
     * multi-store/multi-vendor plugin is installed (single-store parity otherwise).
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
     * Store-scoped customer query: root admin sees every store's customers (each
     * row labelled by its store); a scoped context (store-admin) or a single-store
     * install filters to the own store.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function baseQuery()
    {
        $query = AdminCustomer::query();
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
        return ['email', 'first_name', 'last_name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['email', 'first_name', 'last_name', 'created_at', 'id'];
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
        return 'gp247-shop-admin::customer-manager';
    }

    /**
     * @return string
     */
    protected function pageTitle(): string
    {
        return gp247_language_render('admin.customer.list');
    }

    /**
     * @return string
     */
    protected function baseRoute(): string
    {
        return 'admin_customer.index';
    }

    /**
     * @return array<string, mixed>
     */
    protected function formDefaults(): array
    {
        $defaults = ['password' => '', 'password_confirmation' => '', 'status' => 1];
        foreach (self::FIELDS as $field) {
            $defaults[$field] = '';
        }

        return $defaults;
    }

    /**
     * Reset both the scalar form and the custom-field state.
     *
     * @return void
     */
    public function resetForm(): void
    {
        parent::resetForm();
        $this->initCustomFields();
        $this->addresses = [];
        $this->defaultAddressId = null;
        $this->resetAddressForm();
    }

    /**
     * @param ShopCustomer $model
     * @return array<string, mixed>
     */
    protected function fillForm($model): array
    {
        // Store is immutable on edit — expose it for the read-only display.
        $this->formStoreId = (string) $model->store_id;
        $this->loadCustomFields($model->id);
        $this->defaultAddressId = $model->address_id !== null ? (string) $model->address_id : null;
        $this->refreshAddresses();
        $this->resetAddressForm();

        $form = ['password' => '', 'password_confirmation' => '', 'status' => (int) $model->status];
        foreach (self::FIELDS as $field) {
            $form[$field] = (string) ($model->{$field} ?? '');
        }

        return $form;
    }

    /**
     * Validation is delegated to the brownfield mapping helpers inside save().
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Authorize, validate (via the brownfield mapping helpers) and persist (via
     * ShopCustomer::createCustomer / updateInfo) — preserving legacy parity — then
     * navigate back to the base route.
     *
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     * @throws ValidationException When validation fails.
     */
    public function save(): void
    {
        $this->authorizeAction($this->editingId !== null ? 'update' : 'store');

        $data = $this->customerData();
        $mapping = $this->editingId !== null
            ? gp247_customer_data_edit_mapping($data)
            : gp247_customer_data_insert_mapping($data);

        $validator = Validator::make($data, $mapping['validate'], $mapping['messages']);
        if ($validator->fails()) {
            $this->throwMappedErrors($validator->errors()->messages());
        }

        if ($this->editingId !== null) {
            ShopCustomer::updateInfo($mapping['dataUpdate'], $this->editingId);
            $savedId = (string) $this->editingId;
        } else {
            $insert = $mapping['dataInsert'];
            // WHY: createCustomer does not set store_id; a new customer is owned by
            // the store picked on create (root admin) or the current scoped store
            // (store-admin), server-authoritative via resolveCreateStore().
            $insert['store_id'] = $this->resolveCreateStore();
            $customer = ShopCustomer::createCustomer($insert);
            if (function_exists('gp247_customer_created_by_admin')) {
                gp247_customer_created_by_admin($customer);
            }
            $savedId = (string) $customer->id;
        }

        // Opted-in (keepStateOnSave): stay in place — re-fill the form from the
        // saved record and keep the list state — instead of redirecting.
        if ($this->keepStateOnSave) {
            $this->editingId = $savedId;
            $model = $this->baseQuery()->find($savedId);
            if ($model !== null) {
                $this->form = $this->fillForm($model);
            }
            $this->syncEditUrl($savedId);
            $this->notify('success', gp247_language_render('admin.save_success'));

            return;
        }

        session()->flash('gp247_admin_success', gp247_language_render('admin.save_success'));
        $this->redirect(route($this->baseRoute()), navigate: true);
    }

    /**
     * Unused: customer persistence is handled in save() via the mapping helpers.
     *
     * @param array<string, mixed> $data
     * @return void
     */
    protected function persist(array $data): void
    {
        // No-op — see save().
    }

    /**
     * @param int|string $id
     * @return void
     */
    protected function deleteModel($id): void
    {
        // ShopCustomer::boot() cascades the customer's custom fields on delete.
        $model = $this->baseQuery()->find($id);
        if ($model !== null) {
            $model->delete();
        }
    }

    /**
     * Build the request-shaped customer data array from the form + custom fields.
     *
     * @return array<string, mixed>
     */
    private function customerData(): array
    {
        $data = [
            'password' => (string) ($this->form['password'] ?? ''),
            'password_confirmation' => (string) ($this->form['password_confirmation'] ?? ''),
            'status' => empty($this->form['status']) ? 0 : 1,
            'store_id' => $this->resolveCreateStore(),
            'fields' => $this->customFieldsPayload(),
        ];
        foreach (self::FIELDS as $field) {
            $data[$field] = $this->form[$field] ?? '';
        }
        if ($this->editingId !== null) {
            $data['id'] = $this->editingId;
        }

        return $data;
    }

    /**
     * Re-key validation errors from the mapping (plain field names / fields.*) to
     * the component property paths (form.* / customFields.*) and throw.
     *
     * @param array<string, array<int, string>> $messages
     * @return void
     * @throws ValidationException
     */
    private function throwMappedErrors(array $messages): void
    {
        $mapped = [];
        foreach ($messages as $key => $msgs) {
            $newKey = str_starts_with($key, 'fields.')
                ? 'customFields.' . substr($key, 7)
                : 'form.' . $key;
            $mapped[$newKey] = $msgs;
        }

        throw ValidationException::withMessages($mapped);
    }

    // --- Addresses (1:N, shown on edit) ------------------------------------

    /**
     * Empty address form state.
     *
     * @return void
     */
    private function resetAddressForm(): void
    {
        $this->editingAddressId = null;
        $form = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $form[$field] = '';
        }
        $this->addressForm = $form;
    }

    /**
     * Reload the editing customer's addresses into state.
     *
     * @return void
     */
    private function refreshAddresses(): void
    {
        if ($this->editingId === null) {
            $this->addresses = [];

            return;
        }

        $this->addresses = ShopCustomerAddress::where('customer_id', $this->editingId)
            ->orderBy('id')
            ->get()
            ->map(function ($row): array {
                $data = ['id' => (string) $row->id];
                foreach (self::ADDRESS_FIELDS as $field) {
                    $data[$field] = (string) ($row->{$field} ?? '');
                }

                return $data;
            })
            ->all();
    }

    /**
     * Start adding a new address.
     *
     * @return void
     */
    public function newAddress(): void
    {
        $this->resetAddressForm();
    }

    /**
     * Load an address into the address form for editing.
     *
     * @param int|string $id
     * @return void
     */
    public function editAddress($id): void
    {
        $row = ShopCustomerAddress::where('id', $id)->where('customer_id', $this->editingId)->first();
        if ($row === null) {
            return;
        }

        $form = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $form[$field] = (string) ($row->{$field} ?? '');
        }
        $this->addressForm = $form;
        $this->editingAddressId = (string) $row->id;
    }

    /**
     * Validate (config-driven, via gp247_customer_address_mapping) and persist the
     * address form for the editing customer.
     *
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     * @throws ValidationException When validation fails.
     */
    public function saveAddress(): void
    {
        $this->authorizeAction('update');
        if ($this->editingId === null) {
            return;
        }

        $data = [];
        foreach (self::ADDRESS_FIELDS as $field) {
            $data[$field] = $this->addressForm[$field] ?? '';
        }

        $mapping = gp247_customer_address_mapping($data);
        $validator = Validator::make($data, $mapping['validate'], $mapping['messages']);
        if ($validator->fails()) {
            $mapped = [];
            foreach ($validator->errors()->messages() as $key => $msgs) {
                $mapped['addressForm.' . $key] = $msgs;
            }
            throw ValidationException::withMessages($mapped);
        }

        $payload = gp247_clean($mapping['dataAddress']);

        if ($this->editingAddressId !== null) {
            ShopCustomerAddress::where('id', $this->editingAddressId)
                ->where('customer_id', $this->editingId)
                ->update($payload);
        } else {
            $payload['customer_id'] = $this->editingId;
            ShopCustomerAddress::create($payload);
        }

        $this->refreshAddresses();
        $this->resetAddressForm();
        $this->notify('success', gp247_language_render('admin.save_success'));
    }

    /**
     * Delete an address. Refuses to delete the default address while it is the
     * customer's only one.
     *
     * @param int|string $id
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     */
    public function deleteAddress($id): void
    {
        $this->authorizeAction('update');
        if ($this->editingId === null) {
            return;
        }

        $row = ShopCustomerAddress::where('id', $id)->where('customer_id', $this->editingId)->first();
        if ($row === null) {
            return;
        }

        $isDefault = (string) $id === (string) $this->defaultAddressId;
        $total = ShopCustomerAddress::where('customer_id', $this->editingId)->count();
        if ($isDefault && $total <= 1) {
            $this->notify('warning', gp247_language_render('admin.toast_error'));

            return;
        }

        $row->delete();

        // Reassign the default to another address when the default was removed.
        if ($isDefault) {
            $next = ShopCustomerAddress::where('customer_id', $this->editingId)->orderBy('id')->first();
            $this->applyDefaultAddress($next?->id);
        }

        $this->refreshAddresses();
        $this->notify('success', gp247_language_render('admin.delete_success'));
    }

    /**
     * Set an address as the customer's default.
     *
     * @param int|string $id
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     */
    public function setDefaultAddress($id): void
    {
        $this->authorizeAction('update');
        if ($this->editingId === null) {
            return;
        }

        $exists = ShopCustomerAddress::where('id', $id)->where('customer_id', $this->editingId)->exists();
        if (!$exists) {
            return;
        }

        $this->applyDefaultAddress($id);
        $this->notify('success', gp247_language_render('admin.save_success'));
    }

    /**
     * Persist the customer's default address id.
     *
     * @param int|string|null $addressId
     * @return void
     */
    private function applyDefaultAddress($addressId): void
    {
        ShopCustomer::where('id', $this->editingId)->update(['address_id' => $addressId]);
        $this->defaultAddressId = $addressId !== null ? (string) $addressId : null;
    }

    /**
     * Country options (code => name) for the form select.
     *
     * @return array<string, string>
     */
    public function countryOptions(): array
    {
        return (array) (new AdminCountry())->getCodeAll();
    }

    /**
     * Active custom-field definitions (exposed to the view).
     *
     * @return iterable<mixed>
     */
    public function customFieldList(): iterable
    {
        return $this->customFieldDefs();
    }
}
