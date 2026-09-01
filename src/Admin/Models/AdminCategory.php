<?php

namespace GP247\Shop\Admin\Models;

use GP247\Shop\Models\ShopCategory;
use Cache;
use GP247\Shop\Models\ShopCategoryDescription;

class AdminCategory extends ShopCategory
{
    // WHY: keyed by store id (see getListTitleAdmin) so per-store title lists do not
    // bleed within one request. ADR multi-store_admin-store-scope-seam (leak L3).
    protected static $getListTitleAdmin = [];
    protected static $getListCategoryGroupByParentAdmin = null;
    /**
     * Get category detail in admin
     *
     * @param   [type]  $id  [$id description]
     *
     * @return  [type]       [return description]
     */
    public static function getCategoryAdmin($id)
    {
        return self::where('id', $id)
        ->first();
    }

    /**
     * Get list category in admin
     *
     * @param   [array]  $dataSearch  [$dataSearch description]
     *
     * @return  [type]               [return description]
     */
    public static function getCategoryListAdmin(array $dataSearch)
    {
        $keyword          = $dataSearch['keyword'] ?? '';
        $sort_order       = $dataSearch['sort_order'] ?? '';
        $arrSort          = $dataSearch['arrSort'] ?? '';
        $tableDescription = (new ShopCategoryDescription)->getTable();
        $tableCategory     = (new ShopCategory)->getTable();
        
        $dataSelect = $tableCategory.'.*,
        '.$tableDescription.'.name,
        '.$tableDescription.'.keyword,
        '.$tableDescription.'.description';


        $categoryList = (new ShopCategory)
            ->selectRaw($dataSelect)
            ->leftJoin($tableDescription, $tableDescription . '.category_id', $tableCategory . '.id')
            ->where($tableDescription . '.lang', gp247_get_locale());
        if ($keyword) {
            $categoryList = $categoryList->where(function ($sql) use ($tableDescription, $keyword) {
                $sql->where($tableDescription . '.name', 'like', '%' . $keyword . '%');
            });
        }

        if ($sort_order && array_key_exists($sort_order, $arrSort)) {
            $field = explode('__', $sort_order)[0];
            $sort_field = explode('__', $sort_order)[1];
            $categoryList = $categoryList->sort($field, $sort_field);
        } else {
            $categoryList = $categoryList->sort('created_at', 'desc');
        }

        $categoryList = $categoryList->paginate(20);

        return $categoryList;
    }

    /**
     * Build the flat [id => label] tree of categories for admin selects, where
     * each label is the full ancestor breadcrumb path (e.g. "RCVN → GA → GA ADC")
     * instead of dash indentation. The path prefix is threaded down the recursion
     * so every level shows its complete lineage — this both matches the intended
     * searchable-select UI and fixes the previous depth-accumulator (`$st`) which
     * reset to '' after each recursive branch and mislabelled deep sub-trees.
     *
     * @param   int|string  $parent      Parent id to expand from (0 = roots).
     * @param   array        $tree        Accumulator, carried through recursion.
     * @param   array|null   $categories  Pre-grouped children by parent (cached).
     * @param   string       $prefix      Breadcrumb path of the parent branch.
     * @return  array<int|string, string> [id => "Parent → Child → …"].
     *
     * @aidlc-unit shop-admin
     * @aidlc-story US-SADM-002
     */
    public function getTreeCategoriesAdmin($parent = 0, &$tree = [], $categories = null, $prefix = '')
    {
        $categories = $categories ?? $this->getListCategoryGroupByParentAdmin();
        $categoriesTitle = $this->getListTitleAdmin();
        $tree = $tree ?? [];
        $lisCategory = $categories[$parent] ?? [];
        if ($lisCategory) {
            foreach ($lisCategory as $category) {
                $title = $categoriesTitle[$category['id']] ?? '';
                $path = $prefix === '' ? $title : $prefix . ' → ' . $title;
                $tree[$category['id']] = $path;
                if (!empty($categories[$category['id']])) {
                    $this->getTreeCategoriesAdmin($category['id'], $tree, $categories, $path);
                }
            }
        }
        return $tree;
    }


    /**
     * Get array title category (id => name) for admin, scoped to the current store.
     *
     * WHY store scope: since 1-1 ownership every category carries its own store_id,
     * so a sub-store admin must only see its own categories. ROOT keeps seeing all
     * (single-store behaviour unchanged — no extra where, same query count). The
     * static memo is keyed by store so two stores in one request do not bleed
     * (ADR multi-store_admin-store-scope-seam, leak class L1/L3).
     *
     * @return array<int|string, string> Category id => localized name for the active store.
     *
     * @aidlc-unit multi-store-pro
     * @aidlc-story US-multi-store-pro-admin-store-switcher
     * @aidlc-adr multi-store_admin-store-scope-seam
     */
    public static function getListTitleAdmin()
    {
        $storeCache = session('adminStoreId') ?: GP247_STORE_ID_ROOT;
        $tableDescription = (new ShopCategoryDescription)->getTable();
        $table = (new AdminCategory)->getTable();
        $buildForStore = function () use ($tableDescription, $table, $storeCache) {
            if (!isset(self::$getListTitleAdmin[$storeCache])) {
                $query = self::join($tableDescription, $tableDescription.'.category_id', $table.'.id')
                    ->where('lang', gp247_get_locale());
                // WHY: ROOT = all (single-store unchanged); sub-store filters to its own rows.
                if ($storeCache != GP247_STORE_ID_ROOT) {
                    $query = $query->where($table.'.store_id', $storeCache);
                }
                self::$getListTitleAdmin[$storeCache] = $query->pluck('name', 'id')->toArray();
            }
            return self::$getListTitleAdmin[$storeCache];
        };
        if (gp247_config_global('cache_status') && gp247_config_global('cache_category')) {
            // Embed the group version so gp247_cache_clear('cache_category') (a version
            // bump) invalidates every store x locale variant at once — the `database`
            // cache driver cannot wildcard-forget the old per-store/locale keys.
            $cacheKey = $storeCache.'_cache_category_'.gp247_get_locale().'_v'.gp247_cache_version('category');
            if (!Cache::has($cacheKey)) {
                gp247_cache_set($cacheKey, $buildForStore());
            }
            return Cache::get($cacheKey);
        }
        return $buildForStore();
    }


    /**
     * Get array title category
     * user for admin
     *
     * @return  [type]  [return description]
     */
    public static function getListCategoryGroupByParentAdmin()
    {
        if (self::$getListCategoryGroupByParentAdmin === null) {
            self::$getListCategoryGroupByParentAdmin = self::selectRaw('id, COALESCE(NULLIF(parent, ""), NULLIF(parent, 0), 0) as parent')
            ->get()
            ->groupBy('parent')
            ->toArray();
        }
        return self::$getListCategoryGroupByParentAdmin;
    }


    /**
     * Create a new category
     *
     * @param   array  $dataCreate  [$dataCreate description]
     *
     * @return  [type]              [return description]
     */
    public static function createCategoryAdmin(array $dataCreate)
    {
        return self::create($dataCreate);
    }


    /**
     * Insert data description
     *
     * @param   array  $dataCreate  [$dataCreate description]
     *
     * @return  [type]              [return description]
     */
    public static function insertDescriptionAdmin(array $dataCreate)
    {
        return ShopCategoryDescription::create($dataCreate);
    }
}
