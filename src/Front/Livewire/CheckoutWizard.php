<?php

namespace GP247\Shop\Front\Livewire;

use GP247\Core\Models\AdminCountry;
use GP247\Front\Livewire\BaseFrontComponent;
use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Shop\Models\ShopOrderTotal;
use GP247\Shop\Services\CartItem;

/**
 * Livewire wizard component for the checkout flow (US-LW-006).
 *
 * Manages a 4-step wizard (address → shipping → payment → confirm).
 * On reaching the confirm step, the component commits wizard state to session
 * so the existing addOrder() controller can read from it unchanged (ADR-010).
 *
 * Steps shown conditionally:
 *   - "shipping" only when gp247_config('use_shipping') is truthy.
 *   - "payment"  only when gp247_config('use_payment') is truthy.
 *
 * Extends BaseFrontComponent (ADR-011): the view is resolved through the
 * active template first, falling back to this package's default view, so a
 * Template Developer can override the HTML without touching this class.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-006
 * @aidlc-adr ADR-010, ADR-011
 */
class CheckoutWizard extends BaseFrontComponent
{
    /** @var string Current wizard step: address|shipping|payment|confirm */
    public string $step = 'address';

    // Address fields — snake_case to match gp247_order_mapping_validate() keys directly.
    public string $first_name      = '';
    public string $last_name       = '';
    public string $first_name_kana = '';
    public string $last_name_kana  = '';
    public string $email           = '';
    public string $phone           = '';
    public string $address1        = '';
    public string $address2        = '';
    public string $address3        = '';
    public string $postcode        = '';
    public string $country         = '';
    public string $company         = '';
    public string $comment         = '';

    /** @var string Saved-address ID being applied, 'new', or '' (manual entry). */
    public string $address_process = '';

    /** @var string Selected shipping plugin key. */
    public string $shippingMethod = '';

    /** @var string Selected payment plugin key. */
    public string $paymentMethod = '';

    /**
     * Mount: pre-fill address from the authenticated customer's default address.
     * Guests start with empty fields.
     */
    public function mount(): void
    {
        $customer = customer()->user();
        if (!$customer) {
            return;
        }

        $address = $customer->getAddressDefault();
        if ($address) {
            $this->first_name      = (string) ($address->first_name      ?? '');
            $this->last_name       = (string) ($address->last_name       ?? '');
            $this->first_name_kana = (string) ($address->first_name_kana ?? '');
            $this->last_name_kana  = (string) ($address->last_name_kana  ?? '');
            $this->email           = (string) ($customer->email          ?? '');
            $this->phone           = (string) ($address->phone           ?? '');
            $this->address1        = (string) ($address->address1        ?? '');
            $this->address2        = (string) ($address->address2        ?? '');
            $this->address3        = (string) ($address->address3        ?? '');
            $this->postcode        = (string) ($address->postcode        ?? '');
            $this->country         = (string) ($address->country         ?? '');
            $this->company         = (string) ($customer->company        ?? '');
        } else {
            $this->first_name      = (string) ($customer->first_name      ?? '');
            $this->last_name       = (string) ($customer->last_name       ?? '');
            $this->first_name_kana = (string) ($customer->first_name_kana ?? '');
            $this->last_name_kana  = (string) ($customer->last_name_kana  ?? '');
            $this->email           = (string) ($customer->email           ?? '');
            $this->phone           = (string) ($customer->phone           ?? '');
            $this->address1        = (string) ($customer->address1        ?? '');
            $this->address2        = (string) ($customer->address2        ?? '');
            $this->address3        = (string) ($customer->address3        ?? '');
            $this->postcode        = (string) ($customer->postcode        ?? '');
            $this->country         = (string) ($customer->country         ?? '');
            $this->company         = (string) ($customer->company         ?? '');
        }
    }

    /**
     * Populate address fields from a saved customer address.
     * Guests are silently ignored; unknown IDs are ignored.
     *
     * @param string $id Saved-address PK, 'new', or ''.
     */
    public function selectAddress(string $id): void
    {
        $cleanId               = (string) gp247_clean(data: $id, hight: true);
        $this->address_process = $cleanId;

        if ($cleanId === '' || $cleanId === 'new') {
            return;
        }

        $customer = customer()->user();
        if (!$customer) {
            return;
        }

        $address = $customer->addresses()->find($cleanId);
        if (!$address) {
            return;
        }

        $this->first_name      = (string) ($address->first_name      ?? '');
        $this->last_name       = (string) ($address->last_name       ?? '');
        $this->first_name_kana = (string) ($address->first_name_kana ?? '');
        $this->last_name_kana  = (string) ($address->last_name_kana  ?? '');
        $this->phone           = (string) ($address->phone           ?? '');
        $this->address1        = (string) ($address->address1        ?? '');
        $this->address2        = (string) ($address->address2        ?? '');
        $this->address3        = (string) ($address->address3        ?? '');
        $this->postcode        = (string) ($address->postcode        ?? '');
        $this->country         = (string) ($address->country         ?? '');
    }

