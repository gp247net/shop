<?php
namespace GP247\Shop\Admin\Controllers;

use GP247\Core\Controllers\RootAdminController;
use GP247\Core\Models\AdminLanguage;
use Validator;
use GP247\Shop\Admin\Models\AdminCategory;
use GP247\Core\Models\AdminCustomField;

class AdminCategoryController extends RootAdminController
{
    public $languages;

    public function __construct()
    {
        parent::__construct();
        $this->languages = AdminLanguage::getListActive();
    }

    public function index()
    {
        $categoriesTitle =  AdminCategory::getListTitleAdmin();
        $data = [
            'title'         => gp247_language_render('admin.category.list'),
            'subTitle'      => '',
            'icon'          => 'fa fa-indent',
            'urlDeleteItem' => gp247_route_admin('admin_category.delete'),
            'removeList'    => 1, // 1 - Enable function delete list item
            'buttonRefresh' => 1, // 1 - Enable button refresh
            'css'           => '',
            'js'            => '',
        ];
        //Process add content
        $data['menuRight']    = gp247_config_group('menuRight', \Request::route()->getName());
        $data['menuLeft']     = gp247_config_group('menuLeft', \Request::route()->getName());
        $data['topMenuRight'] = gp247_config_group('topMenuRight', \Request::route()->getName());
        $data['topMenuLeft']  = gp247_config_group('topMenuLeft', \Request::route()->getName());
        $data['blockBottom']  = gp247_config_group('blockBottom', \Request::route()->getName());

        $listTh = [
            'image'  => gp247_language_render('admin.category.image'),
            'title'  => gp247_language_render('admin.category.title'),
            'parent' => gp247_language_render('admin.category.parent'),
            'top'    => gp247_language_render('admin.category.top'),
            'status' => gp247_language_render('admin.category.status'),
            'sort'   => gp247_language_render('admin.category.sort'),
        ];

        if (gp247_store_check_multi_store_installed() && session('adminStoreId') == GP247_STORE_ID_ROOT) {
            $listTh['shop_store'] = gp247_language_render('front.store_list');
        }

        $listTh['action'] = gp247_language_render('action.title');

        $sort_order = gp247_clean(request('sort_order') ?? 'id_desc');
        $keyword    = gp247_clean(request('keyword') ?? '');
        $arrSort = [
            'id__desc' => gp247_language_render('filter_sort.id_desc'),
            'id__asc' => gp247_language_render('filter_sort.id_asc'),
            'title__desc' => gp247_language_render('filter_sort.title_desc'),
            'title__asc' => gp247_language_render('filter_sort.title_asc'),
        ];
        
        $dataSearch = [
            'keyword'    => $keyword,
            'sort_order' => $sort_order,
            'arrSort'    => $arrSort,
        ];
        $dataTmp = (new AdminCategory)->getCategoryListAdmin($dataSearch);
        
        if (gp247_store_check_multi_store_installed() && session('adminStoreId') == GP247_STORE_ID_ROOT) {
            $arrId = $dataTmp->pluck('id')->toArray();
            if (function_exists('gp247_get_list_store_of_category')) {
                $dataStores = gp247_get_list_store_of_category($arrId);
            } else {
                $dataStores = [];
            }
        }

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $dataMap = [
                'image' => gp247_image_render($row->getThumb(), '50px', '50px', $row['title']),
                'title' => $row['title'],
                'parent' => $row['parent'] ? ($categoriesTitle[$row['parent']] ?? '') : 'ROOT',
                'top' => $row['top'] ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-danger">OFF</span>',
                'status' => $row['status'] ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-danger">OFF</span>',
                'sort' => $row['sort'],
            ];

            if (gp247_store_check_multi_store_installed() && session('adminStoreId') == GP247_STORE_ID_ROOT) {
                if (!empty($dataStores[$row['id']])) {
                    $storeTmp = $dataStores[$row['id']]->pluck('code', 'id')->toArray();
                    $storeTmp = array_map(function ($code) {
                        if (is_null($code)) {
                            return ;
                        }
                        $domain = gp247_store_get_domain_from_code($code);
                        return '<a target=_new href="'.$domain.'">'.$code.'</a>';
                    }, $storeTmp);
                    $dataMap['shop_store'] = '<i class="nav-icon fab fa-shopify"></i> '.implode('<br><i class="nav-icon fab fa-shopify"></i> ', $storeTmp);
                }
            }

            $arrAction = [
                '<a href="' . gp247_route_admin('admin_category.edit', ['id' => $row['id'] ? $row['id'] : 'not-found-id']) . '"  class="dropdown-item"><i class="fa fa-edit"></i> '.gp247_language_render('action.edit').'</a>',
                ];
            $arrAction[] = '<a href="#" onclick="deleteItem(\'' . $row['id'] . '\');"  title="' . gp247_language_render('action.delete') . '" class="dropdown-item"><i class="fas fa-trash-alt"></i> '.gp247_language_render('action.remove').'</a>';
            $arrAction[] = '<a href="'.gp247_route_front('category.detail', ['alias' => $row['alias']]).'" target=_new title="Link" class="dropdown-item"><i class="fas fa-external-link-alt"></i></a>';

            $action = $this->procesListAction($arrAction);

            $dataMap['action'] = $action;
            $dataTr[$row['id']] = $dataMap;
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('gp247-core::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);


        //menuRight
        $data['menuRight'][] = '<a href="' . gp247_route_admin('admin_category.create') . '" class="btn  btn-success  btn-flat" title="New" id="button_create_new">
        <i class="fa fa-plus" title="'.gp247_language_render('action.add_new').'"></i>
        </a>';
        //=menuRight

        //menuSort
        $optionSort = '';
        foreach ($arrSort as $key => $sort) {
            $optionSort .= '<option  ' . (($sort_order == $key) ? "selected" : "") . ' value="' . $key . '">' . $sort . '</option>';
        }
        //=menuSort

        //menuSearch
        $data['topMenuRight'][] = '
                <form action="' . gp247_route_admin('admin_category.index') . '" id="button_search">
                <div class="input-group input-group" style="width: 350px;">
                    <select class="form-control rounded-0 select2" name="sort_order" id="sort_order">
                    '.$optionSort.'
                    </select> &nbsp;
                    <input type="text" name="keyword" class="form-control rounded-0 float-right" placeholder="' . gp247_language_render('search.placeholder') . '" value="' . $keyword . '">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                </form>';
        //=menuSearch

        return view('gp247-core::screen.list')
            ->with($data);
    }

    /*
     * Form create new item in admin
     * @return [type] [description]
     */
    public function create()
    {
        $data = [
            'title' => gp247_language_render('admin.category.add_new_title'),
            'subTitle' => '',
            'title_description' => gp247_language_render('admin.category.add_new_des'),
            'icon' => 'fa fa-plus',
            'languages' => $this->languages,
            'category' => [],
            'categories' => (new AdminCategory)->getTreeCategoriesAdmin(),
            'url_action' => gp247_route_admin('admin_category.create'),
            'customFields'      => (new AdminCustomField)->getCustomField($type = 'shop_category'),
        ];

        return view('gp247-shop-admin::screen.category')
            ->with($data);
    }

    /*
     * Post create new item in admin
     * @return [type] [description]
     */
    public function postCreate()
    {
        $data = request()->all();

        $langFirst = array_key_first(gp247_language_all()->toArray()); //get first code language active
        $data['alias'] = !empty($data['alias'])?$data['alias']:$data['descriptions'][$langFirst]['title'];
        $data['alias'] = gp247_word_format_url($data['alias']);
        $data['alias'] = gp247_word_limit($data['alias'], 100);
        $arrValidation = [
            'parent'                 => 'required',
            'sort'                   => 'numeric|min:0',
            'alias'                  => 'required|unique:"'.AdminCategory::class.'",alias|string|max:100',
            'descriptions.*.title'   => 'required|string|max:200',
            'descriptions.*.keyword' => 'nullable|string|max:200',
            'descriptions.*.description' => 'nullable|string|max:500',
        ];
        //Custom fields
        $customFields = (new AdminCustomField)->getCustomField($type = 'shop_category');
        if ($customFields) {
            foreach ($customFields as $field) {
                if ($field->required) {
                    $arrValidation['fields.'.$field->code] = 'required';
                }
            }
        }
        $validator = Validator::make(
            $data,$arrValidation,
            [
                'descriptions.*.title.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('admin.category.title')]),
                'alias.regex' => gp247_language_render('admin.category.alias_validate'),
            ]
        );

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($data);
        }
        $dataCreate = [
            'image'    => $data['image'],
            'alias'    => $data['alias'],
            'parent'   => $data['parent'],
            'top'      => !empty($data['top']) ? 1 : 0,
            'status'   => !empty($data['status']) ? 1 : 0,
            'sort'     => (int) $data['sort'],
        ];
        $dataCreate = gp247_clean($dataCreate, [], true);
        $category = AdminCategory::createCategoryAdmin($dataCreate);
        $dataDes = [];
        $languages = $this->languages;
        foreach ($languages as $code => $value) {
            $dataDes[] = [
                'category_id' => $category->id,
                'lang'        => $code,
                'title'       => $data['descriptions'][$code]['title'],
                'keyword'     => $data['descriptions'][$code]['keyword'],
                'description' => $data['descriptions'][$code]['description'],
            ];
        }
        $dataDes  = gp247_clean($dataDes, [], true);
        AdminCategory::insertDescriptionAdmin($dataDes);

        if (gp247_store_check_multi_store_installed()) {
            // If multi-store
            $shopStore        = $data['shop_store'] ?? [];
            $category->stores()->detach();
            if ($shopStore) {
                $category->stores()->attach($shopStore);
            }
        }
        //Insert custom fields
        $fields = $data['fields'] ?? [];
        gp247_custom_field_update($fields, $category->id, 'shop_category');

        gp247_cache_clear('cache_category');

        return redirect()->route('admin_category.index')->with('success', gp247_language_render('action.create_success'));
    }

