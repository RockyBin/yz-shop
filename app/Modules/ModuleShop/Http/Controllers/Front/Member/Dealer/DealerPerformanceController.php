<?php
/**
 * 经销商业绩接口
 * User: liyaohui
 * Date: 2019/12/16
 * Time: 16:29
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Dealer;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformance;
use Illuminate\Http\Request;

class DealerPerformanceController extends BaseMemberController
{
    /**
     * 获取下级团队业绩
     * @param Request $request
     * @return array
     */
    public function getSubPerformanceList(Request $request){
        try {
            $params = $request->all(['type', 'num', 'year', 'get_count']);
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $data = DealerPerformance::getMemberSubPerformanceList($this->memberId, $params, $page, $pageSize);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取个人业绩
     * @param Request $request
     * @return array
     */
    public function getPerformance(Request $request){
        try {
            $params = $request->all(['type', 'num', 'year']);
            $data = DealerPerformance::getMemberPerformance($this->memberId, $params);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}