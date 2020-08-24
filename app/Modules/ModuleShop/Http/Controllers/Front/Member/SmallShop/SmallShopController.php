<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\SmallShop;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\SmallShop\SmallShop;
use App\Modules\ModuleShop\Libs\SmallShop\SmallShopProduct;
use YZ\Core\Member\Auth;
use Illuminate\Http\Request;


/**
 * 小店 Controller
 * Class WithdrawController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member
 */
class SmallShopController extends BaseMemberController
{
    function edit(Request $request)
    {
        try {
            $memberId = Auth::hasLogin();
            $params = $request->toArray();
            $params['member_id'] = $memberId;
            $params['site_id'] = $this->siteId;

            (new SmallShop($memberId))->edit($params);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    function add(Request $request)
    {
        try {
            $params = $request->toArray();
            $memberId = Auth::hasLogin();
            $params['member_id'] = $memberId;
            $res = SmallShop::add($params);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    function editSmallShopProduct(Request $request)
    {
        try {
            $memberId = Auth::hasLogin();
            $params = $request->toArray();
            (new SmallShopProduct($memberId))->editSmallShopProduct($params);
            return makeApiResponseSuccess('ok');

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

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

    function upload(Request $request)
    {
        try {
            $params = $request->toArray();
            $data = SmallShop::upload($params);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}