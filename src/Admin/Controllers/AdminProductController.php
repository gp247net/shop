<?php
namespace GP247\Shop\Admin\Controllers;

use GP247\Core\Controllers\RootAdminController;
use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Shop\Models\ShopBrand;
use GP247\Shop\Models\ShopTax;
use GP247\Core\Models\AdminLanguage;
use GP247\Shop\Models\ShopProductAttribute;
use GP247\Shop\Models\ShopProductBuild;
use GP247\Shop\Models\ShopProductGroup;
use GP247\Shop\Models\ShopProductImage;
use GP247\Shop\Models\ShopProductDescription;
use GP247\Shop\Models\ShopSupplier;
use GP247\Shop\Models\ShopProductStore;
use GP247\Shop\Models\ShopProductCategory;
use GP247\Shop\Models\ShopProductDownload;
use GP247\Core\Models\AdminCustomField;
use GP247\Shop\Admin\Models\AdminProduct;
use GP247\Core\Models\AdminStore;
use GP247\Shop\Admin\Models\AdminCategory;
use Illuminate\Support\Facades\Validator;
use DB;

class AdminProductController extends RootAdminController
{
    public $languages;
    public $tags;
    public $attributeGroup;
    public $listWeight;
    public $listLength;

    public function __construct()
    {
        parent::__construct();
        $this->languages       = AdminLanguage::getListActive();
        $this->listWeight      = explode(',', config('gp247-config.shop.product_weight_unit'));
        $this->listLength      = explode(',', config('gp247-config.shop.product_length_unit'));
        $this->tags            = explode(',', config('gp247-config.shop.product_tag'));
        $this->attributeGroup  = ShopAttributeGroup::getListAll();
    }

    public function kinds()
    {
        return [
            GP247_PRODUCT_SINGLE => gp247_language_render('product.kind_single'),
            GP247_PRODUCT_BUILD  => gp247_language_render('product.kind_bundle'),
            GP247_PRODUCT_GROUP  => gp247_language_render('product.kind_group'),
        ];
    }

    public function index()
    {
        $categoriesTitle = AdminCategory::getListTitleAdmin();
        $data = [
            'title'         => gp247_language_render('admin.product.list'),
            'subTitle'      => '',
            'urlDeleteItem' => gp247_route_admin('admin_product.delete'),
            'removeList'    => 1, // Enable function delete list item
            'buttonRefresh' => 1, // 1 - Enable button refresh
        ];
        //Process add content
        $data['menuRight']    = gp247_config_group('menuRight', \Request::route()->getName());
        $data['menuLeft']     = gp247_config_group('menuLeft', \Request::route()->getName());
        $data['topMenuRight'] = gp247_config_group('topMenuRight', \Request::route()->getName());
        $data['topMenuLeft']  = gp247_config_group('topMenuLeft', \Request::route()->getName());
        $data['blockBottom']  = gp247_config_group('blockBottom', \Request::route()->getName());

        $listTh = [
            'image'     => gp247_language_render('product.image'),
            'name'     => gp247_language_render('product.name'),
            'category' => gp247_language_render('product.category'),
        ];
        if (gp247_config_admin('product_cost')) {
            $listTh['cost'] = gp247_language_render('product.cost');
        }
        if (gp247_config_admin('product_price')) {
            $listTh['price'] = gp247_language_render('product.price');
        }
        if (gp247_config_admin('product_kind')) {
            $listTh['kind'] = gp247_language_render('product.kind');
        }

        if (((gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) && session('adminStoreId') == GP247_STORE_ID_ROOT)) {
            // Only show store info if store is root
            $listTh['shop_store'] = gp247_language_render('front.store_list');
        }
        $listTh['status'] = gp247_language_render('product.status');
        $listTh['approve'] = gp247_language_render('product.approve');
        $listTh['action'] = gp247_language_render('action.title');



        $keyword     = gp247_clean(request('keyword') ?? '');
        $category_id = gp247_clean(request('category_id') ?? '');
        $sort_order  = gp247_clean(request('sort_order') ?? 'id_desc');

        $arrSort = [
            'id__desc'   => gp247_language_render('filter_sort.id_desc'),
            'id__asc'    => gp247_language_render('filter_sort.id_asc'),
            'name__desc' => gp247_language_render('filter_sort.name_desc'),
            'name__asc'  => gp247_language_render('filter_sort.name_asc'),
        ];
        $dataSearch = [
            'keyword'     => $keyword,
            'category_id' => $category_id,
            'sort_order'  => $sort_order,
            'arrSort'     => $arrSort,
        ];

        $dataTmp = (new AdminProduct)->getProductListAdmin($dataSearch);
        $arrProductId = $dataTmp->pluck('id')->toArray();
        $categoriesTmp = (new AdminProduct)->getListCategoryIdFromProductId($arrProductId);

        if ((gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) && session('adminStoreId') == GP247_STORE_ID_ROOT) {
            // Only show store info if store is root
            $tableStore = (new AdminStore)->getTable();
            $tableProductStore = (new ShopProductStore)->getTable();
            $dataStores =  ShopProductStore::select($tableStore.'.code', $tableStore.'.id', 'product_id')
                ->join($tableStore, $tableStore.'.id', $tableProductStore.'.store_id')
                ->whereIn('product_id', $arrProductId)
                ->get()
                ->groupBy('product_id');
        }

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $kind = $this->kinds()[$row['kind']] ?? $row['kind'];
            if ($row['kind'] == GP247_PRODUCT_BUILD) {
                $kind = '<span class="badge badge-info">' . $kind . '</span>';
            } elseif ($row['kind'] == GP247_PRODUCT_GROUP) {
                $kind = '<span class="badge badge-warning">' . $kind . '</span>';
            }
            $arrName = [];
            $categoriesTmpRow = $categoriesTmp[$row['id']] ?? [];
            if ($categoriesTmpRow) {
            }
            foreach ($categoriesTmpRow as $category) {
                $arrName[] = $categoriesTitle[$category->category_id] ?? '';
            }

            $dataMap = [
                'image' => gp247_image_render($row->getThumb(), '50px', '50px', $row['name']),
                'name' => $row['name'].'<br><b>SKU:</b> '.$row['sku'],
                'category' => implode(';<br>', $arrName),
                
            ];
            if (gp247_config_admin('product_cost')) {
                $dataMap['cost'] = $row['cost'];
            }
            if (gp247_config_admin('product_price')) {
                $dataMap['price'] = $row['price'];
            }
            if (gp247_config_admin('product_kind')) {
                $dataMap['kind'] = $kind;
            }

            if ((gp247_store_check_multi_partner_installed() ||  gp247_store_check_multi_store_installed()) && session('adminStoreId') == GP247_STORE_ID_ROOT) {
                // Only show store info if store is root
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
                } else {
                    $dataMap['shop_store'] = '';
                }
            }
            
