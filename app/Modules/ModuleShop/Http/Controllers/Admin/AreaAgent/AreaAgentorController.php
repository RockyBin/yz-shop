<?php
/**
 * 后台区域代理接口
 * User: liyaohui
 * Date: 2020/6/3
 * Time: 16:08
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\AreaAgent;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentorAdmin;
use Illuminate\Http\Request;

class AreaAgentorController extends BaseAdminController
{
    /**
     * 获取区代列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $list = AreaAgentorAdmin::getList($request->all());
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 取消资格
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function cancel(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $unbind = $request->input('unbind', 0);
            $agentor = new AreaAgentorAdmin($memberId);
            $save = $agentor->cancelAreaAgent($unbind);
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '取消失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 恢复资格
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function recover(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $areaList = $request->input('area_list', []);
            $agentor = new AreaAgentorAdmin($memberId);
            $save = $agentor->recoverAreaAgent($areaList);
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '恢复失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 修改代理区域
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function modifyAgentArea(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $areaList = $request->input('area_list', []);
            $agentor = new AreaAgentorAdmin($memberId);
            $save = $agentor->modifyAgentArea($areaList);
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '修改失败失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 新增区域代理
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function addAgentArea(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $areaList = $request->input('area_list', []);
            $add = AreaAgentorAdmin::addAreaAgent($memberId, $areaList);
            return $add;
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取已经有代理的区域id
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function getDisableAreaIds(Request $request)
    {
        try {
            $areaType = $request->input('area_type', 0);
            $parentId = $request->input('parent_id', 0);
            $list = AreaAgentorAdmin::getDisableAreaIds($areaType, $parentId);
            return makeApiResponseSuccess('ok', ['area_ids' => $list]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取区代统计相关数据
     * @param Request $request
     * @return array
     */
    public function getAreaAgentCount(Request $request)
    {
        try {
            $memberId = $request->input('member_id');
            $areaAgentCount = (new AreaAgentorAdmin($memberId))->getAreaAgentInfo();
            return makeApiResponseSuccess('ok', $areaAgentCount);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取区代下级子列表
     * @param Request $request
     * @return array
     */
    public function getAreaAgentSubList(Request $request)
    {
        try {
            $list = AreaAgentorAdmin::getSubAreaAgentList($request->all());
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}