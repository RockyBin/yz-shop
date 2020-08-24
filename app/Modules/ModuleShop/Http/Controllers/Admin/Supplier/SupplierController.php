<?php
/**
 * 后台供应商接口
 * User: liyaohui
 * Date: 2020/6/22
 * Time: 18:03
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Supplier;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Supplier\SupplierAdmin;
use Illuminate\Http\Request;

class SupplierController extends BaseAdminController
{
    /**
     * 获取供应商列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $list = SupplierAdmin::getList($request->all());
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 新增供应商
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function add(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $name = $request->input('supplier_name', '');
            $supplier = new SupplierAdmin($memberId);
            $add = $supplier->add(['name' => $name]);
            if ($add) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '新增供应商失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 取消供应商资格
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function cancel(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $supplier = new SupplierAdmin($memberId);
            $cancel = $supplier->cancel();
            if ($cancel) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '供应商取消资格失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 恢复供应商资格
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function recover(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $supplier = new SupplierAdmin($memberId);
            $cancel = $supplier->recover();
            if ($cancel) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '供应商恢复资格失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取供应商基本信息和一些统计信息（会员详情页用）
     */
    public function getCountInfo(Request $request){
        try {
            $memberId = $request->input('member_id', 0);
            $supplier = new SupplierAdmin($memberId);
            $info = $supplier->getCountInfo();
            return makeApiResponseSuccess('ok',$info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}