            $dataMap['status'] = $row['status'] ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-danger">OFF</span>';
            $dataMap['approve'] = $row['approve'] ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-danger">OFF</span>';


            $arrAction = [
                '<a href="' . gp247_route_admin('admin_product.edit', ['id' => $row['id'] ? $row['id'] : 'not-found-id']) . '"  class="dropdown-item"><i class="fa fa-edit"></i> '.gp247_language_render('action.edit').'</a>',
                ];
            if ($row['kind'] == GP247_PRODUCT_SINGLE) {
                $arrAction[] = '<a href="#" onclick="cloneItem(\'' . $row['id'] . '\', \'' . gp247_route_admin('admin_product.clone') . '\');"  title="' . gp247_language_render('action.clone') . '" class="dropdown-item"><i class="fa fa-clipboard"></i> '.gp247_language_render('action.clone').'</a>';
            }
            $arrAction[] = '<a href="#" onclick="deleteItem(\'' . $row['id'] . '\');"  title="' . gp247_language_render('action.delete') . '" class="dropdown-item"><i class="fas fa-trash-alt"></i> '.gp247_language_render('action.remove').'</a>';
            $arrAction[] = '<a href="'.gp247_route_front('product.detail', ['alias' => $row['alias']]).'" target=_new title="Link" class="dropdown-item"><i class="fas fa-external-link-alt"></i></a>';

            $action = $this->procesListAction($arrAction);

            $dataMap['action'] = $action;
            $dataTr[$row['id']] = $dataMap;
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('gp247-core::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);

        //menuRight
        $data['menuRight'][] = '<a href="' . gp247_route_admin('admin_product.create') . '" class="btn btn-success btn-flat" title="'.gp247_language_render('admin.product.add_new_title').'" id="button_create_new">
        <i class="fa fa-plus"></i>
        </a>';
        if (gp247_config_admin('product_kind')) {
            $data['menuRight'][] = '<a href="' . gp247_route_admin('admin_product.build_create') . '" class="btn btn-info btn-flat" title="'.gp247_language_render('admin.product.add_new_title_build').'" id="button_create_new">
            <i class="fas fa-puzzle-piece"></i>
            </a>';
            $data['menuRight'][] = '<a href="' . gp247_route_admin('admin_product.group_create') . '" class="btn btn-warning btn-flat" title="'.gp247_language_render('admin.product.add_new_title_group').'" id="button_create_new">
            <i class="fas fa-network-wired"></i>
            </a>';
        }
        //=menuRight

        //menuSort
        $optionSort = '';
        foreach ($arrSort as $key => $sort) {
            $optionSort .= '<option  ' . (($sort_order == $key) ? "selected" : "") . ' value="' . $key . '">' . $sort . '</option>';
        }
        //=menuSort

        //Search with category
        $optionCategory = '';
        $categories = (new AdminCategory)->getTreeCategoriesAdmin();
        if ($categories) {
            foreach ($categories as $k => $v) {
                $optionCategory .= "<option value='{$k}' ".(($category_id == $k) ? 'selected' : '').">{$v}</option>";
            }
        }

        //topMenuRight
        $data['topMenuRight'][] ='
                <form action="' . gp247_route_admin('admin_product.index') . '" id="button_search">
                <div class="input-group input-group float-left">
                    <select class="form-control rounded-0 select2" name="sort_order" id="sort_order">
                    '.$optionSort.'
                    </select> &nbsp;

                    <select class="form-control rounded-0 select2" name="category_id" id="category_id">
                    <option value="">'.gp247_language_render('admin.product.select_category').'</option>
                    '.$optionCategory.'
                    </select> &nbsp;
                    <input type="text" name="keyword" class="form-control rounded-0 float-right" placeholder="' . gp247_language_render('admin.product.search_place') . '" value="' . $keyword . '">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                </form>';
        //=topMenuRight

        return view('gp247-core::screen.list')
            ->with($data);
    }

    /**
     * Form create new item in admin
     * @return [type] [description]
     */
    public function create()
    {
        $product = [];
        $categories = (new AdminCategory)->getTreeCategoriesAdmin();
        // html add more images
        $htmlMoreImage = '<div class="input-group"><input type="text" id="id_sub_image" name="sub_image[]" value="image_value" class="form-control rounded-0 input-sm sub_image" placeholder=""  /><span class="input-group-btn"><a data-input="id_sub_image" data-preview="preview_sub_image" data-type="product" class="btn btn-primary lfm"><i class="fa fa-picture-o"></i> Choose</a></span></div><div id="preview_sub_image" class="img_holder"></div>';
        //end add more images

        // html select attribute
        $htmlProductAtrribute = '<tr><td><br><input type="text" name="attribute[attribute_group][name][]" value="attribute_value" class="form-control rounded-0 input-sm" placeholder="' . gp247_language_render('admin.product.add_attribute_place') . '" /></td><td><br><input type="number" step="0.01" name="attribute[attribute_group][add_price][]" value="add_price_value" class="form-control rounded-0 input-sm" placeholder="' . gp247_language_render('admin.product.add_price_place') . '"></td><td><br><span title="Remove" class="btn btn-flat btn-sm btn-danger removeAttribute"><i class="fa fa-times"></i></span></td></tr>';
        //end select attribute

        $data = [
            'title'                => gp247_language_render('admin.product.add_new_title'),
            'subTitle'             => '',
            'title_description'    => gp247_language_render('admin.product.add_new_des'),
            'languages'            => $this->languages,
            'categories'           => $categories,
            'brands'               => (new ShopBrand)->getListAll(),
            'suppliers'            => (new ShopSupplier)->getListAll(),
            'taxs'                 => (new ShopTax)->getListAll(),
            'tags'                 => $this->tags,
            'kinds'                => $this->kinds(),
            'attributeGroup'       => $this->attributeGroup,
            'htmlMoreImage'        => $htmlMoreImage,
            'htmlProductAtrribute' => $htmlProductAtrribute,
            'listWeight'           => $this->listWeight,
            'listLength'           => $this->listLength,
            'product'              => $product,
            'product_kind'         => GP247_PRODUCT_SINGLE,
            'customFields'         => (new AdminCustomField)->getCustomField($type = 'shop_product'),
        ];

        return view('gp247-shop-admin::screen.product_add')
            ->with($data);
    }

