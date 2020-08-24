<?php
/**
 * 区代业绩接口
 * User: liyaohui
 * Date: 2020/6/2
 * Time: 16:21
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\AreaAgent;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentPerformance;
use Illuminate\Http\Request;

class AreaAgentPerformanceController extends BaseAdminController
{
    /**
     * 获取业绩列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $params = $request->all();
            $list = AreaAgentPerformance::getList($params);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function export(Request $request)
    {
        try {
            $params = $request->all();
            return AreaAgentPerformance::export($params);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}