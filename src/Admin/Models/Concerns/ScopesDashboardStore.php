<?php

namespace GP247\Shop\Admin\Models\Concerns;

/**
 * Store filter shared by the admin dashboard figures (KPI counts, revenue series,
 * latest orders/customers): a non-root store sees only its own rows, while the
 * root store (or no store given) keeps the all-stores view — parity with
 * OrderManager and the placed-revenue scope.
 *
 * @aidlc-unit shop-admin
 * @aidlc-story US-SADM-dashboard-store-scope, US-SADM-revenue-semantics
 * @aidlc-adr shop-admin_revenue-semantics
 */
trait ScopesDashboardStore
{
    /**
     * Constrain $query to $storeId unless it is null or the root store.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|string|null $storeId Null/root = all stores; otherwise this store only.
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-dashboard-store-scope
     */
    protected static function applyDashboardStore($query, $storeId)
    {
        $root = defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : 1;
        if ($storeId !== null && (string) $storeId !== (string) $root) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }
}
