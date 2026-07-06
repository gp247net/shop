<?php

namespace GP247\Shop\Admin\Livewire;

use GP247\Core\AdminShell\Infrastructure\GP247AdminComponent;
use GP247\Core\Models\AdminConfig;
use GP247\Shop\Models\ShopTax;
use Illuminate\Contracts\View\View;

/**
 * Shop configuration screen (shop-admin Unit) — modern Livewire/TailAdmin port of
 * the legacy AdminShopConfigController (config_shop_default): a single page with
 * tabbed sections (product, customer, order, sendmail, limit-per-page, layout,
 * captcha). Replaces the legacy per-field jQuery x-editable/iCheck flow with a
 * single Save that writes each key back through the AdminConfig model exactly the
 * way the core config controllers do — global keys at GP247_STORE_ID_GLOBAL,
 * store keys at the active admin store. Domain/keys unchanged (MC-008). Gated by
 * `admin_shop_config`.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-005
 * @aidlc-adr ADR-001, ADR-006, ADR-007
 */
class ShopConfigForm extends GP247AdminComponent
{
    protected ?string $permission = 'admin_shop_config';

    /** @var array<string, mixed> Config key => current value (bound via wire:model). */
    public array $values = [];

    /** @var string Active admin store id (store-scoped configs target this). */
    public string $storeId = '';

    /**
     * Seed the active store and current config values.
     *
     * @return void
     */
    public function mount(): void
    {
        parent::mount();

        $this->storeId = (string) session('adminStoreId', defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : 1);

        foreach ($this->buildTabs() as $tab) {
            foreach ($tab['fields'] as $field) {
                if ($field['type'] === 'checkbox') {
                    $this->values[$field['key']] = (int) $field['value'];
                } elseif ($field['type'] === 'checklist') {
                    // WHY: legacy stores checklist as JSON array (see gp247_captcha_page).
                    $decoded = json_decode((string) $field['value'], true);
                    $this->values[$field['key']] = is_array($decoded) ? $decoded : [];
                } else {
                    $this->values[$field['key']] = (string) $field['value'];
                }
            }
        }
    }

    /**
     * Build the tabbed field metadata from the same config groups the legacy
     * screen loads. Read-only; used by mount(), render() and save().
     *
     * @return array<int, array{id:string, label:string, fields:array<int, array<string, mixed>>}>
     */
    private function buildTabs(): array
    {
        $global = defined('GP247_STORE_ID_GLOBAL') ? GP247_STORE_ID_GLOBAL : 0;
        $store = $this->storeId;

        $taxOptions = ShopTax::pluck('name', 'id')->map(static fn ($v): string => (string) $v)->all();
        $captchaMethods = function_exists('gp247_captcha_get_plugin_installed')
            ? (array) gp247_captcha_get_plugin_installed()
            : [];

        $load = static fn (string $code, $sid) => AdminConfig::getListConfigByCode([
            'code' => $code,
            'storeId' => $sid,
            'keyBy' => 'key',
        ]);

        $tabs = [];

        // --- Product (global) ---
        $fields = [];
        foreach ($load('product_config', $global) as $c) {
            $fields[] = $c->key === 'product_tax'
                ? $this->field($c, 'select', 'global', $taxOptions, false, '', 'basic')
                : $this->field($c, 'checkbox', 'global', [], false, '', 'basic');
        }
        foreach ($load('product_config_attribute', $global) as $c) {
            $fields[] = $this->field($c, 'checkbox', 'global', [], false, '', 'attribute');
        }
        foreach ($load('product_config_attribute_required', $global) as $c) {
            $fields[] = $this->field($c, 'checkbox', 'global', [], false, '', 'attribute_required');
        }
        $tabs[] = ['id' => 'product', 'label' => 'admin.shop.config_product', 'fields' => $fields];

        // --- Customer (global) ---
        $fields = [];
        foreach ($load('customer_config', $global) as $c) {
            $fields[] = $this->field($c, 'checkbox', 'global', [], false, '', 'basic');
        }
        foreach ($load('customer_config_attribute', $global) as $c) {
            // customer_address1 is always-on in the legacy screen (disabled).
            $fields[] = $this->field($c, 'checkbox', 'global', [], $c->key === 'customer_address1', '', 'attribute');
        }
        foreach ($load('customer_config_attribute_required', $global) as $c) {
            $fields[] = $this->field($c, 'checkbox', 'global', [], false, '', 'attribute_required');
        }
        $tabs[] = ['id' => 'customer', 'label' => 'admin.shop.config_customer', 'fields' => $fields];

        // --- Order (global) ---
        $fields = [];
        foreach ($load('order_config', $global) as $c) {
            $fields[] = $this->field($c, 'checkbox', 'global');
        }
        $tabs[] = ['id' => 'order', 'label' => 'admin.shop.config_order', 'fields' => $fields];

        // --- Sendmail (store) ---
        $fields = [];
        foreach ($load('sendmail_config', $store) as $c) {
            $fields[] = $this->field($c, 'checkbox', 'store');
        }
        $tabs[] = ['id' => 'sendmail', 'label' => 'admin.shop.config_sendmail', 'fields' => $fields];

        // --- Limit per page / display (store, numeric) ---
        $fields = [];
        foreach ($load('display_config', $store) as $c) {
            $fields[] = $this->field($c, 'number', 'store');
        }
        $tabs[] = ['id' => 'limit', 'label' => 'admin.shop.config_limit_per_page', 'fields' => $fields];

        // --- Layout (store) ---
        $fields = [];
        foreach ($load('config_layout', $store) as $c) {
            $fields[] = $this->field($c, 'checkbox', 'store');
        }
        $tabs[] = ['id' => 'layout', 'label' => 'admin.shop.config_layout', 'fields' => $fields];

        // --- Captcha (store) ---
        $captchaPages = [
            'register' => gp247_language_render('admin.captcha.captcha_page_register'),
            'forgot'   => gp247_language_render('admin.captcha.captcha_page_forgot_password'),
            'checkout' => gp247_language_render('admin.captcha.captcha_page_checkout'),
            'contact'  => gp247_language_render('admin.captcha.captcha_page_contact'),
            'review'   => gp247_language_render('admin.captcha.captcha_page_review'),
        ];
        $fields = [];
        foreach ($load('captcha_config', $store) as $c) {
            $type = match ($c->key) {
                'captcha_mode' => 'checkbox',
                'captcha_method' => 'select',
                'captcha_page' => 'checklist',
                default => 'text',
            };
            $options = match ($c->key) {
                'captcha_method' => $captchaMethods,
                'captcha_page' => $captchaPages,
                default => [],
            };
            $fields[] = $this->field($c, $type, 'store', $options);
        }
        $tabs[] = ['id' => 'captcha', 'label' => 'admin.shop.config_captcha', 'fields' => $fields];

        return $tabs;
    }

