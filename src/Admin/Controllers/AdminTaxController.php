<?php
namespace GP247\Shop\Admin\Controllers;

use GP247\Core\Controllers\RootAdminController;
use GP247\Shop\Models\ShopTax;
use Validator;

class AdminTaxController extends RootAdminController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index()
    {
        $data = [
            'title' => gp247_language_render('admin.tax.list'),
            'title_action' => '<i class="fa fa-plus" aria-hidden="true"></i> ' . gp247_language_render('admin.tax.add_new_title'),
            'subTitle' => '',
            'icon' => 'fa fa-indent',
            'urlDeleteItem' => gp247_route_admin('admin_tax.delete'),
            'removeList' => 0, // 1 - Enable function delete list item
            'buttonRefresh' => 0, // 1 - Enable button refresh
            'css' => '',
            'js' => '',
            'url_action' => gp247_route_admin('admin_tax.post_create'),
        ];

        $listTh = [
            'value' => gp247_language_render('admin.tax.value'),
            'name' => gp247_language_render('admin.tax.name'),
            'action' => gp247_language_render('action.title'),
        ];
        $obj = new ShopTax;
        $obj = $obj->orderBy('id', 'desc');
        $dataTmp = $obj->paginate(20);

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $dataTr[$row['id']] = [
                'name' => $row['name'],
                'value' => $row['value'],
                'action' => '
                    <a href="' . gp247_route_admin('admin_tax.edit', ['id' => $row['id'] ? $row['id'] : 'not-found-id']) . '"><span title="' . gp247_language_render('action.edit') . '" type="button" class="btn btn-flat btn-sm btn-primary"><i class="fa fa-edit"></i></span></a>&nbsp;
                  <span onclick="deleteItem(\'' . $row['id'] . '\');"  title="' . gp247_language_render('action.delete') . '" class="btn btn-flat btn-sm btn-danger"><i class="fas fa-trash-alt"></i></span>
                  ',
            ];
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('gp247-core::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);

        $data['layout'] = 'index';
        return view('gp247-shop-admin::screen.tax')
            ->with($data);
    }


    /**
     * Post create new item in admin
     * @return [type] [description]
     */
    public function postCreate()
    {
        $data = request()->all();

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'value' => 'numeric|min:0',
        ], [
            'name.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('admin.tax.name')]),
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($data);
        }

        $dataCreate = [
            'value' => $data['value'],
            'name' => $data['name'],
        ];
        $dataCreate = gp247_clean($dataCreate, [], true);
        $obj = ShopTax::create($dataCreate);

        return redirect()->route('admin_tax.index')->with('success', gp247_language_render('action.create_success'));
    }

    /**
     * Form edit
     */
    public function edit($id)
    {
        $tax = ShopTax::find($id);
        if (!$tax) {
            return 'No data';
        }
        $data = [
            'title' => gp247_language_render('admin.tax.list'),
            'title_action' => '<i class="fa fa-edit" aria-hidden="true"></i> ' . gp247_language_render('action.edit'),
            'subTitle' => '',
            'icon' => 'fa fa-indent',
            'urlDeleteItem' => gp247_route_admin('admin_tax.delete'),
            'removeList' => 0, // 1 - Enable function delete list item
            'buttonRefresh' => 0, // 1 - Enable button refresh
            'css' => '',
            'js' => '',
            'url_action' => gp247_route_admin('admin_tax.edit', ['id' => $tax['id']]),
            'tax' => $tax,
            'id' => $id,
        ];

        $listTh = [
            'name' => gp247_language_render('admin.tax.name'),
            'value' => gp247_language_render('admin.tax.value'),
            'action' => gp247_language_render('action.title'),
        ];
        $obj = new ShopTax;
        $obj = $obj->orderBy('id', 'desc');
        $dataTmp = $obj->paginate(20);

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $dataTr[$row['id']] = [
                'name' => $row['name'],
                'value' => $row['value'],
                'action' => '
                    <a href="' . gp247_route_admin('admin_tax.edit', ['id' => $row['id'] ? $row['id'] : 'not-found-id']) . '"><span title="' . gp247_language_render('action.edit') . '" type="button" class="btn btn-flat btn-sm btn-primary"><i class="fa fa-edit"></i></span></a>&nbsp;
                <span onclick="deleteItem(\'' . $row['id'] . '\');"  title="' . gp247_language_render('action.delete') . '" class="btn btn-flat btn-sm btn-danger"><i class="fas fa-trash-alt"></i></span>
                ',
            ];
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('gp247-core::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);

        $data['layout'] = 'edit';
        return view('gp247-shop-admin::screen.tax')
            ->with($data);
    }


    /**
     * update status
     */
    public function postEdit($id)
    {
        $tax = ShopTax::find($id);
        $data = request()->all();
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'value' => 'numeric|min:0',
        ], [
            'name.required' => gp247_language_render('validation.required', ['attribute' => gp247_language_render('admin.tax.name')]),
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($data);
        }
        //Edit

        $dataUpdate = [
            'value' => $data['value'],
            'name' => $data['name'],
        ];
        $dataUpdate = gp247_clean($dataUpdate, [], true);
        $tax->update($dataUpdate);

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
            ShopTax::destroy($arrID);
            return response()->json(['error' => 0, 'msg' => '']);
        }
    }
}