    /*
     * Form edit
     */
    public function edit($id)
    {
        $category = AdminCategory::getCategoryAdmin($id);

        if (!$category) {
            return redirect()->route('admin.data_not_found')->with(['url' => url()->full()]);
        }

        $data = [
            'title'             =>gp247_language_render('action.edit'),
            'subTitle'          => '',
            'title_description' => '',
            'icon'              => 'fa fa-edit',
            'languages'         => $this->languages,
            'category'          => $category,
            'categories'        => (new AdminCategory)->getTreeCategoriesAdmin(),
            'url_action'        => gp247_route_admin('admin_category.edit', ['id' => $category['id']]),
            'customFields'      => (new AdminCustomField)->getCustomField($type = 'shop_category'),
        ];
        return view('gp247-shop-admin::screen.category')
            ->with($data);
    }

    /*
     * update status
     */
    public function postEdit($id)
    {
        $category = AdminCategory::getCategoryAdmin($id);
        if (!$category) {
            return redirect()->route('admin.data_not_found')->with(['url' => url()->full()]);
        }

        $data = request()->all();

        $langFirst = array_key_first(gp247_language_all()->toArray()); //get first code language active
        $data['alias'] = !empty($data['alias'])?$data['alias']:$data['descriptions'][$langFirst]['title'];
        $data['alias'] = gp247_word_format_url($data['alias']);
        $data['alias'] = gp247_word_limit($data['alias'], 100);
        $arrValidation = [
            'parent'                 => 'required',
            'sort'                   => 'numeric|min:0',
            'alias'                  => 'required|string|max:100|unique:"'.AdminCategory::class.'",alias,' . $id . '',
            'descriptions.*.title'   => 'required|string|max:200',
            'descriptions.*.keyword' => 'nullable|string|max:200',
            'descriptions.*.description' => 'nullable|string|max:500',
        ];
        //Custom fields
        $customFields = (new AdminCustomField)->getCustomField($type = 'shop_category');
        if ($customFields) {
            foreach ($customFields as $field) {
                if ($field->required) {
                    $arrValidation['fields.'.$field->code] = 'required';
                }
            }
        }
        $validator = Validator::make(
            $data,$arrValidation,
            [
                'descriptions.*.title.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('admin.category.title')]),
                'alias.regex'                   => gp247_language_render('admin.category.alias_validate'),
            ]
        );

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($data);
        }
        //Edit
        $dataUpdate = [
            'image'    => $data['image'],
            'alias'    => $data['alias'],
            'parent'   => $data['parent'],
            'sort'     => (int)$data['sort'],
            'top'      => empty($data['top']) ? 0 : 1,
            'status'   => empty($data['status']) ? 0 : 1,
        ];
        $dataUpdate = gp247_clean($dataUpdate, [], true);
        $category->update($dataUpdate);
        $category->descriptions()->delete();
        $dataDes = [];
        foreach ($data['descriptions'] as $code => $row) {
            $dataDes[] = [
                'category_id' => $id,
                'lang'        => $code,
                'title'       => $row['title'],
                'keyword'     => $row['keyword'],
                'description' => $row['description'],
            ];
        }
        $dataDes = gp247_clean($dataDes, [], true);
        AdminCategory::insertDescriptionAdmin($dataDes);

