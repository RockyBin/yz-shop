<?php
/**
 * 供应商商品管理接口
 * User: liyaohui
 * Date: 2020/6/23
 * Time: 17:38
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Supplier;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Supplier\SupplierProductAdmin;
use Illuminate\Http\Request;

class SupplierProductController extends BaseAdminController
{
    /**
     * 获取待审核商品列表
     * @param Request $request
     * @return array
     */
    public function getWaitVerifyProductList(Request $request)
    {
        try {
            $list = SupplierProductAdmin::getWaitVerifyProductList($request->all());
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取待审商品的数据
     * @param Request $request
     * @return array
     */
    public function getWaitVerifyProductInfo(Request $request)
    {
        try {
            $id = $request->input('id', 0);
            $product = new SupplierProductAdmin($id);
            $info = $product->getWaitVerifyProductInfo();
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 审核供应商商品
     * @param Request $request
     * @return array
     */
    public function verifyProducts(Request $request)
    {
        try {
            $ids = $request->input('ids', 0);
            $verify = SupplierProductAdmin::verifyProducts($ids, $request->all());
            if ($verify) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(500, '审核失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}