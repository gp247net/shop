<?php
namespace GP247\Shop\Admin\Controllers;

use GP247\Core\Controllers\RootAdminController;
use GP247\Shop\Models\ShopAttributeGroup;
use GP247\Core\Models\AdminLanguage;
use GP247\Shop\Admin\Models\AdminProduct;

class AdminReportController extends RootAdminController
{
    public $languages;
    public $kinds;
    public $attributeGroup;

    public function __construct()
    {
        parent::__construct();
        $this->languages = AdminLanguage::getListActive();
        $this->attributeGroup = ShopAttributeGroup::getListAll();
        $this->kinds = [
            GP247_PRODUCT_SINGLE => gp247_language_render('product.kind_single'),
            GP247_PRODUCT_BUILD => gp247_language_render('product.kind_bundle'),
            GP247_PRODUCT_GROUP => gp247_language_render('product.kind_group'),
        ];
    }

    public function product()
    {
        $data = [
            'title' => gp247_language_render('admin.product.list'),
            'subTitle' => '',
            'icon' => 'fa fa-indent',
            'urlDeleteItem' => '',
            'removeList' => 0, // 1 - Enable function delete list item
            'buttonRefresh' => 0, // 1 - Enable button refresh
            'css' => '',
            'js' => '',
        ];
        //Process add content
        $data['menuRight'] = gp247_config_group('menuRight', \Request::route()->getName());
        $data['menuLeft'] = gp247_config_group('menuLeft', \Request::route()->getName());
        $data['topMenuRight'] = gp247_config_group('topMenuRight', \Request::route()->getName());
        $data['topMenuLeft'] = gp247_config_group('topMenuLeft', \Request::route()->getName());
        $data['blockBottom'] = gp247_config_group('blockBottom', \Request::route()->getName());

        $listTh = [
            'image' => gp247_language_render('product.image'),
            'sku' => gp247_language_render('product.sku'),
            'name' => gp247_language_render('product.name'),
            'price' => gp247_language_render('product.price'),
            'stock' => gp247_language_render('product.stock'),
            'sold' => gp247_language_render('product.sold'),
            'view' => gp247_language_render('product.view'),
            'kind' => gp247_language_render('product.kind'),
            'status' => gp247_language_render('product.status'),
        ];
        $sort_order = gp247_clean(request('sort_order') ?? 'id_desc');
        $keyword    = gp247_clean(request('keyword') ?? '');
        $arrSort = [
            'id__desc' => gp247_language_render('filter_sort.id_desc'),
            'id__asc' => gp247_language_render('filter_sort.id_asc'),
            'name__desc' => gp247_language_render('filter_sort.name_desc'),
            'name__asc' => gp247_language_render('filter_sort.name_asc'),
            'sold__desc' => gp247_language_render('filter_sort.value_desc'),
            'sold__asc' => gp247_language_render('filter_sort.sold_asc'),
            'view__desc' => gp247_language_render('filter_sort.view_desc'),
            'view__asc' => gp247_language_render('filter_sort.view_asc'),
        ];
        $dataSearch = [
            'keyword'    => $keyword,
            'sort_order' => $sort_order,
            'arrSort'    => $arrSort,
        ];

        $dataTmp = (new AdminProduct)->getProductListAdmin($dataSearch);

        $dataTr = [];
        foreach ($dataTmp as $key => $row) {
            $kind = $this->kinds[$row['kind']] ?? $row['kind'];
            if ($row['kind'] == GP247_PRODUCT_BUILD) {
                $kind = '<span class="badge badge-success">' . $kind . '</span>';
            } elseif ($row['kind'] == GP247_PRODUCT_GROUP) {
                $kind = '<span class="badge badge-danger">' . $kind . '</span>';
            }

            $dataTr[$row['id']] = [
                'image' => gp247_image_render($row['image'], '50px', '', $row['name']),
                'sku' => $row['sku'],
                'name' => $row['name'],
                'price' => $row['price'],
                'stock' => $row['stock'],
                'sold' => $row['sold'],
                'view' => $row['view'],
                'kind' => $kind,
                'status' => $row['status'] ? '<span class="badge badge-success">ON</span>' : '<span class="badge badge-danger">OFF</span>',
            ];
        }

        $data['listTh'] = $listTh;
        $data['dataTr'] = $dataTr;
        $data['pagination'] = $dataTmp->appends(request()->except(['_token', '_pjax']))->links('gp247-core::component.pagination');
        $data['resultItems'] = gp247_language_render('admin.product.result_item', ['item_from' => $dataTmp->firstItem(), 'item_to' => $dataTmp->lastItem(), 'total' =>  $dataTmp->total()]);

        //menu_left
        $data['menu_left'] = '<div class="pull-left">

                    <a class="btn   btn-flat btn-primary grid-refresh" title="Refresh"><i class="fas fa-sync-alt"></i><span class="hidden-xs"> ' . gp247_language_render('action.refresh') . '</span></a> &nbsp;</div>
                    ';
        //=menu_left

        //menuSearch
        $optionSort = '';
        foreach ($arrSort as $key => $status) {
            $optionSort .= '<option  ' . (($sort_order == $key) ? "selected" : "") . ' value="' . $key . '">' . $status . '</option>';
        }
        //=menuSort

        //menuSearch
        $data['topMenuRight'][] = '
                <form action="' . gp247_route_admin('admin_report.product') . '" id="button_search">
                <div class="input-group input-group" style="width: 350px;">
                    <select class="form-control rounded-0 select2" name="sort_order" id="sort_order">
                    '.$optionSort.'
                    </select> &nbsp;
                    <input type="text" name="keyword" class="form-control rounded-0 float-right" placeholder="' . gp247_language_render('admin.product.search_place') . '" value="' . $keyword . '">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                </form>';
        //=menuSearch

        return view('gp247-core::screen.list')
            ->with($data);
    }
}