    /**
     * Select a shipping plugin and immediately persist it to session so
     * ShopOrderTotal::processDataTotal() can calculate shipping costs on the
     * confirm step.
     *
     * @param string $key Plugin key (e.g. 'shipping_flat_rate').
     */
    public function selectShipping(string $key): void
    {
        $this->shippingMethod = (string) gp247_clean(data: $key, hight: true);
        // WHY: processDataTotal() reads session('shippingMethod') — must be set before confirm render.
        session(['shippingMethod' => $this->shippingMethod]);
    }

    /**
     * Select a payment plugin.
     *
     * @param string $key Plugin key (e.g. 'payment_cod').
     */
    public function selectPayment(string $key): void
    {
        $this->paymentMethod = (string) gp247_clean(data: $key, hight: true);
    }

    /**
     * Validate the current step and advance to the next one.
     * Commits all wizard state to session when reaching the confirm step.
     */
    public function nextStep(): void
    {
        match ($this->step) {
            'address'  => $this->advanceFromAddress(),
            'shipping' => $this->advanceFromShipping(),
            'payment'  => $this->advanceFromPayment(),
            default    => null,
        };
    }

    /**
     * Return to the previous step.
     */
    public function prevStep(): void
    {
        $this->step = match ($this->step) {
            'confirm'  => gp247_config('use_payment')  ? 'payment'  : (gp247_config('use_shipping') ? 'shipping' : 'address'),
            'payment'  => gp247_config('use_shipping') ? 'shipping' : 'address',
            'shipping' => 'address',
            default    => 'address',
        };
    }

    // -------------------------------------------------------------------------
    // Private: step advancement helpers
    // -------------------------------------------------------------------------

    /**
     * Validate address fields and move to the next step.
     * When the next step is confirm (both plugins disabled), also commits to session.
     */
    private function advanceFromAddress(): void
    {
        $dataMap = gp247_order_mapping_validate();
        $rules   = array_filter(
            $dataMap['validate'],
            fn(string $k): bool => !in_array($k, ['shippingMethod', 'paymentMethod'], true),
            ARRAY_FILTER_USE_KEY
        );
        $this->validate($rules, $dataMap['messages']);

        $next = $this->nextStepId();
        if ($next === 'confirm') {
            $this->commitToSession();
        }
        $this->step = $next;
    }

    /**
     * Validate shipping selection and move to the next step.
     */
    private function advanceFromShipping(): void
    {
        if (gp247_config('use_shipping')) {
            $this->validate(['shippingMethod' => 'required']);
        }

        $next = $this->nextStepId();
        if ($next === 'confirm') {
            $this->commitToSession();
        } else {
            // Persist early so processDataTotal() can read it on confirm.
            session(['shippingMethod' => $this->shippingMethod]);
        }
        $this->step = $next;
    }

    /**
     * Validate payment selection, commit all state to session, and go to confirm.
     */
    private function advanceFromPayment(): void
    {
        if (gp247_config('use_payment')) {
            $this->validate(['paymentMethod' => 'required']);
        }
        $this->commitToSession();
        $this->step = 'confirm';
    }

    /**
     * Determine the next step based on enabled plugins.
     */
    private function nextStepId(): string
    {
        return match ($this->step) {
            'address'  => gp247_config('use_shipping') ? 'shipping' : (gp247_config('use_payment') ? 'payment' : 'confirm'),
            'shipping' => gp247_config('use_payment') ? 'payment' : 'confirm',
            'payment'  => 'confirm',
            default    => 'confirm',
        };
    }