        if (gp247_store_check_multi_store_installed()) {
            // If multi-store
            $shopStore        = $data['shop_store'] ?? [];
            $category->stores()->detach();
            if ($shopStore) {
                $category->stores()->attach($shopStore);
            }
        }
        //Insert custom fields
        $fields = $data['fields'] ?? [];
        gp247_custom_field_update($fields, $category->id, 'shop_category');

        gp247_cache_clear('cache_category');

        //
        return redirect()->route('admin_category.index')->with('success', gp247_language_render('action.edit_success'));
    }

    /*
    Delete list Item
    Need mothod destroy to boot deleting in model
    */
    public function deleteList()
    {
        if (!request()->ajax()) {
            return response()->json(['error' => 1, 'msg' => gp247_language_render('admin.method_not_allow')]);
        } else {
            $ids = request('ids');
            $arrID = explode(',', $ids);
            $arrDontPermission = [];
            foreach ($arrID as $key => $id) {
                if (!$this->checkPermisisonItem($id)) {
                    $arrDontPermission[] = $id;
                }
            }
            if (count($arrDontPermission)) {
                return response()->json(['error' => 1, 'msg' => gp247_language_render('admin.remove_dont_permisison') . ': ' . json_encode($arrDontPermission)]);
            }
            AdminCategory::destroy($arrID);
            gp247_cache_clear('cache_category');
            return response()->json(['error' => 0, 'msg' => '']);
        }
    }

    /**
     * Check permisison item
     */
    public function checkPermisisonItem($id)
    {
        return AdminCategory::getCategoryAdmin($id);
    }
}
