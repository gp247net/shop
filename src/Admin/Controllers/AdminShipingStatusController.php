<?php
namespace GP247\Shop\Admin\Controllers;

use GP247\Core\Controllers\RootAdminController;
use GP247\Shop\Models\ShopShippingStatus;
use Validator;

class AdminShipingStatusController extends RootAdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        $data = [
            'title' => gp247_language_render('admin.shipping_status.list'),
            'title_action' => '<i class="fa fa-plus" aria-hidden="true"></i> ' . gp247_language_render('admin.shipping_status.add_new_title'),
            'subTitle' => '',
            'icon' => 'fa fa-indent',
            'urlDeleteItem' => gp247_route_admin('admin_shipping_status.delete'),
            'removeList' => 0, // 1 - Enable function delete list item
            'buttonRefresh' => 0, // 1 - Enable button refresh
            'url_action' => gp247_route_admin('admin_shipping_status.post_create'),
        ];

        $listTh = [
            'id' => 'ID',
            'name' => gp247_language_render('admin.shipping_status.name'),
            'action' => gp247_language_render('action.title'),
        ];
        $obj = new ShopShippingStatus;
        $obj = $obj->orderBy('id', 'desc');
        $dataTmp = $obj->paginate(20);

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $dataTr[$row['id']] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? 'N/A',
                ];
            $arrAction = [
                '<a href="' . gp247_route_admin('admin_shipping_status.edit', ['id' => $row['id'] ? $row['id'] : 'not-found-id']) . '"  class="dropdown-item"><i class="fa fa-edit"></i> '.gp247_language_render('action.edit').'</a>',
                ];
            if (!isset($this->statusProtected()[$row['id']])) {
                $arrAction[] = '<a href="#" onclick="deleteItem(\'' . $row['id'] . '\');"  title="' . gp247_language_render('action.delete') . '" class="dropdown-item"><i class="fas fa-trash-alt"></i> '.gp247_language_render('action.remove').'</a>';
            }
            $action = $this->procesListAction($arrAction);
            $dataTr[$row['id']]['action'] = $action;
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('gp247-core::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);

        $data['layout'] = 'index';
        return view('gp247-shop-admin::screen.shipping_status')
            ->with($data);
    }


    /**
     * Post create new item in admin
     * @return [type] [description]
     */
    public function postCreate()
    {
        $data = request()->all();
        $dataOrigin = request()->all();
        $validator = Validator::make($dataOrigin, [
            'name' => 'required',
        ], [
            'name.required' => gp247_language_render('validation.required'),
        ]);

        if ($validator->fails()) {

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        $dataCreate = [
            'name' => $data['name'],
        ];
        $dataCreate = gp247_clean($dataCreate, [], true);
        $obj = ShopShippingStatus::create($dataCreate);

        return redirect()->route('admin_shipping_status.index')->with('success', gp247_language_render('action.create_success'));
    }

    /**
     * Form edit
     */
    public function edit($id)
    {
        $shipping_status = ShopShippingStatus::find($id);
        if (!$shipping_status) {
            return 'No data';
        }
        $data = [
        'title' => gp247_language_render('admin.shipping_status.list'),
        'title_action' => '<i class="fa fa-edit" aria-hidden="true"></i> ' . gp247_language_render('action.edit'),
        'subTitle' => '',
        'icon' => 'fa fa-indent',
        'urlDeleteItem' => gp247_route_admin('admin_shipping_status.delete'),
        'removeList' => 0, // 1 - Enable function delete list item
        'buttonRefresh' => 0, // 1 - Enable button refresh
        'url_action' => gp247_route_admin('admin_shipping_status.edit', ['id' => $shipping_status['id']]),
        'shipping_status' => $shipping_status,
        'id' => $id,
    ];

        $listTh = [
        'id' => 'ID',
        'name' => gp247_language_render('admin.shipping_status.name'),
        'action' => gp247_language_render('admin.shipping_status.action.title'),
    ];
        $obj = new ShopShippingStatus;
        $obj = $obj->orderBy('id', 'desc');
        $dataTmp = $obj->paginate(20);

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $dataTr[$row['id']] = [
            'id' => $row['id'],
            'name' => $row['name'] ?? 'N/A',
            ];
            $arrAction = [
                '<a href="' . gp247_route_admin('admin_shipping_status.edit', ['id' => $row['id'] ? $row['id'] : 'not-found-id']) . '"  class="dropdown-item"><i class="fa fa-edit"></i> '.gp247_language_render('action.edit').'</a>',
                ];
            if (!isset($this->statusProtected()[$row['id']])) {
                $arrAction[] = '<a href="#" onclick="deleteItem(\'' . $row['id'] . '\');"  title="' . gp247_language_render('action.delete') . '" class="dropdown-item"><i class="fas fa-trash-alt"></i> '.gp247_language_render('action.remove').'</a>';
            }
            $action = $this->procesListAction($arrAction);
            $dataTr[$row['id']]['action'] = $action;
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('gp247-core::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);

        $data['layout'] = 'edit';
        return view('gp247-shop-admin::screen.shipping_status')
        ->with($data);
    }

    /**
     * update status
     */
    public function postEdit($id)
    {
        $data = request()->all();
        $dataOrigin = request()->all();
        $validator = Validator::make($dataOrigin, [
            'name' => 'required',
        ], [
            'name.required' => gp247_language_render('validation.required'),
        ]);

        if ($validator->fails()) {

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        //Edit
        $dataUpdate = [
            'name' => $data['name'],
        ];
        $dataUpdate = gp247_clean($dataUpdate, [], true);
        $obj = ShopShippingStatus::find($id);
        $obj->update($dataUpdate);

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
            foreach ($arrID as $key => $row) {
                if (isset($this->statusProtected()[$row])) {
                    return response()->json(['error' => 1, 'msg' => gp247_language_render('admin.shipping_status.status_protected', ['status' => $this->statusProtected()[$row]])]);
                }
            }
            ShopShippingStatus::destroy($arrID);
            return response()->json(['error' => 0, 'msg' => '']);
        }
    }
    protected function statusProtected() {
        return [
            '1' => 'Not sent',
            '2' => 'Sending',
            '3' => 'Shipping done',
            '4' => 'Refunded',
        ];
    }
}