    /**
     * Write all wizard state to session so the addOrder() controller can read it
     * via session() calls without any modification (ADR-010 Hybrid Strangler).
     */
    private function commitToSession(): void
    {
        $c = fn(string $v): string => (string) gp247_clean(data: $v, hight: true);

        session([
            'shippingMethod'  => $c($this->shippingMethod),
            'paymentMethod'   => $c($this->paymentMethod),
            'address_process' => $c($this->address_process),
            'shippingAddress' => [
                'first_name'      => $c($this->first_name),
                'last_name'       => $c($this->last_name),
                'first_name_kana' => $c($this->first_name_kana),
                'last_name_kana'  => $c($this->last_name_kana),
                'email'           => $c($this->email),
                'phone'           => $c($this->phone),
                'address1'        => $c($this->address1),
                'address2'        => $c($this->address2),
                'address3'        => $c($this->address3),
                'postcode'        => $c($this->postcode),
                'country'         => $c($this->country),
                'company'         => $c($this->company),
                'comment'         => $c($this->comment),
            ],
        ]);

        // WHY: processDataTotal() depends on session('shippingMethod') being set.
        $objects = ShopOrderTotal::getObjectOrderTotal();
        session(['dataTotal' => ShopOrderTotal::processDataTotal($objects)]);
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    /**
     * View key resolved through the active template (ADR-011).
     *
     * @return string
     */
    protected function templateViewKey(): string
    {
        return 'livewire.shop_checkout-wizard';
    }

    /**
     * Default package view namespace, used when the active template has no override.
     *
     * @return string
     */
    protected function defaultViewNamespace(): string
    {
        return 'gp247-shop-front';
    }

    /**
     * Data passed to the resolved checkout-wizard view.
     *
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'shippingPlugins' => $this->loadShippingPlugins(),
            'paymentPlugins'  => $this->loadPaymentPlugins(),
            'dataTotal'       => $this->step === 'confirm' ? (session('dataTotal') ?? []) : [],
            // WHY: session('dataCheckout') comes back as plain arrays instead of
            // CartItem instances when session.serialization = json; rehydrate so
            // the view can keep using $item->id/$item->options etc.
            'cartItems'       => collect(session('dataCheckout') ?? [])->map(fn ($item) => CartItem::hydrate($item)),
            'attributesGroup' => ShopAttributeGroup::pluck('name', 'id')->all(),
            'customer'        => customer()->user(),
            'countries'       => AdminCountry::getCodeAll(),
            'fieldConfig'     => $this->fieldConfig(),
        ];
    }

    /**
     * Per-field show/required flags for the address step, driven by the admin
     * `customer_config_attribute`/`customer_config_attribute_required` toggles
     * (same keys gp247_order_mapping_validate() reads for server-side validation
     * — see vendor/gp247/shop/src/Library/Helpers/order.php). Templates use this
     * instead of hardcoding which optional fields to render, so the view always
     * matches what the admin enabled and what the backend actually validates/saves.
     * first_name/email have no admin toggle (always shown, always required).
     *
     * @return array<string, array{show: bool, required: bool}>
     */
    private function fieldConfig(): array
    {
        $field = static fn (string $key): array => [
            'show'     => (bool) gp247_config($key),
            'required' => (bool) gp247_config($key.'_required'),
        ];

        return [
            'last_name' => $field('customer_lastname'),
            'name_kana' => $field('customer_name_kana'),
            'address1'  => $field('customer_address1'),
            'address2'  => $field('customer_address2'),
            'address3'  => $field('customer_address3'),
            'phone'     => $field('customer_phone'),
            'country'   => $field('customer_country'),
            'postcode'  => $field('customer_postcode'),
            'company'   => $field('customer_company'),
        ];
    }

    // -------------------------------------------------------------------------
    // Private: plugin loaders
    // -------------------------------------------------------------------------

    /**
     * Load enabled shipping plugins for the shipping step.
     *
     * @return array<string, mixed>
     */
    private function loadShippingPlugins(): array
    {
        if (!gp247_config('use_shipping')) {
            return [];
        }

        $modules = gp247_extension_get_via_code(code: 'shipping');
        $sources = gp247_extension_get_all_local(type: 'Plugins');
        $result  = [];

        foreach ($modules as $module) {
            if (!array_key_exists($module['key'], $sources)) {
                continue;
            }
            $class = gp247_extension_get_namespace(type: 'Plugins', key: $module['key']) . '\AppConfig';
            if (class_exists($class) && method_exists($class, 'getInfo')) {
                $result[$module['key']] = (new $class)->getInfo();
            }
        }

        return $result;
    }

    /**
     * Load enabled payment plugins for the payment step.
     *
     * @return array<string, mixed>
     */
    private function loadPaymentPlugins(): array
    {
        if (!gp247_config('use_payment')) {
            return [];
        }

        $modules = gp247_extension_get_via_code(code: 'payment');
        $sources = gp247_extension_get_all_local(type: 'Plugins');
        $result  = [];

        foreach ($modules as $module) {
            if (!array_key_exists($module['key'], $sources)) {
                continue;
            }
            $class = $sources[$module['key']] . '\AppConfig';
            if (class_exists($class)) {
                $result[$module['key']] = (new $class)->getInfo();
            }
        }

        return $result;
    }
}
