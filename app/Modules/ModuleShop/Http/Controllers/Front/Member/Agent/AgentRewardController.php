<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Agent;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformance;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceRewardSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentRecommendReward;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceModel;
use App\Modules\ModuleShop\Libs\SiteConfig\WithdrawConfig;
use App\Modules\ModuleShop\Libs\SmallShop\SmallShop;
use Illuminate\Http\Request;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;
use YZ\Core\Site\Config;
use YZ\Core\Site\Site;

/**
 * 代理团队分红奖励相关
 * Class AgentRewardController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\Agent
 */
class AgentRewardController extends BaseController
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
            $memberModel = $member->getModel();
            // 检查是否代理
            $agentor = new Agentor($memberId);
            if (!$agentor->isAgent()) {
                return makeServiceResultFail("不是代理");
            }
            $data = $agentor->getCountData([
                'team' => true,
                'team_contain_self' => true, // 包含自身
                'reward' => true,
                'performance_now' => true,
            ], true);
            // 基础设置
            $baseSetting = AgentBaseSetting::getCurrentSiteSettingFormat();
            $agentLevel = intval($memberModel->agent_level);
            $data['base_setting'] = $baseSetting;
            $data['member'] = [
                'id' => $memberModel->id,
                'name' => $memberModel->name,
                'nickname' => $memberModel->nickname,
                'headurl' => $memberModel->headurl,
                'mobile' => $memberModel->mobile,
                'agent_level' => $agentLevel,
                'agent_level_text' => Constants::getAgentLevelTextForFront($agentLevel),
            ];
            $cloudStockData = $this->getCloudStockData();
            $data['cloud_stock_data'] = $cloudStockData;
            $data['show_cloud_stock'] = 0; //云仓已经迁移到经销商，这行后面可以删除
            // 是否拥有小店 true 代表存在 false 代表不存在
            $data['show_small_shop'] = SmallShop::getInfo(['member_id' => $memberId])['id'] ? true : false;
            $config = (new Config(Site::getCurrentSite()->getSiteId()))->getModel();
            $data['small_shop_status'] = $config->small_shop_status;
            $data['small_shop_optional_product_status'] = $config->small_shop_optional_product_status;
            $withdrawConfig = New WithdrawConfig();
            $data['withdraw_config'] = $withdrawConfig->getInfo(0, true);
            // 返回数据
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取云仓数据
     * @param Request $request
     * @return array
     */
    private function getCloudStockData()
    {
        $this->_cloudStock = new CloudStock($this->memberId, 0);
        $data['cloud_stock_total_inventory'] = $this->_cloudStock->getTotalInventory();
        $cloudStockFinance = $this->_cloudStock->getMoneyCount();
        //下级代理进货收入
        $retailStatus1 = moneyYuan2Cent($cloudStockFinance['retailStatus1']);
        //C端零售收入
        $purchaseStatus1 = moneyYuan2Cent($cloudStockFinance['purchaseStatus1']);
        $data['cloud_stock_money_count'] = moneyCent2Yuan($retailStatus1 + $purchaseStatus1);
        return $data;
    }

    /**
     * 分红列表
     * @param Request $request
     * @return array
     */
    public function getRewardList(Request $request)
    {
        try {
            $finance = new Finance();
            $param = $request->toArray();
            $param['single_member'] = true;
            $param['order_info'] = true;
            $param['member_id'] = $this->memberId;
            $param['types'] = CoreConstants::FinanceType_AgentCommission;
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
                        if (in_array($subType, [CoreConstants::FinanceSubType_AgentCommission_Order, CoreConstants::FinanceSubType_AgentCommission_SaleReward])) {
                            $commissionText = $item->buyer_nickname;
                            if (intval($item->status) == CoreConstants::FinanceStatus_Active) {
                                $commissionText .= '-交易成功-';
                            } else if (intval($item->status) == CoreConstants::FinanceStatus_Invalid) {
                                $commissionText .= '-交易失败-';
                            } else {
                                $commissionText .= '-交易中-';
                            }
                        } else if ($subType == CoreConstants::FinanceSubType_AgentCommission_Recommend) {
                            if ($item->from_member1) {
                                $fromMember1 = MemberModel::query()->where('site_id', $this->siteId)->where('id', $item->from_member1)->first();
                                if ($fromMember1) {
                                    $commissionText = $fromMember1->nickname . '-';
                                }
                            }
                        } else if ($subType == CoreConstants::FinanceSubType_AgentCommission_Performance) {
                            $financeOrderId = $item->order_id;
                            if (str_contains($financeOrderId, 'PERFORMANCE_REWARD_')) {
                                $countParam = explode('_', substr($financeOrderId, strlen('PERFORMANCE_REWARD_')), 3);
                                $countType = intval($countParam[0]);
                                if ($countType == 1) {
                                    $commissionText = $countParam[1] . '年第' . $countParam[2] . '季度';
                                } else if ($countType == 2) {
                                    $commissionText = $countParam[1] . '年度';
                                } else {
                                    $commissionText = $countParam[1] . '年' . $countParam[2] . '月';
                                }
                            }
                        }
                        $commissionText .= CoreConstants::getFinanceSubTypeTextForFront($subType);
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
    public function getRewardWithdrawList(Request $request)
    {
        try {
            $finance = new Finance();
            $param = $request->toArray();
            $param['single_member'] = true;
            $param['order_info'] = true;
            $param['member_id'] = $this->memberId;
            $param['types'] = CoreConstants::FinanceType_AgentCommission;
            $param['out_types'] = [CoreConstants::FinanceOutType_Withdraw, CoreConstants::FinanceOutType_CommissionToBalance];
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
                    if ($item->out_type == CoreConstants::FinanceInType_CommissionToBalance) {
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

    /**
     * 推荐奖数据统计
     * @param Request $request
     * @return array
     */
    public function recommendCount(Request $request)
    {
        try {
            $memberId = $this->memberId;
            $member = new Member($memberId);
            if (!$member->checkExist()) {
                return makeServiceResultFail("不是会员");
            }
            $memberModel = $member->getModel();
            $money = 0;
            $num = 0;
            $financeData = FinanceModel::query()->where('site_id', $this->siteId)->where('member_id', $this->memberId)
                ->where('type', CoreConstants::FinanceType_AgentCommission)
                ->where('sub_type', CoreConstants::FinanceSubType_AgentCommission_Recommend)
                ->where('status', CoreConstants::FinanceStatus_Active)
                ->selectRaw('sum(money) as money, count(1) as num')
                ->first();
            if ($financeData) {
                $money = intval($financeData->money);
                $num = intval($financeData->num);
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), [
                'member' => [
                    'id' => $memberModel->id,
                    'name' => $memberModel->name,
                    'nickname' => $memberModel->nickname,
                    'headurl' => $memberModel->headurl,
                    'mobile' => $memberModel->mobile,
                    'agent_level' => $memberModel->agent_level,
                    'agent_level_text' => Constants::getAgentLevelTextForFront(intval($memberModel->agent_level)),
                ],
                'recommend_total_money' => moneyCent2Yuan($money),
                'recommend_num' => $num,
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 推荐奖列表
     * @param Request $request
     * @return array
     */
    public function recommendList(Request $request)
    {
        try {
            $param = $request->toArray();
            $param['member_id'] = $this->memberId;
            $param['order_by'] = 'checked_at';
            $param['status'] = Constants::AgentRewardStatus_Active;
            $data = AgentRecommendReward::getList($param);
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $item->reward_money = moneyCent2Yuan($item->reward_money);
                    $item->sub_member_agent_level_text = Constants::getAgentLevelTextForFront(intval($item->sub_member_agent_level));
                }
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}