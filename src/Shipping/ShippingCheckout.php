<?php

namespace GP247\Shop\Shipping;

use GP247\Shop\Shipping\Data\RateRequest;
use GP247\Shop\Shipping\Data\RateSnapshot;

/**
 * The storefront side of a chosen rate: keeps the snapshot (session('shippingRate'))
 * and the request it was quoted for (session('shippingRateRequest')), and answers
 * "what is the shipping line of this checkout" for ShopOrderTotal.
 *
 * session('shippingMethod') stays the plugin key, so every reader of the old contract
 * (templates, plugins, addOrder) keeps working (ADR shop-shipping_legacy-getinfo-adapter).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-rate-snapshot
 * @aidlc-adr shop-shipping_rate-snapshot-and-currency
 */
final class ShippingCheckout
{
    public const SESSION_RATE = 'shippingRate';
    public const SESSION_REQUEST = 'shippingRateRequest';

    /**
     * Remember the shopper's choice.
     *
     * @param RateSnapshot $snapshot Chosen rate.
     * @param RateRequest  $request  Request it was quoted for.
     * @return void
     */
    public static function remember(RateSnapshot $snapshot, RateRequest $request): void
    {
        session([
            self::SESSION_RATE => $snapshot->toArray(),
            self::SESSION_REQUEST => $request->toArray(),
        ]);
    }

    /**
     * @return void
     */
    public static function forget(): void
    {
        session()->forget([self::SESSION_RATE, self::SESSION_REQUEST, RateService::SESSION_QUOTES]);
    }

    /**
     * @return RateSnapshot|null The stored snapshot, if any.
     */
    public static function snapshot(): ?RateSnapshot
    {
        return RateSnapshot::fromArray(session(self::SESSION_RATE));
    }

    /**
     * The rate to bill for the given shipping plugin, or null when the old getInfo()
     * path must be used.
     *
     * 1. A stored snapshot for this plugin: used as-is while valid; once expired it is
     *    re-quoted for the same request — a changed price then surfaces through
     *    addOrder()'s existing "price changed, review" check
     *    (US-LW-checkout-total-freshness). A carrier that cannot answer keeps the
     *    shopper's price (an outage must not block the order).
     * 2. No snapshot but a contract provider and a committed address (plain
     *    controller checkout): quote now and remember the plugin's cheapest rate.
     *
     * @param string $shippingMethod Plugin key in session('shippingMethod').
     * @return RateSnapshot|null
     */
    public static function resolveForTotals(string $shippingMethod): ?RateSnapshot
    {
        if ($shippingMethod === '') {
            return null;
        }

        $snapshot = self::snapshot();
        $committed = self::committedRequest();
        if ($snapshot !== null && $snapshot->pluginKey === $shippingMethod) {
            // The price is only valid for the parcel it was quoted for: once the address /
            // cart is committed, a snapshot of another request is quoted again.
            $request = $committed ?? RateRequest::fromArray(session(self::SESSION_REQUEST));
            $stale = $committed !== null && !$snapshot->matches($committed);
            if (!$stale && !$snapshot->isExpired()) {
                return $snapshot;
            }
            $fresh = $request !== null ? RateService::instance()->find($request, $snapshot->rateId, fresh: $snapshot->isExpired()) : null;
            if ($fresh !== null && $request !== null) {
                self::remember($fresh, $request);

                return $fresh;
            }
            if ($stale) {
                self::forget();

                return null;
            }

            // Expired and the carrier did not answer: keep the shopper's price (an outage
            // must not block the order).
            return $snapshot;
        }

        if (ShippingProviderRegistry::instance()->definition($shippingMethod) === null || $committed === null) {
            return null;
        }
        foreach (RateService::instance()->quote($committed)['rates'] as $candidate) {
            if ($candidate->pluginKey === $shippingMethod) {
                self::remember($candidate, $committed);

                return $candidate;
            }
        }

        return null;
    }

    /**
     * True when the shipping plugin is a contract provider that could not price this
     * checkout: the order must be refused, never billed with the old getInfo() value
     * (which is 0 for contract providers — no free shipping by accident).
     *
     * @param string $shippingMethod Plugin key.
     * @return bool
     */
    public static function isUnquotable(string $shippingMethod): bool
    {
        return $shippingMethod !== ''
            && ShippingProviderRegistry::instance()->definition($shippingMethod) !== null
            && self::resolveForTotals($shippingMethod) === null;
    }

    /**
     * @return RateRequest|null The parcel of the committed checkout (session address + cart).
     */
    private static function committedRequest(): ?RateRequest
    {
        $address = session('shippingAddress');
        if (!is_array($address) || $address === []) {
            return null;
        }

        return RateRequestFactory::fromCheckout(
            $address,
            (array) (session('dataCheckout') ?? []),
            self::storeId(),
            (string) (session('paymentMethod') ?? ''),
        );
    }

    /**
     * @return string Store being checked out (vendor store on a marketplace checkout).
     */
    public static function storeId(): string
    {
        if (function_exists('gp247_plugin_store_id')) {
            return (string) gp247_plugin_store_id();
        }

        return (string) config('app.storeId', defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : '1');
    }
}