    /**
     * Form create new item in admin
     * @return [type] [description]
     */
    public function createProductBuild()
    {
        $product = [];
        $categories = (new AdminCategory)->getTreeCategoriesAdmin();

        $listProductSingle = (new AdminProduct)->getProductSelectAdmin(['kind' => [GP247_PRODUCT_SINGLE]]);

        // html select product build
        $htmlSelectBuild = '<div class="select-product">';
        $htmlSelectBuild .= '<table width="100%"><tr><td width="70%"><select class="form-control rounded-0 productInGroup select2" data-placeholder="' . gp247_language_render('admin.product.select_product_in_build') . '" style="width: 100%;" name="productBuild[]" >';
        $htmlSelectBuild .= '';
        foreach ($listProductSingle as $k => $v) {
            $htmlSelectBuild .= '<option value="' . $k . '">' . $v['name'] . '</option>';
        }
        $htmlSelectBuild .= '</select></td><td style="width:100px"><input class="form-control rounded-0"  type="number" name="productBuildQty[]" value="1" min=1></td><td><span title="Remove" class="btn btn-flat btn-sm btn-danger removeproductBuild"><i class="fa fa-times"></i></span></td></tr></table>';
        $htmlSelectBuild .= '</div>';
        //end select product build

        // html select attribute
        $htmlProductAtrribute = '<tr><td><br><input type="text" name="attribute[attribute_group][name][]" value="attribute_value" class="form-control rounded-0 input-sm" placeholder="' . gp247_language_render('admin.product.add_attribute_place') . '" /></td><td><br><input type="number" step="0.01" name="attribute[attribute_group][add_price][]" value="add_price_value" class="form-control rounded-0 input-sm" placeholder="' . gp247_language_render('admin.product.add_price_place') . '"></td><td><br><span title="Remove" class="btn btn-flat btn-sm btn-danger removeAttribute"><i class="fa fa-times"></i></span></td></tr>';
        //end select attribute

        // html add more images
        $htmlMoreImage = '<div class="input-group"><input type="text" id="id_sub_image" name="sub_image[]" value="image_value" class="form-control rounded-0 input-sm sub_image" placeholder=""  /><span class="input-group-btn"><a data-input="id_sub_image" data-preview="preview_sub_image" data-type="product" class="btn btn-primary lfm"><i class="fa fa-picture-o"></i> Choose</a></span></div><div id="preview_sub_image" class="img_holder"></div>';
        //end add more images


        $data = [
        'title'                => gp247_language_render('admin.product.add_new_title_build'),
        'subTitle'             => '',
        'title_description'    => gp247_language_render('admin.product.add_new_des'),
        'icon'                 => 'fa fa-plus',
        'languages'            => $this->languages,
        'categories'           => $categories,
        'brands'               => (new ShopBrand)->getListAll(),
        'suppliers'            => (new ShopSupplier)->getListAll(),
        'taxs'                 => (new ShopTax)->getListAll(),
        'tags'                 => $this->tags,
        'kinds'                => $this->kinds(),
        'attributeGroup'       => $this->attributeGroup,
        'product_kind'         => GP247_PRODUCT_BUILD,
        'product'              => $product,
        'htmlSelectBuild'      => $htmlSelectBuild,
        'listProductSingle'    => $listProductSingle,
        'htmlProductAtrribute' => $htmlProductAtrribute,
        'htmlMoreImage'        => $htmlMoreImage,
        'listWeight'           => $this->listWeight,
        'listLength'           => $this->listLength,
    ];

        return view('gp247-shop-admin::screen.product_add')
        ->with($data);
    }


    /**
     * Form create new item in admin
     * @return [type] [description]
     */
    public function createProductGroup()
    {
        $product = [];
        $categories = (new AdminCategory)->getTreeCategoriesAdmin();

        $listProductSingle = (new AdminProduct)->getProductSelectAdmin(['kind' => [GP247_PRODUCT_SINGLE]]);

        // html select product group
        $htmlSelectGroup = '<div class="select-product">';
        $htmlSelectGroup .= '<table width="100%"><tr><td width="80%"><select class="form-control rounded-0 productInGroup select2" data-placeholder="' . gp247_language_render('admin.product.select_product_in_group') . '" style="width: 100%;" name="productInGroup[]" >';
        $htmlSelectGroup .= '';
        foreach ($listProductSingle as $k => $v) {
            $htmlSelectGroup .= '<option value="' . $k . '">' . $v['name'] . '</option>';
        }
        $htmlSelectGroup .= '</select></td><td><span title="Remove" class="btn btn-flat btn-sm btn-danger removeproductInGroup"><i class="fa fa-times"></i></span></td></tr></table>';
        $htmlSelectGroup .= '</div>';
        //End select product group


        $data = [
        'title'                => gp247_language_render('admin.product.add_new_title_group'),
        'subTitle'             => '',
        'title_description'    => gp247_language_render('admin.product.add_new_des'),
        'icon'                 => 'fa fa-plus',
        'languages'            => $this->languages,
        'categories'           => $categories,
        'brands'               => (new ShopBrand)->getListAll(),
        'suppliers'            => (new ShopSupplier)->getListAll(),
        'taxs'                 => (new ShopTax)->getListAll(),
        'tags'                 => $this->tags,
        'kinds'                => $this->kinds(),
        'attributeGroup'       => $this->attributeGroup,
        'product_kind'         => GP247_PRODUCT_GROUP,
        'product'              => $product,
        'listProductSingle'    => $listProductSingle,
        'htmlSelectGroup'      => $htmlSelectGroup,
        'listWeight'           => $this->listWeight,
        'listLength'           => $this->listLength,
    ];

        return view('gp247-shop-admin::screen.product_add')
        ->with($data);
    }


    /**
     * Post create new item in admin
     * @return [type] [description]
     */

