<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\AreaAgent;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformance;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceRewardSetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentConstants;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentor;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceModel;
use App\Modules\ModuleShop\Libs\SiteConfig\WithdrawConfig;
use Illuminate\Http\Request;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;

/**
 * 区代返佣相关控制器
 * Class AgentCommissionController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\AreaAgent
 */
class AgentCommissionController extends BaseController
{
    /**
     * 分红基础信息
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        try {
            // 会员信息
            $memberId = $this->memberId;
            $member = new Member($memberId);
            if (!$member->checkExist()) {
                return makeServiceResultFail("不是会员");
            }
            $data = AreaAgentor::getCountData([
                'member_id' => $memberId,
                'commission' => true,
            ], true);
            $withdrawConfig = New WithdrawConfig();
            $data['withdraw_config'] = $withdrawConfig->getInfo(0, true);
            // 返回数据
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


    /**
     * 分红列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $finance = new Finance();
            $param = $request->toArray();
            $param['single_member'] = true;
            $param['order_info'] = true;
            $param['member_id'] = $this->memberId;
            $param['types'] = CoreConstants::FinanceType_AreaAgentCommission;
            $param['in_types'] = CoreConstants::FinanceInType_Commission;
            $data = $finance->getList($param);
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $commissionText = "";
                    $item->money = moneyCent2Yuan(abs($item->money));
                    $item->money_fee = moneyCent2Yuan(abs($item->money_fee));
                    $item->money_real = moneyCent2Yuan(abs($item->money_real));
                    // 构造说明文字
                    if (intval($item->in_type) == CoreConstants::FinanceInType_Commission) {
                        $subType = intval($item->sub_type);
                        $commissionText = CoreConstants::getFinanceSubTypeTextForFront($subType) . '-';
                        if (in_array($subType, [CoreConstants::FinanceSubType_AreaAgentCommission_Order])) {
                            if (intval($item->status) == CoreConstants::FinanceStatus_Active) {
                                $commissionText .= '交易成功-';
                            } else if (intval($item->status) == CoreConstants::FinanceStatus_Invalid) {
                                $commissionText .= '交易失败-';
                            } else {
                                $commissionText .= '交易中-';
                            }
                            $commissionText .= $item->buyer_nickname;
                        }
                    }
                    if ($commissionText == '') $commissionText = $item->about;
                    $item->commission_text = $commissionText;
                    for ($i = 1; $i <= 10; $i++) {
                        unset($item['from_member' . $i]);
                    }
                }
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 分红提现列表
     * @param Request $request
     * @return array
     */
    public function getCommissionWithdrawList(Request $request)
    {
        try {
            $finance = new Finance();
            $param = $request->toArray();
            $param['single_member'] = true;
            $param['order_info'] = true;
            $param['time_order_by'] = true;
            $param['order_by'] = 'time';
            $param['member_id'] = $this->memberId;
            $param['types'] = CoreConstants::FinanceType_AreaAgentCommission;
            $param['out_types'] = [CoreConstants::FinanceOutType_AreaAgentCommissionToBalance, CoreConstants::FinanceOutType_Withdraw];
            $data = $finance->getList($param);
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    for ($i = 1; $i <= 10; $i++) {
                        unset($item['from_member' . $i]);
                    }
                    $commissionText = "";
                    $item->money = moneyCent2Yuan(abs($item->money));
                    $item->money_fee = moneyCent2Yuan(abs($item->money_fee));
                    $item->money_real = moneyCent2Yuan(abs($item->money_real));
                    if ($item->out_type == CoreConstants::FinanceOutType_AreaAgentCommissionToBalance) {
                        $item->out_account = trans('shop-front.diy_word.balance');
                    } else {
                        $item->out_account = CoreConstants::getPayTypeWithdrawText($item->pay_type);
                    }
                    $item->commission_text = $commissionText;
                }
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 业绩统计
     * @param Request $request
     * @return array
     */
    public function performanceCount(Request $request)
    {
        try {
            $memberId = $this->memberId;
            $member = new Member($memberId);
            if (!$member->checkExist()) {
                return makeServiceResultFail("不是会员");
            }
            $memberModel = $member->getModel();
            $countType = intval($request->get('count_type', 0));
            $countYear = intval($request->get('count_year', 0));
            $countNum = intval($request->get('count_num', 0));
            $timeParam = AgentPerformance::parseTime($countType, $countYear, $countNum);
            $performanceRewardSetting = AgentPerformanceRewardSetting::getCurrentSiteSetting();
            $countPeriod = $performanceRewardSetting->count_period;
            // 业绩统计
            $performanceMoney = 0;
            $performanceNum = 0;
            $agentPerformanceData = AgentPerformanceModel::query()->where('site_id', $this->siteId)->where('member_id', $this->memberId)
                ->where('count_period', $countPeriod)
                ->where('order_time', '>=', $timeParam['start_time'])
                ->where('order_time', '<=', $timeParam['end_time'])
                ->selectRaw('sum(money) as money, count(distinct(order_id)) as num')
                ->first();
            if ($agentPerformanceData) {
                $performanceMoney = intval($agentPerformanceData->money);
                $performanceNum = intval($agentPerformanceData->num);
            }
            // 总业绩
            $agentPerformanceTotal = AgentPerformanceModel::query()->where('site_id', $this->siteId)->where('member_id', $this->memberId)
                ->where('count_period', $countPeriod)
                ->sum('money');
            // 总奖金
            $agentPerformanceRewardTotal = FinanceModel::query()->where('site_id', $this->siteId)->where('member_id', $this->memberId)
                ->where('type', CoreConstants::FinanceType_AgentCommission)
                ->where('sub_type', CoreConstants::FinanceSubType_AgentCommission_Performance)
                ->where('status', CoreConstants::FinanceStatus_Active)
                ->sum('money');
            // 返回数据
            return makeApiResponseSuccess('ok', [
                'member' => [
                    'id' => $memberModel->id,
                    'name' => $memberModel->name,
                    'nickname' => $memberModel->nickname,
                    'headurl' => $memberModel->headurl,
                    'mobile' => $memberModel->mobile,
                    'agent_level' => $memberModel->agent_level,
                    'agent_level_text' => Constants::getAgentLevelTextForFront(intval($memberModel->agent_level)),
                ],
                'performance_total_money' => moneyCent2Yuan($agentPerformanceTotal),
                'performance_reward_total_money' => moneyCent2Yuan($agentPerformanceRewardTotal),
                'performance_money' => moneyCent2Yuan($performanceMoney),
                'performance_num' => intval($performanceNum),
                'count_period' => $countPeriod,
                'time_start' => $timeParam['start'],
                'time_end' => $timeParam['end'],
            ]);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}