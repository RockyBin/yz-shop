<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\GroupBuying;

use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuying;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingProducts;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use Illuminate\Support\Facades\Session;
use YZ\Core\Member\Auth;

class GroupBuyingController extends BaseFrontController
{
    function getInfo(Request $request)
    {
        try {
            if (!$request->group_buying_id) {
                return makeApiResponseFail('请输入活动ID');
            }
            $params = [];
            if ($request->group_buying_sku) {
                $params['group_buying_sku'] = $request->group_buying_sku;
            }
            $data = (new GroupBuying($request->group_buying_id))->getInfo($params);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponse(500, $e->getMessage());
        }
    }

    public function checkQualification(Request $request)
    {
        try {
            if (!$request->group_buying_id) {
                return makeApiResponseFail('数据异常：ID不能不空');
            }
            $obj = new GroupBuying($request->group_buying_id);
            $member_id = Auth::hasLogin();
            if (!$member_id) {
                return makeApiResponse(403, trans('shop-front.member.login_need'), [
                    'redirect' => $this->getLoginUrl(),
                ]);
            }
            $res = $obj->checkQualification($member_id, $request->group_buying_id);
            return $res;
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function mockGroupBuyingSuccess(Request $request)
    {
        try {
            if (!$request->group_buying_id) {
                return makeApiResponseFail('数据异常：ID不能不空');
            }
            if(GroupBuying::checkMockGroupBuying($request->group_buying_id)){
                GroupBuying::mockGroupBuyingSuccess($request->group_buying_id);
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}