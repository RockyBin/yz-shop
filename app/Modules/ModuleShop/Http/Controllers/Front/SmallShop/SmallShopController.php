<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\SmallShop;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\SmallShop\SmallShop;
use App\Modules\ModuleShop\Libs\SmallShop\SmallShopProduct;
use YZ\Core\Member\Auth;
use Illuminate\Http\Request;


/**
 * 小店 Controller
 * Class WithdrawController
 * @package App\Modules\ModuleShop\Http\Controllers\Front
 */
class SmallShopController extends BaseFrontController
{
    function getInfo(Request $request)
    {
        try {
            $param = $request->toArray();
            if ($request->member_id) {
                $param['member_id'] = $request->member_id;
            } else {
                $memberId = Auth::hasLogin();
                $param['member_id'] = $memberId;
            }
            $data = SmallShop::getInfo($param);
            if ($data) {
                return makeApiResponseSuccess('ok', $data);
            } else {
                return makeApiResponseFail('请先申请小店');
            }

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    function getSmallShopProductList(Request $request)
    {
        try {
            $param = $request->toArray();
            if ($request->member_id) {
                $param['member_id'] = $request->member_id;
            } else {
                $memberId = Auth::hasLogin();
                $param['member_id'] = $memberId;
            }
            $data = (new SmallShopProduct($param['member_id']))->getSmallShopProductList($param, $request->page, $request->page_size);
            if ($data) {
                return makeApiResponseSuccess('ok', $data);
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}