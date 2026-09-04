<?php

namespace GP247\Shop\Front\Contracts;

/**
 * Contract a total-method plugin (configCode "Promotion"; legacy "Total": coupon, point, …) implements
 * on its AppConfig so the checkout can drive it uniformly, for any template
 * (default or future) without template↔plugin coupling.
 *
 * This is the L2 (behavior) layer of the checkout total-method contract:
 * `CheckoutWizard` discovers active total plugins (L1), calls applyTotal/removeTotal
 * which delegate here, recomputes session('dataTotal') and re-renders reactively
 * (no jQuery, no manual DOM swap). The plugin's own storefront fragment view
 * (checkoutView) is rendered by the shared partial. The data layer
 * (session('totalMethod'), getInfo(), ShopOrderTotal, addOrder()) is unchanged.
 *
 * A total plugin that does NOT implement this interface is hidden from the 2.0
 * checkout (with a logged warning) until upgraded — see
 * ADR-storefront-checkout-total-method-contract / RISK-MAINT-plugin-total-v1-hidden.
 *
 * @aidlc-unit storefront
 * @aidlc-story US-LW-006
 * @aidlc-adr ADR-storefront-checkout-total-method-contract
 */
interface CheckoutTotalMethod
{
    /**
     * Validate the submitted payload and, on success, register the method into
     * session('totalMethod')[<pluginKey>] so ShopOrderTotal::getInfo() picks it up.
     * Must NOT throw for ordinary validation failures — return the error instead.
     *
     * @param array<string, mixed> $payload Sanitised user input (e.g. ['code' => 'SUMMER']).
     * @return array{error: int, msg?: string} error 0 = applied, 1 = rejected (with msg).
     */
    public function checkoutApply(array $payload): array;

    /**
     * Remove this method from session('totalMethod') (idempotent).
     *
     * @return void
     */
    public function checkoutRemove(): void;

    /**
     * The storefront fragment view rendered inside the checkout total-method zone
     * (e.g. 'Plugins/ShopDiscount::checkout'), or null when the plugin contributes
     * a total line but needs no user input UI.
     *
     * @return string|null
     */
    public function checkoutView(): ?string;
}
