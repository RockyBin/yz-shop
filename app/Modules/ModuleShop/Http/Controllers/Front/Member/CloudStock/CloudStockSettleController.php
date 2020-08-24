<?php
/**
 * 云仓收入结算接口
 * User: liyaohui
 * Date: 2019/9/10
 * Time: 15:12
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;


use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSkuSettle;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformanceReward;
use App\Modules\ModuleShop\Libs\Dealer\DealerReward;
use Illuminate\Http\Request;
use YZ\Core\Member\Member;

class CloudStockSettleController extends BaseMemberController
{
    public function getSettleFinanceInfo()
    {
        try {
            $memberId = $this->memberId;
            // 订单结算
            $settle = CloudStock::getSettleCount($memberId);
            // 余额
            $finance = CloudStock::getMemberFinanceInfo($memberId);
            // 奖金
            $reward = DealerReward::getMemberReward($memberId, false);
            // $performance = DealerPerformanceReward::getPerformanceRewardCount($memberId)['reward_money'];
            $data = [
                'retail' => $settle['retailStatus1'],
                'purchase' => $settle['purchaseStatus1'],
                'order_all' => $settle['allStatus1'],
                'balance' => $finance['balance'],
                'freeze' => $finance['freeze'],
                'total' => $settle['allStatus1'] + $reward['performanceCount'] + $reward['recommendCount'] + $reward['saleCount'],
                'performance_count' => $reward['performanceCount'],
                'recommend_count' => $reward['recommendCount'],
                'sale_count' => $reward['saleCount'],
            ];
            // 分转元
            $data = array_map(function ($item) {
                return moneyCent2Yuan($item);
            }, $data);

            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取云仓结算列表
     * @param Request $request
     * @return array
     */
    public function getSettleList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $params = $request->all(['type']);
            $list = CloudStockSkuSettle::getSettleList($this->memberId, $params, $page, $pageSize);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取云仓提现列表
     * @param Request $request
     * @return array
     */
    public function getRewardWithdrawList(Request $request)
    {
        try {
            $params = $request->all();
            $params['member_id'] = $this->memberId;
            $list = CloudStockSkuSettle::getRewardWithdrawList($params);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取业绩奖列表
     * @param Request $request
     * @return array
     */
    public function getPerformanceRewardList(Request $request){
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $data = DealerPerformanceReward::getPerformanceRewardList($this->memberId, $page, $pageSize);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}