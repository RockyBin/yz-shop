<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\GroupBuying;

use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuying;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingProducts;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use YZ\Core\Member\Auth;

class GroupBuyingProductController extends BaseFrontController
{
    function getProductList(Request $request)
    {
        try {
            if (!$request->group_buying_setting_id) {
                return makeApiResponseFail('请输入活动ID');
            }
            $params = $request->toArray();
            $params['check_product'] = true;
            $list = GroupBuyingProducts::getFrontList($params);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponse(500, $e->getMessage());
        }
    }

    function getProductDetail(Request $request)
    {
        try {
            if (!$request->group_product_id) {
                return makeApiResponseFail('请输入活动产品ID');
            }
            $params = $request->toArray();
            $data = GroupBuyingProducts::getDetail($params);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponse(500, $e->getMessage());
        }
    }

    /**
     * 凑团列表
     * @param Request $request
     * @return array
     */
    function getVirtualGroupList(Request $request)
    {
        try {
            if (!$request->group_product_id) {
                return makeApiResponseFail('请输入活动产品ID');
            }
            $params = $request->toArray();
            $data = GroupBuying::getVirtualGroupList($params);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponse(500, $e->getMessage());
        }
    }

    /**
     * SKU 数据
     * @param Request $request
     * @return array
     */
    public function getSku(Request $request)
    {
        try {
            if (!$request->group_product_id) {
                return makeApiResponseFail('数据异常：ID不能不空');
            }
            $obj = new GroupBuyingProducts($request->group_product_id);
            $member_id = Auth::hasLogin();
            $data = $obj->getSku($member_id);
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $e) {
            return makeApiResponse(500, $e->getMessage());
        }
    }

    /**
     * 检测这个产品是否能买，暂时只检测活动状态
     * @param Request $request
     * @return array
     */
    public function checkActivityStatus(Request $request)
    {
        try {
            if (!$request->group_product_id) {
                return makeApiResponseFail('数据异常：ID不能不空');
            }
            $obj = new GroupBuyingProducts($request->group_product_id);
            if ($obj->checkActivityStatus()) {
                return makeApiResponseSuccess('成功');
            } else {
                return makeApiResponseFail('活动已过期');
            }

        } catch (\Exception $e) {
            return makeApiResponse(500, $e->getMessage());
        }
    }
}