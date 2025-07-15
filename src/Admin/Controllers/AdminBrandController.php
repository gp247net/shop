<?php
namespace GP247\Shop\Admin\Controllers;

use GP247\Core\Controllers\RootAdminController;
use GP247\Shop\Models\ShopBrand;
use GP247\Core\Models\AdminCustomField;
use Validator;

class AdminBrandController extends RootAdminController
{
    public function __construct()
    {
        parent::__construct();
    }
    public function index()
    {
        $brand = new ShopBrand;
        $data = [
            'title' => gp247_language_render('admin.brand.list'),
            'title_action' => '<i class="fa fa-plus" aria-hidden="true"></i> ' . gp247_language_render('admin.brand.add_new_title'),
            'subTitle' => '',
            'icon' => 'fa fa-indent',
            'urlDeleteItem' => gp247_route_admin('admin_brand.delete'),
            'removeList' => 0, // 1 - Enable function delete list item
            'buttonRefresh' => 0, // 1 - Enable button refresh
            'css' => '',
            'js' => '',
            'url_action' => gp247_route_admin('admin_brand.post_create'),
            'customFields'      => (new AdminCustomField)->getCustomField($type = 'shop_brand'),
        ];

        $listTh = [
            'name' => gp247_language_render('admin.brand.name'),
            'image' => gp247_language_render('admin.brand.image'),
            'status' => gp247_language_render('admin.brand.status'),
        ];

        $listTh['action'] = gp247_language_render('action.title');

        $obj = new ShopBrand;
        $obj = $obj->orderBy('id', 'desc');
        $dataTmp = $obj->paginate(20);

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $dataMap = [
                'name' => $row['name'],
                'image' => gp247_image_render($row->getThumb(), '50px', '', $row['name']),
                'status' => $row['status'] ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-danger">OFF</span>',
            ];
            $dataMap['action'] = '<a href="' . gp247_route_admin('admin_brand.edit', ['id' => $row['id'] ? $row['id'] : 'not-found-id']) . '"><span title="' . gp247_language_render('action.edit') . '" type="button" class="btn btn-flat btn-sm btn-primary"><i class="fa fa-edit"></i></span></a>&nbsp;
                                <span onclick="deleteItem(\'' . $row['id'] . '\');"  title="' . gp247_language_render('action.delete') . '" class="btn btn-flat btn-sm btn-danger"><i class="fas fa-trash-alt"></i></span>
                                ';
            $dataTr[$row['id']] = $dataMap;
        }

        $data['brand'] = $brand;

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('gp247-core::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);

        $data['layout'] = 'index';
        return view('gp247-shop-admin::screen.brand')
            ->with($data);
    }