    /**
     * Normalise one AdminConfig row into field metadata.
     *
     * @param object $c           AdminConfig row (key, value, detail).
     * @param string $type        Render type: checkbox|select|number|text.
     * @param string $scope       global|store (target store_id on save).
     * @param array<int|string, string> $options Select options (value => label).
     * @param bool   $disabled    Render disabled + skip on save.
     * @param string $labelSuffix Appended to the label (e.g. " (*)").
     * @return array<string, mixed>
     */
    private function field($c, string $type, string $scope, array $options = [], bool $disabled = false, string $labelSuffix = '', string $section = ''): array
    {
        return [
            'key' => $c->key,
            'label' => $c->detail,
            'labelSuffix' => $labelSuffix,
            'type' => $type,
            'scope' => $scope,
            'options' => $options,
            'disabled' => $disabled,
            'value' => $c->value,
            'section' => $section,
        ];
    }

    /**
     * Persist every editable field back to AdminConfig using the same scope rules
     * as the core config controllers (global vs active store).
     *
     * @return void
     * @throws \GP247\Core\AdminShell\Domain\AuthorizationException When denied.
     */
    public function save(): void
    {
        $this->authorizeAction('update');

        $global = defined('GP247_STORE_ID_GLOBAL') ? GP247_STORE_ID_GLOBAL : 0;

        foreach ($this->buildTabs() as $tab) {
            foreach ($tab['fields'] as $field) {
                if ($field['disabled']) {
                    continue;
                }
                $key = $field['key'];
                if (!array_key_exists($key, $this->values)) {
                    continue;
                }

                if ($field['type'] === 'checkbox') {
                    $value = empty($this->values[$key]) ? 0 : 1;
                } elseif ($field['type'] === 'checklist') {
                    // WHY: legacy reads checklist via json_decode (gp247_captcha_page).
                    $selected = array_values(array_filter(
                        (array) ($this->values[$key] ?? []),
                        static fn ($v): bool => $v !== '' && $v !== null,
                    ));
                    $value = json_encode($selected);
                } else {
                    // WHY: escape at the boundary like the core config update (XSS).
                    $value = gp247_clean((string) $this->values[$key]);
                }

                $targetStore = $field['scope'] === 'global' ? $global : $this->storeId;
                AdminConfig::where('key', $key)->where('store_id', $targetStore)->update(['value' => $value]);
            }
        }

        $this->notify('success', gp247_language_render('admin.msg_change_success'));
    }

    /**
     * @return View
     */
    public function render(): View
    {
        return view('gp247-shop-admin::config-form', [
            'tabs' => $this->buildTabs(),
            'sendmailStatus' => (bool) (function_exists('gp247_config_admin') ? gp247_config_admin('email_action_mode') : false),
            'adminConfigUrl' => function_exists('gp247_route_admin') ? gp247_route_admin('admin_config.index') : '#',
        ])->layout('gp247-admin::layouts.admin', [
            'title' => gp247_language_render('admin.menu_titles.shop_config'),
        ]);
    }
}
