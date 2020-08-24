<?php
/**
 * 产品列表
 * User: 李耀辉
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Product;

use Illuminate\Http\Request;
use YZ\Core\Member\Auth;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Product\Product;
use YZ\Core\Site\Site;

class ProductListController extends BaseFrontController
{
    public function getList(Request $request)
    {
        try {
            $memberId = Auth::hasLogin();
            $param = $request->toArray();
            $param['status'] = '1';
            if ($memberId) {
                $param['member_id'] = $memberId;
            }
            $param['merge_sold_count'] = 1;
            //没有指定排序时，按 sort 字段进行排序
            if(!$param['order_by']['column']) {
                //$param['order_by']['column'] = 'sort';
                //$param['order_by']['order'] = 'desc';
                $param['order_by']['raworder'] = 'sort desc,sold_count desc';
            }
            $param['view_perm'] = 1; //限制浏览权限
            $data = Product::getList($param, $param['page'], $param['page_size']);
            return makeApiResponseSuccess('成功', [
                'total' => intval($data['total']),
                'page_size' => intval($data['page_size']),
                'current' => intval($data['current']),
                'last_page' => intval($data['last_page']),
                'list' => $data['list']
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}