    /**
     * Post create new item in admin
     * @return [type] [description]
     */
    public function postCreate()
    {
        $data = request()->all();

        $data['alias'] = !empty($data['alias'])?$data['alias']:$data['name'];
        $data['alias'] = gp247_word_format_url($data['alias']);
        $data['alias'] = gp247_word_limit($data['alias'], 100);
        $arrValidation = [
            'name'  => 'required|string|max:100',
            'alias' => 'required|unique:"'.ShopBrand::class.'",alias|string|max:100',
            'image' => 'required',
            'sort'  => 'numeric|min:0',
            'url'   => 'url|nullable',
        ];
        //Custom fields
        $customFields = (new AdminCustomField)->getCustomField($type = 'shop_brand');
        if ($customFields) {
            foreach ($customFields as $field) {
                if ($field->required) {
                    $arrValidation['fields.'.$field->code] = 'required';
                }
            }
        }

        $validator = Validator::make($data, $arrValidation, [
            'name.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('admin.brand.name')]),
            'alias.regex' => gp247_language_render('admin.brand.alias_validate'),
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($data);
        }
        $dataCreate = [
            'image' => $data['image'],
            'name' => $data['name'],
            'alias' => $data['alias'],
            'url' => $data['url'],
            'sort' => (int) $data['sort'],
            'status' => (!empty($data['status']) ? 1 : 0),
        ];
        $dataCreate = gp247_clean($dataCreate, [], true);
        $brand = ShopBrand::create($dataCreate);

        //Insert custom fields
        $fields = $data['fields'] ?? [];
        gp247_custom_field_update($fields, $brand->id, 'shop_brand');

        return redirect()->route('admin_brand.index')->with('success', gp247_language_render('action.create_success'));
    }

    /**
     * Form edit
     */
    public function edit($id)
    {
        $brand = ShopBrand::find($id);
        if (!$brand) {
            return 'No data';
        }
        $data = [
        'title' => gp247_language_render('admin.brand.list'),
        'title_action' => '<i class="fa fa-edit" aria-hidden="true"></i> ' . gp247_language_render('action.edit'),
        'subTitle' => '',
        'icon' => 'fa fa-indent',
        'urlDeleteItem' => gp247_route_admin('admin_brand.delete'),
        'removeList' => 0, // 1 - Enable function delete list item
        'buttonRefresh' => 0, // 1 - Enable button refresh
        'css' => '',
        'js' => '',
        'url_action' => gp247_route_admin('admin_brand.edit', ['id' => $brand['id']]),
        'brand' => $brand,
        'id' => $id,
        'customFields'      => (new AdminCustomField)->getCustomField($type = 'shop_brand'),
    ];

        $listTh = [
        'name' => gp247_language_render('admin.brand.name'),
        'image' => gp247_language_render('admin.brand.image'),
        'sort' => gp247_language_render('admin.brand.sort'),
        'status' => gp247_language_render('admin.brand.status'),
        'action' => gp247_language_render('action.title'),
    ];
        $obj = new ShopBrand;
        $obj = $obj->orderBy('id', 'desc');
        $dataTmp = $obj->paginate(20);

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $dataTr[$row['id']] = [
            'name' => $row['name'],
            'image' => gp247_image_render($row->getThumb(), '50px', '', $row['name']),
            'sort' => $row['sort'],
            'status' => $row['status'] ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-danger">OFF</span>',
            'action' => '
                <a href="' . gp247_route_admin('admin_brand.edit', ['id' => $row['id'] ? $row['id'] : 'not-found-id']) . '"><span title="' . gp247_language_render('action.edit') . '" type="button" class="btn btn-flat btn-sm btn-primary"><i class="fa fa-edit"></i></span></a>&nbsp;

              <span onclick="deleteItem(\'' . $row['id'] . '\');"  title="' . gp247_language_render('action.delete') . '" class="btn btn-flat btn-sm btn-danger"><i class="fas fa-trash-alt"></i></span>
              ',
        ];
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('gp247-core::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);

        $data['layout'] = 'edit';
        return view('gp247-shop-admin::screen.brand')
        ->with($data);
    }


    /**
     * update status
     */
    public function postEdit($id)
    {
        $brand = ShopBrand::find($id);
        $data = request()->all();
        $data['alias'] = !empty($data['alias'])?$data['alias']:$data['name'];
        $data['alias'] = gp247_word_format_url($data['alias']);
        $data['alias'] = gp247_word_limit($data['alias'], 100);
        $arrValidation = [
            'name'  => 'required|string|max:100',
            'alias' => 'required|unique:"'.ShopBrand::class.'",alias,' . $brand->id . ',id|string|max:100',
            'image' => 'required',
            'sort'  => 'numeric|min:0',
        ];
        //Custom fields
        $customFields = (new AdminCustomField)->getCustomField($type = 'shop_brand');
        if ($customFields) {
            foreach ($customFields as $field) {
                if ($field->required) {
                    $arrValidation['fields.'.$field->code] = 'required';
                }
            }
        }
        $validator = Validator::make($data, $arrValidation, [
            'name.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('admin.brand.name')]),
            'alias.regex' => gp247_language_render('admin.brand.alias_validate'),
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($data);
        }
        //Edit

        $dataUpdate = [
            'image' => $data['image'],
            'name' => $data['name'],
            'alias' => $data['alias'],
            'url' => $data['url'],
            'sort' => (int) $data['sort'],
            'status' => (!empty($data['status']) ? 1 : 0),

        ];
        $dataUpdate = gp247_clean($dataUpdate, [], true);
        $brand->update($dataUpdate);

        //Insert custom fields
        $fields = $data['fields'] ?? [];
        gp247_custom_field_update($fields, $brand->id, 'shop_brand');

        return redirect()->back()->with('success', gp247_language_render('action.edit_success'));
    }

    /*
    Delete list item
    Need mothod destroy to boot deleting in model
    */
    public function deleteList()
    {
        if (!request()->ajax()) {
            return response()->json(['error' => 1, 'msg' => gp247_language_render('admin.method_not_allow')]);
        } else {
            $ids = request('ids');
            $arrID = explode(',', $ids);
            ShopBrand::destroy($arrID);
            return response()->json(['error' => 0, 'msg' => '']);
        }
    }
}