    public function postCreate()
    {
        $data = request()->all();
        $langFirst = array_key_first(gp247_language_all()->toArray()); //get first code language active
        $data['alias'] = !empty($data['alias'])?$data['alias']:$data['descriptions'][$langFirst]['name'];
        $data['alias'] = gp247_word_format_url($data['alias']);
        $data['alias'] = gp247_word_limit($data['alias'], 100);

        switch ($data['kind']) {
            case GP247_PRODUCT_SINGLE: // product single
                $arrValidation = [
                    'kind'                       => 'required',
                    'sort'                       => 'numeric|min:0',
                    'minimum'                    => 'numeric|min:0',
                    'descriptions.*.name'        => 'required|string|max:100',
                    'descriptions.*.keyword'     => 'nullable|string|max:100',
                    'descriptions.*.description' => 'nullable|string|max:100',
                    'descriptions.*.content'     => 'required|string',
                    'category'                   => 'required',
                    'sku'                        => 'required|product_sku_unique',
                    'alias'                      => 'required|string|max:120|product_alias_unique',
                ];


                $arrValidation = $this->validateAttribute($arrValidation);

                // Get custom field validation rules
                 $arrValidation = $this->getCustomFieldValidation($arrValidation, AdminProduct::class);

                
                $arrMsg = [
                    'descriptions.*.name.required'    => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.name')]),
                    'descriptions.*.content.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.content')]),
                    'category.required'               => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.category')]),
                    'sku.regex'                       => gp247_language_render('product.sku_validate'),
                    'sku.product_sku_unique'          => gp247_language_render('product.sku_unique'),
                    'alias.regex'                     => gp247_language_render('product.alias_validate'),
                    'alias.product_alias_unique'      => gp247_language_render('product.alias_unique'),
                ];
                break;

            case GP247_PRODUCT_BUILD: //product build
                $arrValidation = [
                    'kind'                       => 'required',
                    'sort'                       => 'numeric|min:0',
                    'minimum'                    => 'numeric|min:0',
                    'descriptions.*.name'        => 'required|string|max:100',
                    'descriptions.*.keyword'     => 'nullable|string|max:100',
                    'descriptions.*.description' => 'nullable|string|max:100',
                    'category'                   => 'required',
                    'sku'                        => 'required|product_sku_unique',
                    'alias'                      => 'required|string|max:120|product_alias_unique',
                    'productBuild'               => 'required',
                    'productBuildQty'            => 'required',
                ];

                $arrValidation = $this->validateAttribute($arrValidation);

                $arrMsg = [
                    'descriptions.*.name.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.name')]),
                    'category.required'            => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.category')]),
                    'sku.regex'                    => gp247_language_render('product.sku_validate'),
                    'sku.product_sku_unique'       => gp247_language_render('product.sku_unique'),
                    'alias.regex'                  => gp247_language_render('product.alias_validate'),
                    'alias.product_alias_unique'   => gp247_language_render('product.alias_unique'),
                ];
                break;

            case GP247_PRODUCT_GROUP: //product group
                $arrValidation = [
                    'kind'                       => 'required',
                    'productInGroup'             => 'required',
                    'sku'                        => 'required|product_sku_unique',
                    'alias'                      => 'required|string|max:120|product_alias_unique',
                    'sort'                       => 'numeric|min:0',
                    'category'                   => 'required',
                    'descriptions.*.name'        => 'required|string|max:200',
                    'descriptions.*.keyword'     => 'nullable|string|max:200',
                    'descriptions.*.description' => 'nullable|string|max:500',
                ];
                $arrMsg = [
                    'descriptions.*.name.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.name')]),
                    'sku.regex'                    => gp247_language_render('product.sku_validate'),
                    'category.required'            => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.category')]),
                    'sku.product_sku_unique'       => gp247_language_render('product.sku_unique'),
                    'alias.regex'                  => gp247_language_render('product.alias_validate'),
                    'alias.product_alias_unique'   => gp247_language_render('product.alias_unique'),
                ];
                break;

            default:
                $arrValidation = [
                    'kind' => 'required',
                ];
                break;
        }


        $validator = $this->validateWithCustomFields(
            $data, 
            $arrValidation,
            $arrMsg ?? []
        );


        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($data);
        }

        $category        = $data['category'] ?? [];
        $attribute       = $data['attribute'] ?? [];
        $descriptions    = $data['descriptions'];
        $productInGroup  = $data['productInGroup'] ?? [];
        $productBuild    = $data['productBuild'] ?? [];
        $productBuildQty = $data['productBuildQty'] ?? [];
        $subImages       = $data['sub_image'] ?? [];
        $downloadPath    = $data['download_path'] ?? '';
        $dataCreate = [
            'brand_id'       => $data['brand_id'] ?? "",
            'supplier_id'    => $data['supplier_id'] ?? "",
            'price'          => $data['price'] ?? 0,
            'sku'            => $data['sku'],
            'cost'           => $data['cost'] ?? 0,
            'stock'          => $data['stock'] ?? 0,
            'weight_class'   => $data['weight_class'] ?? '',
            'length_class'   => $data['length_class'] ?? '',
            'weight'         => $data['weight'] ?? 0,
            'height'         => $data['height'] ?? 0,
            'length'         => $data['length'] ?? 0,
            'width'          => $data['width'] ?? 0,
            'kind'           => $data['kind'] ?? GP247_PRODUCT_SINGLE,
            'alias'          => $data['alias'],
            'tag'            => $data['tag'] ?? "",
            'image'          => $data['image'] ?? '',
            'tax_id'         => $data['tax_id'] ?? "",
            'status'         => (!empty($data['status']) ? 1 : 0),
            'approve'        => (!empty($data['approve']) ? 1 : 0),
            'sort'           => (int) $data['sort'],
            'minimum'        => (int) ($data['minimum'] ?? 0),
        ];

        if (!empty($data['date_available'])) {
            $dataCreate['date_available'] = $data['date_available'];
        }

        try {
            DB::connection(GP247_DB_CONNECTION)->beginTransaction();
            //insert product
            $dataCreate = gp247_clean($dataCreate, [], true);
            $product = AdminProduct::createProductAdmin($dataCreate);

            //Promoton price
            if ((isset($data['promotion_use']) && $data['promotion_use'] == 'on') && in_array($data['kind'], [GP247_PRODUCT_SINGLE, GP247_PRODUCT_BUILD])) {
                $arrPromotion['price_promotion'] = $data['price_promotion'];
                $arrPromotion['date_start'] = $data['price_promotion_start'] ? $data['price_promotion_start'] : null;
                $arrPromotion['date_end'] = $data['price_promotion_end'] ? $data['price_promotion_end'] : null;
                $arrPromotion = gp247_clean($arrPromotion, [], true);
                $product->promotionPrice()->create($arrPromotion);
            }

            //Insert category
            if ($category) {
                $product->categories()->attach($category);
            }

            $shopStore        = $data['shop_store'] ?? [session('adminStoreId')];
            if ($shopStore) {
                $product->stores()->attach($shopStore);
            }

            //Insert group
            if ($productInGroup && $data['kind'] == GP247_PRODUCT_GROUP) {
                $arrDataGroup = [];
                foreach ($productInGroup as $pID) {
                    if ($pID) {
                        $arrDataGroup[$pID] = new ShopProductGroup(['product_id' => $pID]);
                    }
                }
                $product->groups()->saveMany($arrDataGroup);
            }

            //Insert Build
            if ($productBuild && $data['kind'] == GP247_PRODUCT_BUILD) {
                $arrDataBuild = [];
                foreach ($productBuild as $key => $pID) {
                    if ($pID) {
                        $arrDataBuild[$pID] = new ShopProductBuild(['product_id' => $pID, 'quantity' => $productBuildQty[$key]]);
                    }
                }
                $product->builds()->saveMany($arrDataBuild);
            }

            //Insert attribute
            if ($attribute && $data['kind'] == GP247_PRODUCT_SINGLE) {
                $arrDataAtt = [];
                foreach ($attribute as $group => $rowGroup) {
                    if (count($rowGroup)) {
                        foreach ($rowGroup['name'] as $key => $nameAtt) {
                            if ($nameAtt) {
                                $dataAtt = gp247_clean(['name' => $nameAtt, 'add_price' => $rowGroup['add_price'][$key],  'attribute_group_id' => $group], [], true);
                                $arrDataAtt[] = new ShopProductAttribute($dataAtt);
                            }
                        }
                    }
                }
                $product->attributes()->saveMany($arrDataAtt);
            }

            //Insert path download
            if (!empty($data['tag']) && $data['tag'] == GP247_TAG_DOWNLOAD && $downloadPath) {
                $dataDownload = gp247_clean(['product_id' => $product->id, 'path' => $downloadPath], [], true);
                $dataDownload['id'] = gp247_generate_id();
                ShopProductDownload::insert($dataDownload);
            }

            //Insert custom fields
            $fields = $data['fields'] ?? [];
            gp247_custom_field_update($fields, $product->id, 'shop_product');

            //Insert description
            $dataDes = [];
            $languages = $this->languages;
            foreach ($languages as $code => $value) {
                $dataDes[] = gp247_clean([
                    'product_id'  => $product->id,
                    'lang'        => $code,
                    'name'        => $descriptions[$code]['name'],
                    'keyword'     => $descriptions[$code]['keyword'],
                    'description' => $descriptions[$code]['description'],
                    'content'     => $descriptions[$code]['content'] ?? '',
                ], ['content'], true);
            }

            AdminProduct::insertDescriptionAdmin($dataDes);

            //Insert sub mages
            if ($subImages && in_array($data['kind'], [GP247_PRODUCT_SINGLE, GP247_PRODUCT_BUILD])) {
                $arrSubImages = [];
                foreach ($subImages as $key => $image) {
                    if ($image) {
                        $arrSubImages[] = new ShopProductImage(gp247_clean(['image' => $image], [], true));
                    }
                }
                $product->images()->saveMany($arrSubImages);
            }

            gp247_cache_clear('cache_product');
            DB::connection(GP247_DB_CONNECTION)->commit();
            return redirect()->route('admin_product.index')->with('success', gp247_language_render('admin.product.create_success'));
        } catch (\Exception $e) {
            DB::connection(GP247_DB_CONNECTION)->rollBack();
            return redirect()->back()->withInput($data)->with('error', $e->getMessage());
        }
    }

    /*
    * Form edit
    */
    public function edit($id)
    {
        $product = (new AdminProduct)->getProductAdmin($id);
        
        if ($product === null) {
            return redirect()->route('admin.data_not_found')->with(['url' => url()->full()]);
        }
        
        $categories = (new AdminCategory)->getTreeCategoriesAdmin();
        
        $listProductSingle = (new AdminProduct)->getProductSelectAdmin(['kind' => [GP247_PRODUCT_SINGLE]]);

        // html select product group
        $htmlSelectGroup = '<div class="select-product">';
        $htmlSelectGroup .= '<table width="100%"><tr><td width="80%"><select class="form-control rounded-0 productInGroup select2" data-placeholder="' . gp247_language_render('admin.product.select_product_in_group') . '" style="width: 100%;" name="productInGroup[]" >';
        $htmlSelectGroup .= '';
        foreach ($listProductSingle as $k => $v) {
            $htmlSelectGroup .= '<option value="' . $k . '">' . $v['name'] . '</option>';
        }
        $htmlSelectGroup .= '</select></td><td><span title="Remove" class="btn btn-flat btn-sm btn-danger removeproductInGroup"><i class="fa fa-times"></i></span></td></tr></table>';
        $htmlSelectGroup .= '</div>';
        //End select product group

        // html select product build
        $htmlSelectBuild = '<div class="select-product">';
        $htmlSelectBuild .= '<table width="100%"><tr><td width="70%"><select class="form-control rounded-0 productInGroup select2" data-placeholder="' . gp247_language_render('admin.product.select_product_in_build') . '" style="width: 100%;" name="productBuild[]" >';
        $htmlSelectBuild .= '';
        foreach ($listProductSingle as $k => $v) {
            $htmlSelectBuild .= '<option value="' . $k . '">' . $v['name'] . '</option>';
        }
        $htmlSelectBuild .= '</select></td><td style="width:100px"><input class="form-control rounded-0"  type="number" name="productBuildQty[]" value="1" min=1></td><td><span title="Remove" class="btn btn-flat btn-sm btn-danger removeproductBuild"><i class="fa fa-times"></i></span></td></tr></table>';
        $htmlSelectBuild .= '</div>';
        //end select product build

        // html select attribute
        $htmlProductAtrribute = '<tr><td><br><input type="text" name="attribute[attribute_group][name][]" value="attribute_value" class="form-control rounded-0 input-sm" placeholder="' . gp247_language_render('admin.product.add_attribute_place') . '" /></td><td><br><input type="number" step="0.01" name="attribute[attribute_group][add_price][]" value="add_price_value" class="form-control rounded-0 input-sm" placeholder="' . gp247_language_render('admin.product.add_price_place') . '"></td><td><br><span title="Remove" class="btn btn-flat btn-sm btn-danger removeAttribute"><i class="fa fa-times"></i></span></td></tr>';
        //end select attribute


        $data = [
            'title'                => gp247_language_render('admin.product.edit'),
            'subTitle'             => '',
            'title_description'    => '',
            'icon'                 => 'fa fa-edit',
            'languages'            => $this->languages,
            'product'              => $product,
            'categories'           => $categories,
            'brands'               => (new ShopBrand)->getListAll(),
            'suppliers'            => (new ShopSupplier)->getListAll(),
            'taxs'                 => (new ShopTax)->getListAll(),
            'tags'                 => $this->tags,
            'kinds'                => $this->kinds(),
            'attributeGroup'       => $this->attributeGroup,
            'htmlSelectGroup'      => $htmlSelectGroup,
            'htmlSelectBuild'      => $htmlSelectBuild,
            'listProductSingle'    => $listProductSingle,
            'htmlProductAtrribute' => $htmlProductAtrribute,
            'listWeight'           => $this->listWeight,
            'listLength'           => $this->listLength,

        ];

        //Only prduct single have custom field
        if ($product->kind == GP247_PRODUCT_SINGLE) {
            $data['customFields'] = (new AdminCustomField)->getCustomField($type = 'shop_product');
        } else {
            $data['customFields'] = [];
        }
        return view('gp247-shop-admin::screen.product_edit')
            ->with($data);
    }


    public function postEdit($id)
    {
        $product = (new AdminProduct)->getProductAdmin($id);
        if ($product === null) {
            return redirect()->route('admin.data_not_found')->with(['url' => url()->full()]);
        }
        $data = request()->all();
        $langFirst = array_key_first(gp247_language_all()->toArray()); //get first code language active
        $data['alias'] = !empty($data['alias'])?$data['alias']:$data['descriptions'][$langFirst]['name'];
        $data['alias'] = gp247_word_format_url($data['alias']);
        $data['alias'] = gp247_word_limit($data['alias'], 100);

        switch ($product['kind']) {
            case GP247_PRODUCT_SINGLE: // product single
                $arrValidation = [
                    'sort' => 'numeric|min:0',
                    'minimum' => 'numeric|min:0',
                    'descriptions.*.name' => 'required|string|max:200',
                    'descriptions.*.keyword' => 'nullable|string|max:200',
                    'descriptions.*.description' => 'nullable|string|max:500',
                    'descriptions.*.content' => 'required|string',
                    'category' => 'required',
                    'sku' => 'required|product_sku_unique:'.$id,
                    'alias' => 'required|string|max:120|product_alias_unique:'.$id,
                ];


                // Get custom field validation rules
                $arrValidation = $this->getCustomFieldValidation($arrValidation, AdminProduct::class);

                $arrValidation = $this->validateAttribute($arrValidation);

                $arrMsg = [
                    'descriptions.*.name.required'    => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.name')]),
                    'descriptions.*.content.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.content')]),
                    'category.required'               => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.category')]),
                    'sku.regex'                       => gp247_language_render('product.sku_validate'),
                    'sku.product_sku_unique'          => gp247_language_render('product.sku_unique'),
                    'alias.regex'                     => gp247_language_render('product.alias_validate'),
                    'alias.product_alias_unique'      => gp247_language_render('product.alias_unique'),
                ];
                break;
            case GP247_PRODUCT_BUILD: //product build
                $arrValidation = [
                    'sort' => 'numeric|min:0',
                    'minimum' => 'numeric|min:0',
                    'descriptions.*.name' => 'required|string|max:200',
                    'descriptions.*.keyword' => 'nullable|string|max:200',
                    'descriptions.*.description' => 'nullable|string|max:500',
                    'category' => 'required',
                    'sku' => 'required|product_sku_unique:'.$id,
                    'alias' => 'required|string|max:120|product_alias_unique:'.$id,
                    'productBuild' => 'required',
                    'productBuildQty' => 'required',
                ];

                $arrValidation = $this->validateAttribute($arrValidation);
                
                $arrMsg = [
                    'descriptions.*.name.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.name')]),
                    'category.required'            => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.category')]),
                    'sku.regex'                    => gp247_language_render('product.sku_validate'),
                    'sku.product_sku_unique'       => gp247_language_render('product.sku_unique'),
                    'alias.regex'                  => gp247_language_render('product.alias_validate'),
                    'alias.product_alias_unique'   => gp247_language_render('product.alias_unique'),
                ];
                break;

            case GP247_PRODUCT_GROUP: //product group
                $arrValidation = [
                    'sku' => 'required|product_sku_unique:'.$id,
                    'alias' => 'required|string|max:120|product_alias_unique:'.$id,
                    'productInGroup' => 'required',
                    'category' => 'required',
                    'sort' => 'numeric|min:0',
                    'descriptions.*.name' => 'required|string|max:200',
                    'descriptions.*.keyword' => 'nullable|string|max:200',
                    'descriptions.*.description' => 'nullable|string|max:500',
                ];
                $arrMsg = [
                    'sku.regex'                    => gp247_language_render('product.sku_validate'),
                    'sku.product_sku_unique'       => gp247_language_render('product.sku_unique'),
                    'category.required'            => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.category')]),
                    'alias.regex'                  => gp247_language_render('product.alias_validate'),
                    'alias.product_alias_unique'   => gp247_language_render('product.alias_unique'),
                    'descriptions.*.name.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('product.name')]),
                ];
                break;

            default:
                break;
        }

        $validator = $this->validateWithCustomFields(
            $data, 
            $arrValidation,
            $arrMsg ?? []
        );

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($data);
        }
        //Edit

        $category        = $data['category'] ?? [];
        $attribute       = $data['attribute'] ?? [];
        $productInGroup  = $data['productInGroup'] ?? [];
        $productBuild    = $data['productBuild'] ?? [];
        $productBuildQty = $data['productBuildQty'] ?? [];
        $subImages       = $data['sub_image'] ?? [];
        $downloadPath    = $data['download_path'] ?? '';
        $dataUpdate = [
            'image'        => $data['image'] ?? '',
            'tax_id'       => $data['tax_id'] ?? "",
            'brand_id'     => $data['brand_id'] ?? "",
            'supplier_id'  => $data['supplier_id'] ?? "",
            'price'        => $data['price'] ?? 0,
            'cost'         => $data['cost'] ?? 0,
            'stock'        => $data['stock'] ?? 0,
            'weight_class' => $data['weight_class'] ?? '',
            'length_class' => $data['length_class'] ?? '',
            'weight'       => $data['weight'] ?? 0,
            'height'       => $data['height'] ?? 0,
            'length'       => $data['length'] ?? 0,
            'width'        => $data['width'] ?? 0,
            'tag'          => $data['tag'] ?? "",
            'sku'          => $data['sku'],
            'alias'        => $data['alias'],
            'status'       => (!empty($data['status']) ? 1 : 0),
            'approve'       => (!empty($data['approve']) ? 1 : 0),
            'sort'         => (int) $data['sort'],
            'minimum'      => (int) ($data['minimum'] ?? 0)
        ];
        if (!empty($data['date_available'])) {
            $dataUpdate['date_available'] = $data['date_available'];
        }
        $dataUpdate = gp247_clean($dataUpdate, [], true);

        try {
            
            DB::connection(GP247_DB_CONNECTION)->beginTransaction();
            $product->update($dataUpdate);

            $shopStore        = $data['shop_store'] ?? [session('adminStoreId')];
            $product->stores()->detach();
            if ($shopStore) {
                $product->stores()->attach($shopStore);
            }

            //Update custom field
            $fields = $data['fields'] ?? [];
            gp247_custom_field_update($fields, $product->id, 'shop_product');


            //Promoton price
            $product->promotionPrice()->delete();
            if ((isset($data['promotion_use']) && $data['promotion_use'] == 'on') && in_array($product['kind'], [GP247_PRODUCT_SINGLE, GP247_PRODUCT_BUILD])) {
                $arrPromotion['price_promotion'] = $data['price_promotion'];
                $arrPromotion['date_start'] = $data['price_promotion_start'] ? $data['price_promotion_start'] : null;
                $arrPromotion['date_end'] = $data['price_promotion_end'] ? $data['price_promotion_end'] : null;
                $arrPromotion = gp247_clean($arrPromotion, [], true);
                $product->promotionPrice()->create($arrPromotion);
            }

            $product->descriptions()->delete();
            $dataDes = [];
            foreach ($data['descriptions'] as $code => $row) {
                $dataDes[] = gp247_clean([
                    'product_id' => $id,
                    'lang' => $code,
                    'name' => $row['name'],
                    'keyword' => $row['keyword'],
                    'description' => $row['description'],
                    'content' => $row['content'] ?? '',
                ], ['content'], true);
            }
            AdminProduct::insertDescriptionAdmin($dataDes);

            $product->categories()->detach();
            if (count($category)) {
                $product->categories()->attach($category);
            }

            //Update group
            if ($product['kind'] == GP247_PRODUCT_GROUP) {
                $product->groups()->delete();
                if (count($productInGroup)) {
                    $arrDataGroup = [];
                    foreach ($productInGroup as $pID) {
                        if ($pID) {
                            $arrDataGroup[$pID] = new ShopProductGroup(['product_id' => $pID]);
                        }
                    }
                    $product->groups()->saveMany($arrDataGroup);
                }
            }

            //Update Build
            if ($product['kind'] == GP247_PRODUCT_BUILD) {
                $product->builds()->delete();
                if (count($productBuild)) {
                    $arrDataBuild = [];
                    foreach ($productBuild as $key => $pID) {
                        if ($pID) {
                            $arrDataBuild[$pID] = new ShopProductBuild(['product_id' => $pID, 'quantity' => $productBuildQty[$key]]);
                        }
                    }
                    $product->builds()->saveMany($arrDataBuild);
                }
            }

            //Update path download
            (new ShopProductDownload)->where('product_id', $product->id)->delete();
            if ($product['tag'] == GP247_TAG_DOWNLOAD && $downloadPath) {
                $dataDownload = gp247_clean(['product_id' => $product->id, 'path' => $downloadPath], [], true);
                $dataDownload['id'] = gp247_generate_id();       
                ShopProductDownload::insert($dataDownload);
            }


            //Update attribute
            if ($product['kind'] == GP247_PRODUCT_SINGLE) {
                $product->attributes()->delete();
                if (count($attribute)) {
                    $arrDataAtt = [];
                    foreach ($attribute as $group => $rowGroup) {
                        if (count($rowGroup)) {
                            foreach ($rowGroup['name'] as $key => $nameAtt) {
                                if ($nameAtt) {
                                    $dataAtt = gp247_clean(['name' => $nameAtt, 'add_price' => $rowGroup['add_price'][$key], 'attribute_group_id' => $group], [], true);
                                    $arrDataAtt[] = new ShopProductAttribute($dataAtt);
                                }
                            }
                        }
                    }
                    $product->attributes()->saveMany($arrDataAtt);
                }
            }

            //Update sub mages
            if (in_array($product['kind'], [GP247_PRODUCT_SINGLE, GP247_PRODUCT_BUILD])) {
                $product->images()->delete();
                if ($subImages) {
                    $arrSubImages = [];
                    foreach ($subImages as $key => $image) {
                        if ($image) {
                            $arrSubImages[] = new ShopProductImage(gp247_clean(['image' => $image], [], true));
                        }
                    }
                    $product->images()->saveMany($arrSubImages);
                }
            }

            gp247_cache_clear('cache_product');
            DB::connection(GP247_DB_CONNECTION)->commit();
            return redirect()->route('admin_product.index')->with('success', gp247_language_render('admin.product.edit_success'));
        } catch (\Exception $e) {
            DB::connection(GP247_DB_CONNECTION)->rollBack();
            return redirect()->back()->withInput($data)->with('error', $e->getMessage());
        }
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
            $arrCantDelete = [];
            $arrDontPermission = [];
            $arrDelete = [];
            foreach ($arrID as $key => $id) {
                if (!$this->checkPermisisonItem($id)) {
                    $arrDontPermission[] = $id;
                } elseif (ShopProductBuild::where('product_id', $id)->first() || ShopProductGroup::where('product_id', $id)->first()) {
                    $arrCantDelete[] = $id;
                } else {
                    $arrDelete[] = $id;
                }
            }
            if ($arrDelete) {
                AdminProduct::destroy($arrDelete);
                gp247_cache_clear('cache_product');
            }

            if (count($arrDontPermission)) {
                return response()->json(['error' => 1, 'msg' => gp247_language_render('admin.remove_dont_permisison') . ': ' . json_encode($arrDontPermission)]);
            } elseif (count($arrCantDelete)) {
                return response()->json(['error' => 1, 'msg' => gp247_language_render('admin.product.cant_remove_child') . ': ' . json_encode($arrCantDelete)]);
            } else {
                return response()->json(['error' => 0, 'msg' => '']);
            }
        }
    }

    /**
     * Validate attribute product
     */
    public function validateAttribute(array $arrValidation)
    {
        if (gp247_config_admin('product_brand')) {
            if (gp247_config_admin('product_brand_required')) {
                $arrValidation['brand_id'] = 'required';
            } else {
                $arrValidation['brand_id'] = 'nullable';
            }
        }

        if (gp247_config_admin('product_supplier')) {
            if (gp247_config_admin('product_supplier_required')) {
                $arrValidation['supplier_id'] = 'required';
            } else {
                $arrValidation['supplier_id'] = 'nullable';
            }
        }

        if (gp247_config_admin('product_price')) {
            if (gp247_config_admin('product_price_required')) {
                $arrValidation['price'] = 'required|numeric|min:0';
            } else {
                $arrValidation['price'] = 'nullable|numeric|min:0';
            }
        }

        if (gp247_config_admin('product_cost')) {
            if (gp247_config_admin('product_cost_required')) {
                $arrValidation['cost'] = 'required|numeric|min:0';
            } else {
                $arrValidation['cost'] = 'nullable|numeric|min:0';
            }
        }

        if (gp247_config_admin('product_promotion')) {
            if (gp247_config_admin('product_promotion_required')) {
                $arrValidation['price_promotion'] = 'required|numeric|min:0';
            } else {
                $arrValidation['price_promotion'] = 'nullable|numeric|min:0';
            }
        }

        if (gp247_config_admin('product_stock')) {
            if (gp247_config_admin('product_stock_required')) {
                $arrValidation['stock'] = 'required|numeric';
            } else {
                $arrValidation['stock'] = 'nullable|numeric';
            }
        }

        if (gp247_config_admin('product_tag')) {
            if (gp247_config_admin('product_tag_required')) {
                $arrValidation['tag'] = 'required|string';
            } else {
                $arrValidation['tag'] = 'nullable|string';
            }
        }

        if (gp247_config_admin('product_available')) {
            if (gp247_config_admin('product_available_required')) {
                $arrValidation['date_available'] = 'required|date';
            } else {
                $arrValidation['date_available'] = 'nullable|date';
            }
        }

        if (gp247_config_admin('product_weight')) {
            if (gp247_config_admin('product_weight_required')) {
                $arrValidation['weight'] = 'required|numeric';
                $arrValidation['weight_class'] = 'required|string';
            } else {
                $arrValidation['weight'] = 'nullable|numeric';
                $arrValidation['weight_class'] = 'nullable|string';
            }
        }

        if (gp247_config_admin('product_length')) {
            if (gp247_config_admin('product_length_required')) {
                $arrValidation['length_class'] = 'required|string';
                $arrValidation['length'] = 'required|numeric|min:0';
                $arrValidation['width'] = 'required|numeric|min:0';
                $arrValidation['height'] = 'required|numeric|min:0';
            } else {
                $arrValidation['length_class'] = 'nullable|string';
                $arrValidation['length'] = 'nullable|numeric|min:0';
                $arrValidation['width'] = 'nullable|numeric|min:0';
                $arrValidation['height'] = 'nullable|numeric|min:0';
            }
        }
        return $arrValidation;
    }

    /**
     * Check permisison item
     */
    public function checkPermisisonItem($id)
    {
        return (new AdminProduct)->getProductAdmin($id);
    }

    /**
     * Clone product
     * Only clone single product
     * @return  [type]  [return description]
     */
    public function cloneProduct() {

        if (!request()->ajax()) {
            return response()->json(['error' => 1, 'msg' => gp247_language_render('admin.method_not_allow')]);
        }
        $pId = request('pId');
        $product = AdminProduct::find($pId);
        if (!$product) {
            return response()->json(['error' => 1, 'msg' => 'Product not found']);
       }
        if ($product->kind != GP247_PRODUCT_SINGLE) {
            return response()->json(['error' => 1, 'msg' => 'Only clone product single']);
        }
        try {
            DB::connection(GP247_DB_CONNECTION)->beginTransaction();
            //Product info
            $dataProduct = \Illuminate\Support\Arr::except($product->toArray(), ['id', 'created_at', 'updated_at']);
            $dataProduct['sku'] = $dataProduct['sku'].'-'.time();
            $dataProduct['alias'] = $dataProduct['alias'].'-'.time();
            $newProduct = AdminProduct::create($dataProduct);

            //Product description
            $productDescription = $product->descriptions->toArray();
            $newDescription = [];
            foreach ($productDescription as $key => $row) {
                $row['product_id'] = $newProduct->id;
                $row['name'] = $row['name'].'- '.time();
                $newDescription[] = $row;
            }
            ShopProductDescription::insert($newDescription);

            //Product category
            $productCategory = (new ShopProductCategory)->where('product_id', $product->id)->get()->toArray();
            $newCategory = [];
            foreach ($productCategory as $key => $row) {
                $row['product_id'] = $newProduct->id;
                $newCategory[] = $row;
            }
            ShopProductCategory::insert($newCategory);


            //Product store
            $productStore = (new ShopProductStore)->where('product_id', $product->id)->get()->toArray();
            $newStore = [];
            foreach ($productStore as $key => $row) {
                $row['product_id'] = $newProduct->id;
                $newStore[] = $row;
            }
            ShopProductStore::insert($newStore);

            DB::connection(GP247_DB_CONNECTION)->commit();
            return response()->json(['error' => 0, 'msg' => gp247_language_render('ac_success')]);
        } catch (\Throwable $e) {
            DB::connection(GP247_DB_CONNECTION)->rollBack();
            return response()->json(['error' => 1, 'msg' => $e->getMessage()]);
        }
       
    }
}
