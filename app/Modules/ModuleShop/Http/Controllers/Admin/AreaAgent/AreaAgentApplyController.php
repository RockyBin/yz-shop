<?php
/**
 * 区代审核接口
 * User: liyaohui
 * Date: 2020/5/27
 * Time: 10:55
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\AreaAgent;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentApplyAdmin;
use Illuminate\Http\Request;

class AreaAgentApplyController extends BaseAdminController
{
    /**
     * 获取审核列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $params = $request->all();
            $list = (new AreaAgentApplyAdmin())->getApplyList($params);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 审核区域代理
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function verify(Request $request)
    {
        try {
            $params = $request->all();
            (new AreaAgentApplyAdmin())->verify($params);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除审核记录
     * @param Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        try {
            (new AreaAgentApplyAdmin())->deleteApply($request->input('apply_id'));
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}