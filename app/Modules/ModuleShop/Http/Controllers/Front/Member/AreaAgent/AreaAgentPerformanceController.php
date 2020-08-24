<?php
/**
 * 区代业绩接口
 * User: liyaohui
 * Date: 2020/6/8
 * Time: 11:25
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\AreaAgent;


use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentPerformance;
use Illuminate\Http\Request;

class AreaAgentPerformanceController extends BaseMemberController
{
    /**
     * 获取区代业绩
     * @param Request $request
     * @return array
     */
    public function getPerformance(Request $request)
    {
        try {
            $time = $request->input('time');
            $timeType = $request->input('time_type');
            $getTotal = $request->input('get_total', false);
            $data = AreaAgentPerformance::getAreaAgentPerformanceData($this->memberId, $timeType, $time, $getTotal);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}