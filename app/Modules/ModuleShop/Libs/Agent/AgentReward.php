<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceModel;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceRewardModel;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceRewardRuleModel;
use App\Modules\ModuleShop\Libs\Model\AgentRecommendRewardModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Finance\Finance;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;

/**
 * 团队代理的各类奖励的计算类
 * Class Agent
 * @package App\Modules\ModuleShop\Libs\Agent
 */
class AgentReward
{
    /**
     * 根据购买者会会员ID计算链条内相应人员的代理佣金所得金额
     * @param $memberId 购买者的会员ID
     * @param $money 产品的最终售价
     * @param $costMoney 产品的成本价
     * @param $buyNum 订购的数量
     * @param AgentOrderCommisionConfig $config 订单代理佣金配置
     * @return array 佣金列表
     */
    public static function calOrderCommisionMoney($memberId, $money, $costMoney, $buyNum, AgentOrderCommisionConfig $config): array
    {
        if ($config->maxlevel < 1) return [];
        $members = AgentHelper::getParentAgents($memberId);
        if (!is_array($members['normal'])) return [];
        $members = $members['normal'];
        $totalMoney = $config->type ? $money : $money - $costMoney; // 总共有多少钱可以分
        if ($totalMoney < 0) $totalMoney = 0;
        $assignedMoney = 0; //记录已经分出去的钱，以便进行逐级分配
        $memberMoneys = [];
        $chain = [$memberId];
        foreach ($members as $key => $mem) {
            if ($mem['agent_level'] > $config->maxlevel) continue; // 当前代理的等级ID大于最大值，表示此代理的等级没有启用
            $commission = $config->getLevelCommision($mem['agent_level']);
            if ($commission < 0) $commission = 0;
            if ($config->amountType) { // 分固定金额时
                $moneyTemp = $commission * $buyNum;
            } else {
                // 按比例分时，暂且不管溢出的问题 By Aison 2019-07-30
                $moneyTemp = intval($totalMoney * ($commission / 100));
            }
            if (!(AgentBaseSetting::getCurrentSiteSetting()->internal_purchase) && $mem['id'] == $memberId) $moneyTemp = 0;
            if ($config->bonusMode == 1) $moneyTemp -= $assignedMoney; //逐级分配时
            if ($moneyTemp < 0) $moneyTemp = 0;
            $assignedMoney += $moneyTemp;
            $memberMoneys[] = ['member_id' => $mem['id'], 'agent_level' => $mem['agent_level'], 'money' => $moneyTemp, 'unit_money' => intval($moneyTemp / $buyNum), 'chain' => $chain];
            $chain[] = $mem['id'];
        }
        return $memberMoneys;
    }

    /**
     * 根据购买者会会员ID计算链条内相应平级或越级代理佣金所得金额
     * @param $memberId 购买者的会员ID
     * @param $money 产品的最终售价
     * @param $costMoney 产品的成本价
     * @param $buyNum 订购的数量
     * @param AgentSaleRewardCommisionConfig $config 订单代理佣金配置
     * @return array 佣金列表
     */
    public static function calSaleRewardCommisionMoney($memberId, $money, $costMoney, $buyNum, AgentSaleRewardCommisionConfig $config): array
    {
        if (!$config->enable) return [];
        // 查找当前会员的相关信息
        $agents = AgentHelper::getParentAgents($memberId);
        if (!is_array($agents['samelevel']) && !is_array($agents['lowlevel'])) return [];
        $members = [];
        foreach ($agents['samelevel'] as $m) {
            $m['is_samelevel'] = 1;
            $members[] = $m;
        }
        foreach ($agents['lowlevel'] as $m) {
            $m['is_lowlevel'] = 1;
            $members[] = $m;
        }
        $totalMoney = $config->type ? $money : $money - $costMoney; // 总共有多少钱可以分
        if ($totalMoney < 0) return [];
        $memberMoneys = [];
        $chain = [$memberId, $agents['normal'][0]['id']];
        foreach ($members as $key => $mem) {
            if ($mem['agent_level'] > $config->maxlevel) continue; // 当前代理的等级ID大于最大值，表示此代理的等级没有启用
            if ($mem['is_samelevel']) $commission = $config->getLevelCommision($mem['agent_level']);
            else $commission = $config->getLowLevelCommision();
            if ($config->amountType) {
                // 分固定金额时
                $moneyTemp = $commission * $buyNum;
            } else {
                // 按比例分时
                $moneyTemp = intval($totalMoney * $commission / 100);
            }
            $memberMoneys[] = ['member_id' => $mem['id'], 'agent_level' => $mem['agent_level'], 'money' => $moneyTemp, 'unit_money' => intval($moneyTemp / $buyNum), 'is_samelevel' => $mem['is_samelevel'], 'is_lowlevel' => $mem['is_lowlevel'], 'chain' => $chain];
        }
        return $memberMoneys;
    }

    /**
     * 添加代理正常订单佣金记录
     * @param $siteId 网站ID
     * @param $orderId 订单ID
     * @param array $commission 分佣表
     * @param $clearOld 清除旧的分佣记录，一般在对订单完全执行重新分佣时才需要设置为1
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function addAgentOrderCommission($siteId, $orderId, array $commission, $clearOld = 0)
    {
        // 查询佣金结算时机
        $commisionGrantTime = intval(AgentBaseSetting::getCurrentSiteSetting()->commision_grant_time);
        // 查询此订单是否已经有生效的正数佣金，如果有，表示此订单之前是付款后就结算佣金的，这时则不添加负数记录，以达到已发放的佣金不能再扣减的目的
        $activeCount = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Active, 'sub_type' => Constants::FinanceSubType_AgentCommission_Order, 'order_id' => $orderId])->where('money', '>', 0)->count('id');
        // 先将此订单之前的分佣记录删除
        if ($clearOld) FinanceModel::where(['type' => Constants::FinanceType_AgentCommission, 'sub_type' => Constants::FinanceSubType_AgentCommission_Order, 'order_id' => $orderId])->delete();
        // 记录新的记录
        $noticeFinanceIDs = [];
        $financeObj = new Finance();
        $batchNumber = date('YmdHis');
        foreach ($commission as $item) {
            if ($activeCount && $item['money'] < 0) continue;
            $finInfo = [
                'site_id' => $siteId,
                'member_id' => $item['member_id'],
                'type' => Constants::FinanceType_AgentCommission,
                'sub_type' => Constants::FinanceSubType_AgentCommission_Order,
                'pay_type' => Constants::PayType_Commission,
                'in_type' => Constants::FinanceInType_Commission,
                'tradeno' => $item['tradeno'] ? $item['tradeno'] : 'AGENT_ORDER_COMMISSION_' . $batchNumber . '_' . genUuid(8),
                'order_id' => $orderId,
                'terminal_type' => Constants::TerminalType_Unknown,
                'money' => $item['money'],
                'created_at' => date('Y-m-d H:i:s'),
                'about' => $item['about'] ? $item['about'] : '代理订单分佣，订单号：' . $orderId,
                'status' => $commisionGrantTime === 1 ? Constants::FinanceStatus_Freeze : Constants::FinanceStatus_Active
            ];
            if ($commisionGrantTime === 0) $finInfo['active_at'] = date('Y-m-d H:i:s');
            if ($item['money'] < 0) {
                $finInfo['in_type'] = Constants::FinanceInType_Unknow;
                $finInfo['out_type'] = Constants::FinanceOutType_Reverse;
            }
            if (is_array($item['chain'])) {
                $i = 1;
                foreach ($item['chain'] as $id) {
                    $finInfo['from_member' . $i] = $id;
                    $i++;
                }
            }
            if ($item['money'] != 0) {
                $financeId = $financeObj->add($finInfo);
                if ($financeId) {
                    $noticeFinanceIDs[] = $financeId;
                }
            }
        }
    }

    /**
     * 扣减单种商品代理正常佣金，一般在发生售后时使用
     * @param $orderIdOrModel
     * @param $orderItemIdOrModel
     * @return mixed|void
     */
    public static function deductAgentOrderCommisionByItem($orderIdOrModel, $orderItemIdOrModel)
    {
        if (is_numeric($orderIdOrModel)) $order = OrderModel::find($orderIdOrModel);
        else $order = $orderIdOrModel;
        // 查询此订单是否已经有生效的正数佣金，如果有，表示此订单之前是付款后就结算佣金的，这时则不添加负数记录，以达到已发放的佣金不能再扣减的目的
        $activeCount = FinanceModel::query()
            ->where('type', Constants::FinanceType_AgentCommission)
            ->where('status', Constants::FinanceStatus_Active)
            ->where('sub_type', Constants::FinanceSubType_AgentCommission_Order)
            ->where('order_id', $order->id)
            ->where('money', '>', 0)
            ->count('id');
        if (is_numeric($orderItemIdOrModel)) $orderItemModel = $order->items->find($orderItemIdOrModel);
        else $orderItemModel = $orderItemIdOrModel;
        $commisionList = json_decode($orderItemModel->agent_order_commision, true);
        if (!$activeCount && $orderItemModel && $orderItemModel->after_sale_over_num > 0) {
            if (is_array($commisionList) && count($commisionList) > 0) {
                foreach ($commisionList as &$commisionItem) {
                    if ($commisionItem['member_id']) {
                        // 防止溢出
                        $orderItemNum = $orderItemModel->num > $orderItemModel->after_sale_over_num ? $orderItemModel->num - $orderItemModel->after_sale_over_num : 0;
                        // 重新计算分佣金额
                        $commisionItem['money'] = $commisionItem['unit_money'] * $orderItemNum;
                    }
                }
                unset($commisionItem);
                // 更新 OrderItemModel
                $orderItemModel->agent_order_commision = json_encode($commisionList);
                $orderItemModel->save();
            }
        }
        return $commisionList;
    }

    /**
     * 添加代理订单销售奖(平级/越级奖)佣金记录，当金额为负数时，表示要将之前的佣金记录扣除一部分（一般在订单退款时才有此类操作）
     * @param $siteId 网站ID
     * @param $orderId 订单ID
     * @param array $commission 分佣表
     * @param $clearOld 清除旧的分佣记录，一般在对订单完全执行重新分佣时才需要设置为1
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function addAgentSaleRewardCommission($siteId, $orderId, array $commission, $clearOld = 0)
    {
        // 查询佣金结算时机
        $commisionGrantTime = intval(AgentBaseSetting::getCurrentSiteSetting()->commision_grant_time);
        // 查询此订单是否已经有生效的正数佣金，如果有，表示此订单之前是付款后就结算佣金的，这时则不添加负数记录，以达到已发放的佣金不能再扣减的目的
        $activeCount = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Active, 'sub_type' => Constants::FinanceSubType_AgentCommission_SaleReward, 'order_id' => $orderId])->where('money', '>', 0)->count('id');
        // 先将此订单之前的分佣记录删除
        if ($clearOld) FinanceModel::where(['type' => Constants::FinanceType_AgentCommission, 'sub_type' => Constants::FinanceSubType_AgentCommission_SaleReward, 'order_id' => $orderId])->delete();
        // 记录新的记录
        $noticeFinanceIDs = [];
        $financeObj = new Finance();
        $batchNumber = date('YmdHis');
        foreach ($commission as $item) {
            if ($activeCount && $item['money'] < 0) continue;
            $finInfo = [
                'site_id' => $siteId,
                'member_id' => $item['member_id'],
                'type' => Constants::FinanceType_AgentCommission,
                'sub_type' => Constants::FinanceSubType_AgentCommission_SaleReward,
                'pay_type' => Constants::PayType_Commission,
                'in_type' => Constants::FinanceInType_Commission,
                'tradeno' => $item['tradeno'] ? $item['tradeno'] : 'AGENT_SALEREWARD_COMMISSION_' . $batchNumber . '_' . genUuid(8),
                'order_id' => $orderId,
                'terminal_type' => Constants::TerminalType_Unknown,
                'money' => $item['money'],
                'created_at' => date('Y-m-d H:i:s'),
                'about' => $item['about'] ? $item['about'] : '代理订单销售奖，订单号：' . $orderId,
                'status' => $commisionGrantTime === 1 ? Constants::FinanceStatus_Freeze : Constants::FinanceStatus_Active
            ];
            if ($commisionGrantTime === 0) $finInfo['active_at'] = date('Y-m-d H:i:s');
            if ($item['money'] < 0) {
                $finInfo['in_type'] = Constants::FinanceInType_Unknow;
                $finInfo['out_type'] = Constants::FinanceOutType_Reverse;
            }
            if (is_array($item['chain'])) {
                $i = 1;
                foreach ($item['chain'] as $id) {
                    $finInfo['from_member' . $i] = $id;
                    $i++;
                }
            }
            if ($item['money'] != 0) {
                $financeId = $financeObj->add($finInfo);
                if ($financeId) {
                    $noticeFinanceIDs[] = $financeId;
                }
            }
        }
        // 发送通知
        foreach ($noticeFinanceIDs as $noticeFinanceID) {
            $financeModel = FinanceModel::query()->where('site_id', $siteId)->where('id', $noticeFinanceID)->first();
            MessageNoticeHelper::sendMessageAgentCommission($financeModel);
        }
    }

    /**
     * 扣减单种商品代理销售奖佣金，一般在发生售后时使用
     * @param $orderIdOrModel
     * @param $orderItemIdOrModel
     * @return mixed|void
     */
    public static function deductAgentSaleRewardCommisionByItem($orderIdOrModel, $orderItemIdOrModel)
    {
        if (is_numeric($orderIdOrModel)) $order = OrderModel::find($orderIdOrModel);
        else $order = $orderIdOrModel;
        // 查询此订单是否已经有生效的正数佣金，如果有，表示此订单之前是付款后就结算佣金的，这时则不添加负数记录，以达到已发放的佣金不能再扣减的目的
        $activeCount = FinanceModel::query()
            ->where('type', Constants::FinanceType_AgentCommission)
            ->where('status', Constants::FinanceStatus_Active)
            ->where('sub_type', Constants::FinanceSubType_AgentCommission_SaleReward)
            ->where('order_id', $order->id)
            ->where('money', '>', 0)
            ->count('id');
        if (is_numeric($orderItemIdOrModel)) $orderItemModel = $order->items->find($orderItemIdOrModel);
        else $orderItemModel = $orderItemIdOrModel;
        $commisionList = json_decode($orderItemModel->agent_sale_reward_commision, true);
        if (!$activeCount && $orderItemModel && $orderItemModel->after_sale_over_num > 0) {
            if (is_array($commisionList) && count($commisionList) > 0) {
                foreach ($commisionList as &$commisionItem) {
                    if ($commisionItem['member_id']) {
                        // 防止溢出
                        $orderItemNum = $orderItemModel->num > $orderItemModel->after_sale_over_num ? $orderItemModel->num - $orderItemModel->after_sale_over_num : 0;
                        // 重新计算分佣金额
                        $commisionItem['money'] = $commisionItem['unit_money'] * $orderItemNum;
                    }
                }
                unset($commisionItem);
                // 更新 OrderItemModel
                $orderItemModel->agent_sale_reward_commision = json_encode($commisionList);
                $orderItemModel->save();
            }
        }
        return $commisionList;
    }

    /**
     * 激活订单的代理佣金，包括正常代理佣金的销售奖(平级/越级奖)，一般在订单完成时使用
     *
     * @param [type] $orderId 订单号
     * @return array 关于此订单的代理佣金汇总记录(已减掉因退款等原因扣除的佣金)
     */
    public static function activeAgentCommisionStatusByOrder($orderId)
    {
        //正常代理佣金
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'sub_type' => Constants::FinanceSubType_AgentCommission_Order, 'order_id' => $orderId]);
        $query->update(['status' => \YZ\Core\Constants::FinanceStatus_Active, 'active_at' => date('Y-m-d H:i:s')]);
        //销售奖
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'sub_type' => Constants::FinanceSubType_AgentCommission_SaleReward, 'order_id' => $orderId]);
        $query->update(['status' => \YZ\Core\Constants::FinanceStatus_Active, 'active_at' => date('Y-m-d H:i:s')]);
        //代理其他奖，暂时只有感恩奖
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'sub_type' => Constants::FinanceSubType_AgentCommission_OtherReward, 'order_id' => $orderId]);
        $query->update(['status' => \YZ\Core\Constants::FinanceStatus_Active, 'active_at' => date('Y-m-d H:i:s')]);
        //返回经过汇总后的佣金记录
        $financeList = [];
        $query = FinanceModel::query()->where(['status' => \YZ\Core\Constants::FinanceStatus_Active, 'type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'sub_type' => Constants::FinanceSubType_AgentCommission_Order, 'order_id' => $orderId]);
        $query = $query->select('member_id', 'order_id', 'type', 'sub_type', 'status', 'active_at')->selectRaw('sum(money) as money')->groupBy('member_id', 'order_id');
        $list = $query->get();
        if ($list) {
            $financeList['normal'] = $list;
        }
        $query = FinanceModel::query()->where(['status' => \YZ\Core\Constants::FinanceStatus_Active, 'type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'sub_type' => Constants::FinanceSubType_AgentCommission_SaleReward, 'order_id' => $orderId]);
        $query = $query->select('member_id', 'order_id', 'type', 'sub_type', 'status', 'active_at')->selectRaw('sum(money) as money')->groupBy('member_id', 'order_id');
        $list = $query->get();
        if ($list) {
            $financeList['salereward'] = $list;
        }
        return $financeList;
    }

    /**
     * 将订单的代理佣金变为失效，包括正常代理佣金的销售奖(平级/越级奖)，一般在订单全部退款时使用
     * 如果佣金发放时间为付款后，那佣金的之前的状态就不是 FinanceStatus_Freeze 状态，那此过程实际上是没有更新任何数据
     * @param [type] $orderId
     * @return boolean
     */
    public static function cancelAgentCommisionByOrder($orderId)
    {
        //正常代理佣金
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'sub_type' => Constants::FinanceSubType_AgentCommission_Order, 'order_id' => $orderId]);
        $query->update(['status' => \YZ\Core\Constants::FinanceStatus_Invalid, 'invalid_at' => date('Y-m-d H:i:s')]);
        //销售奖
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'sub_type' => Constants::FinanceSubType_AgentCommission_SaleReward, 'order_id' => $orderId]);
        $query->update(['status' => \YZ\Core\Constants::FinanceStatus_Invalid, 'invalid_at' => date('Y-m-d H:i:s')]);
        //代理其他奖
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'sub_type' => Constants::FinanceSubType_AgentCommission_OtherReward, 'order_id' => $orderId]);
        $query->update(['status' => \YZ\Core\Constants::FinanceStatus_Invalid, 'invalid_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 删除订单的代理佣金，包括正常代理佣金的销售奖(平级/越级奖)，只有未生效的佣金可以删除，生效状态的不给删除
     *
     * @param [type] $orderId 订单号
     * @return void
     */
    public static function deleteAgentCommisionStatusByOrder($orderId, $where = [])
    {
        //正常代理佣金
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'sub_type' => Constants::FinanceSubType_AgentCommission_Order, 'order_id' => $orderId])->where('status', '<>', \YZ\Core\Constants::FinanceStatus_Active);
        if (count($where)) $query->where($where);
        $query->delete();
        //销售奖
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'sub_type' => Constants::FinanceSubType_AgentCommission_SaleReward, 'order_id' => $orderId])->where('status', '<>', \YZ\Core\Constants::FinanceStatus_Active);
        if (count($where)) $query->where($where);
        $query->delete();
    }

    /**
     * 发放绩效奖励（上一个月、上一个季度、上一年）
     * @param null $baseDate 以哪个时间为准则计算，默认以当前时间
     * @param bool $reset 是否重置（强行清理旧数据，慎用）
     * @param array $outputData 输出的内容
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function grantPerformanceReward($baseDate = null, $reset = false, &$outputData = [])
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $agentPerformanceRewardSetting = AgentPerformanceRewardSetting::getCurrentSiteSetting();
        if (!$agentPerformanceRewardSetting->enable) return;

        $countPeriod = intval($agentPerformanceRewardSetting->count_period);
        $givePeriod = intval($agentPerformanceRewardSetting->give_period);
        $giveAgentLevel = intval($agentPerformanceRewardSetting->give_agent_level);
        $isAutoCheck = $agentPerformanceRewardSetting->auto_check ? true : false;

        $timeStamp = $baseDate ? strtotime($baseDate) : time(); // 时间戳
        $givePeriodSign = '';
        $timeStart = ''; // 起始时间（包含）
        $timeEnd = ''; // 结束时间（不包含）
        if ($givePeriod == 2) {
            // 按年
            $lastYear = date('Y', strtotime('-1 year', $timeStamp));
            $timeStart = $lastYear . '-01-01 00:00:00';
            $timeEnd = date('Y', $timeStamp) . '-01-01 00:00:00';
            $givePeriodSign = $lastYear;
        } else if ($givePeriod == 1) {
            // 按季度
            $lastTime = strtotime('-3 month', $timeStamp);
            $lastQuarter = ceil(intval(date('n', $lastTime)) / 3);
            $lastQuarterMonth = 1 + ($lastQuarter - 1) * 3;
            $lastQuarterMonth = $lastQuarterMonth < 10 ? '0' . $lastQuarterMonth : $lastQuarterMonth;
            $timeStart = date('Y', $lastTime) . '-' . $lastQuarterMonth . '-01 00:00:00';

            $thisQuarter = ceil(intval(date('n', $timeStamp)) / 3);
            $thisQuarterMonth = 1 + ($thisQuarter - 1) * 3;
            $thisQuarterMonth = $thisQuarterMonth < 10 ? '0' . $thisQuarterMonth : $thisQuarterMonth;
            $timeEnd = date('Y', $timeStamp) . '-' . $thisQuarterMonth . '-01 00:00:00';

            $givePeriodSign = date('Y', $lastTime) . '_' . $lastQuarter;
        } else {
            // 按月
            $lastTime = strtotime('-1 month', $timeStamp);
            $timeStart = date('Y-m', $lastTime) . '-01 00:00:00';
            $timeEnd = date('Y-m', $timeStamp) . '-01 00:00:00';
            $givePeriodSign = date('Y_n', $lastTime);
        }
        $givePeriodSign = $givePeriod . "_" . $givePeriodSign;
        $outputData = [
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'period_sign' => $givePeriodSign,
        ];
        // 强行清理旧数据
        if ($reset) {
            // 清理奖励数据
            AgentPerformanceRewardModel::query()
                ->where('site_id', $siteId)
                ->where('period', $givePeriodSign)
                ->delete();
            // 清理财务
            FinanceModel::query()
                ->where('site_id', $siteId)
                ->where('type', Constants::FinanceType_AgentCommission)
                ->where('sub_type', Constants::FinanceSubType_AgentCommission_Performance)
                ->where('order_id', AgentPerformanceReward::buildFinanceOrderId($givePeriodSign))
                ->delete();
        }
        // 统计业绩数据
        $performanceData = AgentPerformanceModel::query()
            ->where('site_id', $siteId)
            ->where('count_period', $countPeriod)
            ->where('order_time', '>=', $timeStart)
            ->where('order_time', '<', $timeEnd)
            ->groupBy('member_id')
            ->select('member_id', DB::raw('sum(money) as total_money'))
            ->get()->pluck('total_money', 'member_id')
            ->toArray();
        if (count($performanceData) == 0) return;
        // 获取用户数据
        $memberIds = array_keys($performanceData);
        $memberData = MemberModel::query()
            ->where('site_id', $siteId)
            ->whereIn('id', $memberIds)
            ->select('id', 'agent_level', 'agent_parent_id')
            ->get();
        $memberList = [];
        foreach ($memberData as $memberDataItem) {
            $memberList[$memberDataItem->id] = $memberDataItem;
        }
        // 获取业绩奖励规则，按代理等级，目标从大到小排序
        $ruleData = AgentPerformanceRewardRuleModel::query()
            ->where('site_id', $siteId)
            ->orderBy('agent_level')
            ->orderByDesc('target')
            ->get();
        if (count($ruleData) == 0) return;
        // 处理规则
        $ruleList = [];
        foreach ($ruleData as $ruleDataItem) {
            $ruleList[$ruleDataItem->agent_level][] = [
                'target' => intval($ruleDataItem->target),
                'reward_type' => intval($ruleDataItem->reward_type),
                'reward' => intval($ruleDataItem->reward),
            ];
        }
        // 获取已经发过奖励的会员
        $existMemberIds = AgentPerformanceRewardModel::query()
            ->where('site_id', $siteId)
            ->where('period', $givePeriodSign)
            ->select('member_id')->get()
            ->pluck('member_id')->toArray();
        // 处理数据
        $insertRewardData = []; // 插入的奖励数据
        $insertFinanceData = []; // 插入的财务数据
        $now = date('Y-m-d H:i:s'); // 当前时间
        foreach ($memberIds as $memberId) {
            $performance = intval($performanceData[$memberId]);
            $agentLevel = intval($memberList[$memberId]->agent_level);
            if ($agentLevel == 0 || $performance == 0 || !$ruleList[$agentLevel]) continue;
            if (in_array($memberId, $existMemberIds)) continue; // 如果已经发过奖励就不发了，保证等幂性
            if ($giveAgentLevel == 0 && intval($memberList[$memberId]->agent_parent_id) != 0) continue; // 只发给最高级别
            foreach ($ruleList[$agentLevel] as $ruleItem) {
                if ($performance >= $ruleItem['target']) {
                    // 达到业绩目标
                    $performanceReward = $ruleItem['reward_type'] == 1 ? ceil($performance * ($ruleItem['reward'] / 10000)) : $ruleItem['reward'];
                    // 奖励数据
                    $rewardData = [
                        'site_id' => $siteId,
                        'member_id' => $memberId,
                        'member_agent_level' => $agentLevel,
                        'reward_money' => $performanceReward,
                        'performance_money' => $performance,
                        'status' => $isAutoCheck ? LibsConstants::AgentRewardStatus_Active : LibsConstants::AgentRewardStatus_Freeze,
                        'created_at' => $now,
                        'checked_at' => $isAutoCheck ? $now : null,
                        'period' => $givePeriodSign,
                    ];
                    $insertRewardData[] = $rewardData;
                    if ($isAutoCheck) {
                        // 财务数据
                        $financeData = AgentPerformanceReward::buildFinanceData($rewardData);
                        if ($financeData) {
                            $insertFinanceData[] = $financeData;
                        }
                    }
                    break;
                }
            }
        }
        // 批量插入数据
        if (count($insertRewardData) > 0) {
            DB::table('tbl_agent_performance_reward')->insert($insertRewardData);
        }
        if (count($insertFinanceData) > 0) {
            DB::table('tbl_finance')->insert($insertFinanceData);
        }
        // 发送通知
        if (count($insertFinanceData) > 0) {
            foreach ($insertFinanceData as $insertFinanceDataItem) {
                $noticeFinanceModel = new FinanceModel();
                $noticeFinanceModel->fill($insertFinanceDataItem);
                MessageNoticeHelper::sendMessageAgentCommission($noticeFinanceModel);
            }
        }
    }

    /**
     * 统计业绩，包含运费
     * @param OrderModel $order 订单Model
     * @param int $money 单位：分
     * @param int $countPeriod 时期，0=付款后，1=维权期后
     * @param bool $reset 是否重置
     */
    public static function buildOrderPerformance($order, int $money, $countPeriod = 0, $reset = false)
    {
        if (!$order || !$order->id || $money < 0) return;
        $orderId = $order->id;
        $countPeriod = intval($countPeriod);
        $siteId = Site::getCurrentSite()->getSiteId();
        // 清理旧数据
        if ($reset) {
            AgentPerformanceModel::query()->where('site_id', $siteId)
                ->where('order_id', $orderId)
                ->where('count_period', $countPeriod)
                ->delete();
        }
        // 查找订单数据
        $orderModel = OrderModel::query()->where('site_id', $siteId)->where('id', $orderId)->first();
        if (!$orderModel || !$orderModel->pay_at) return; // 必须付款了
        // 售后期比需数据完整
        if ($countPeriod && (!$orderModel->end_at || intval($orderModel->status) != LibsConstants::OrderStatus_OrderFinished)) return;

        $agents = [];
        if ($countPeriod || $reset) {
            // 如果是维权期后 或 重置，读取 付款后 记录的代理关系
            $agentPerformance = $orderModel->agent_performance;
            if ($agentPerformance) {
                $agentPerformance = json_decode($agentPerformance, true);
                if (is_array($agentPerformance['agents'])) {
                    $agents = $agentPerformance['agents'];
                }
            }
        } else {
            $agents = AgentHelper::getParentAgents($orderModel->member_id)['normal'];
            if (!is_array($agents)) $agents = [];
            // 记录代理关系
            $agentsLog = [];
            foreach ($agents as $agent) {
                $agentsLog[] = [
                    'id' => $agent['id'],
                    'agent_level' => $agent['agent_level'],
                ];
            }
            OrderModel::query()->where('site_id', $siteId)->where('id', $orderId)->update([
                "agent_performance" => json_encode([
                    'agents' => $agentsLog,
                ]),
            ]);
        }
        if (!is_array($agents) || count($agents) == 0) {
            return;
        }
        // 查找历史数据
        $existMemberIds = AgentPerformanceModel::query()->where('site_id', $siteId)
            ->where('order_id', $orderId)
            ->where('count_period', $countPeriod)
            ->select('member_id')->get()
            ->pluck('member_id')->toArray();
        $insertDataList = []; // 插入的数据数组
        $now = date('Y-m-d H:i:s');
        $setting = AgentBaseSetting::getCurrentSiteSetting();
        foreach ($agents as $agent) {
            // 设置了自购不计算业绩
            if (!$setting->internal_purchase_performance && $agent['id'] == $order->member_id) {
                continue;
            }
            if (!in_array($agent['id'], $existMemberIds)) {
                $insertDataList[] = [
                    'site_id' => $siteId,
                    'member_id' => $agent['id'],
                    'money' => intval($money),
                    'order_id' => $orderId,
                    'count_period' => intval($countPeriod),
                    'order_time' => $countPeriod ? $orderModel->end_at : $orderModel->pay_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        // 批量插入数据
        if (count($insertDataList) > 0) {
            DB::table('tbl_agent_performance')->insert($insertDataList);
        }
    }

    /**
     * 出现售后时要扣除业绩（付款后）
     * @param $orderId
     */
    public static function buildOrderPerformanceForAfterSale($orderId)
    {
        if (!$orderId) return;
        $countPeriod = 0;
        $siteId = Site::getCurrentSite()->getSiteId();
        $orderModel = OrderModel::query()
            ->where('site_id', $siteId)
            ->where('id', $orderId)
            ->first();
        if (!$orderModel) return;
        // 检查业绩是否存在
        $performanceList = AgentPerformanceModel::query()
            ->where('site_id', $siteId)
            ->where('order_id', $orderId)
            ->where('count_period', $countPeriod)
            ->where('money', '>', '0')
            ->get();
        if (count($performanceList) == 0) return;
        // 计算出总共退款了多少钱
        $totalRealMoney = AfterSaleModel::query()
            ->where('site_id', $siteId)
            ->where('order_id', $orderId)
            ->where('status', LibsConstants::RefundStatus_Over)
            ->where('real_money', '>', 0)
            ->sum('real_money');
        $totalRealMoney = intval($totalRealMoney);
        if ($totalRealMoney <= 0) return;
        $orderMoney = intval($orderModel->money) - abs(intval($orderModel->freight));
        // 防止溢出
        if ($totalRealMoney > $orderMoney) $totalRealMoney = $orderMoney;
        $refundPerformanceExist = AgentPerformanceModel::query()
            ->where('site_id', $siteId)
            ->where('order_id', $orderId)
            ->where('count_period', $countPeriod)
            ->where('money', '<', '0')
            ->count();
        $now = date('Y-m-d H:i:s');
        if ($refundPerformanceExist > 0) {
            // 如果已经有退款业绩，则直接更新数据库
            AgentPerformanceModel::query()
                ->where('site_id', $siteId)
                ->where('order_id', $orderId)
                ->where('count_period', $countPeriod)
                ->where('money', '<', '0')
                ->update([
                    'money' => -1 * $totalRealMoney,
                    'updated_at' => $now,
                ]);
        } else {
            // 如果没有退款业绩，则插入数据
            $insertDataList = [];
            foreach ($performanceList as $performanceItem) {
                $insertDataList[] = [
                    'site_id' => $siteId,
                    'member_id' => $performanceItem['member_id'],
                    'money' => -1 * $totalRealMoney,
                    'order_id' => $orderId,
                    'count_period' => intval($performanceItem['count_period']),
                    'order_time' => $performanceItem['order_time'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            // 批量插入
            if (count($insertDataList) > 0) {
                DB::table('tbl_agent_performance')->insert($insertDataList);
            }
        }
    }

    /**
     * 发放推荐奖励
     * @param $memberId
     * @param int $newAgentLevel
     * @return bool
     * @throws \Exception
     */
    public static function grantRecommendReward($memberId, $newAgentLevel = -1)
    {
        $siteId = Site::getCurrentSite()->getSiteId();
        $setting = AgentRecommendRewardSetting::getCurrentSiteSetting();
        $isAutoCheck = $setting->auto_check ? true : false;
        $result = AgentReward::calRecommendRewardCommisionMoney($memberId, $newAgentLevel, $setting);
        if ($result) {
            $newAgentLevel = intval($result['sub_member_agent_level']);
            $agentRecommendReward = new AgentRecommendReward();
            $agentRecommendReward->add([
                'site_id' => $siteId,
                'member_id' => $result['member_id'],
                'member_agent_level' => $result['member_agent_level'],
                'sub_member_id' => $result['sub_member_id'],
                'sub_member_agent_level' => $newAgentLevel,
                'reward_money' => $result['reward_money'],
                'status' => LibsConstants::AgentRewardStatus_Freeze,
            ], true);
            if ($isAutoCheck) {
                // 审核通过
                $agentRecommendReward->pass();
            }
            return true;

        }
        return false;
    }

    /**
     * 计算推荐奖励佣金
     * @param $memberId
     * @param $newAgentLevel
     * @param $setting
     * @return array|bool
     */
    public static function calRecommendRewardCommisionMoney($memberId, $newAgentLevel, $setting = null)
    {
        if (!$setting) {
            $setting = AgentRecommendRewardSetting::getCurrentSiteSetting();
        }
        // 是否开启
        if (!$setting->enable || !is_array($setting->commision)) return false;
        $newAgentLevel = intval($newAgentLevel);
        $baseSetting = new AgentBaseSetting();
        $maxLevel = intval($baseSetting->getSettingModel()->level);
        // 获取会员信息
        $memberModel = MemberModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $memberId)
            ->select('id', 'agent_level', 'invite1')
            ->first();
        if (!$memberModel || !$memberModel->invite1) return false;
        if ($newAgentLevel < 0) $newAgentLevel = intval($memberModel->agent_level);
        if ($newAgentLevel == 0) return false; // 非代理不处理
        // 获取上级会员信息
        $parentModel = MemberModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $memberModel->invite1)
            ->where('agent_level', '>', 0)
            ->select('id', 'agent_level')
            ->first();
        if (!$parentModel) return false;
        $parentAgentLevel = intval($parentModel->agent_level);
        // 检查代理等级是否有效
        if ($maxLevel < $newAgentLevel || $maxLevel < $parentAgentLevel) return false;
        $sign = 'normal';
        if ($newAgentLevel > $parentAgentLevel) $sign = 'low_level';
        else if ($newAgentLevel == $parentAgentLevel) $sign = 'same_level';
        $commisionKey = $sign . '_' . $parentAgentLevel . '_' . $newAgentLevel;
        $commision = $setting->commision;
        if ($commision[$commisionKey] && $commision[$commisionKey]['reward']) {
            return [
                'sub_member_id' => $memberId,
                'sub_member_agent_level' => $newAgentLevel,
                'member_id' => $parentModel->id,
                'member_agent_level' => $parentAgentLevel,
                'reward_money' => intval($commision[$commisionKey]['reward']),
                'sign' => $sign,
                'config' => $commision[$commisionKey],
            ];
        }
        return false;
    }

    /**
     * 将代理佣金的金额写回去，目前是用在当订单完全售后的情况下，将佣金金额更新回去，好让前后台有相应的展示
     * @return mixed
     */
    public static function restoreOrderCommissionByOrder($orderIdOrModel)
    {
        if (is_numeric($orderIdOrModel)) $oderModel = OrderModel::find($orderIdOrModel);
        else $oderModel = $orderIdOrModel;
        // 重新计算每个商品的佣金
        $itemCommisions = [];
        foreach ($oderModel->items as $item) {
            $itemCommisions = array_merge($itemCommisions, static::restoreOrderCommissionByItem($oderModel, $item));
        }
        // 汇总每个商品的佣金
        $memberMoneys = [];
        foreach ($itemCommisions as $item) {
            if (!$item['member_id']) continue;
            if (!$memberMoneys[$item['member_id']]) $memberMoneys[$item['member_id']] = $item['money'];
            else $memberMoneys[$item['member_id']] += $item['money'];
        }
        // 重新写回总佣金值
        $oldCommision = json_decode($oderModel->agent_order_commision, true);
        if (!is_array($oldCommision)) return [];
        if (!count($oldCommision)) return [];
        foreach ($oldCommision as $key => $val) {
            if (!$val['member_id']) continue;
            $oldCommision[$key]['money'] = $memberMoneys[$val['member_id']];
            FinanceModel::whereIn('status', [\YZ\Core\Constants::FinanceStatus_Freeze, \YZ\Core\Constants::FinanceStatus_Invalid])->
            where([
                'order_id' => $oderModel->id,
                'type' => \YZ\Core\Constants::FinanceType_AgentCommission,
                'sub_type' => \YZ\Core\Constants::FinanceSubType_AgentCommission_Order,
                'member_id' => $val['member_id']
            ])
                ->update(['money' => $memberMoneys[$val['member_id']], 'money_real' => $memberMoneys[$val['member_id']]]);
        }
        $oderModel->agent_order_commision = json_encode($oldCommision);
        $oderModel->save();
        return $oldCommision;
    }

    /**
     * 将代理佣金的金额写回去，目前是用在当订单完全售后的情况下，将佣金金额更新回去，好让前后台有相应的展示
     * @return mixed
     */
    public static function restoreOrderCommissionByItem($orderIdOrModel, $orderItemIdOrOrderItemModel)
    {
        if (is_numeric($orderIdOrModel)) $oderModel = OrderModel::find($orderIdOrModel);
        else $oderModel = $orderIdOrModel;
        if (is_numeric($orderItemIdOrOrderItemModel)) $oderItemModel = OrderItemModel::find($orderItemIdOrOrderItemModel);
        else $oderItemModel = $orderItemIdOrOrderItemModel;
        if ($oderItemModel && $oderItemModel->after_sale_over_num >= $oderItemModel->num) {
            $oldCommision = json_decode($oderItemModel->agent_order_commision, true);
            if (!is_array($oldCommision)) return [];
            if (!count($oldCommision)) return [];
            foreach ($oldCommision as $key => $val) {
                if ($val['member_id']) {
                    $money = $val['unit_money'] * $oderItemModel->num;
                    $oldCommision[$key]['money'] = $money;
                }
            }
            $oderItemModel->agent_order_commision = json_encode($oldCommision);
            $oderItemModel->save();
            return $oldCommision;
        }
    }

    /**
     * 将代理佣金的金额写回去，目前是用在当订单完全售后的情况下，将佣金金额更新回去，好让前后台有相应的展示
     * @return mixed
     */
    public static function restoreSaleRewardCommissionByOrder($orderIdOrModel)
    {
        if (is_numeric($orderIdOrModel)) $oderModel = OrderModel::find($orderIdOrModel);
        else $oderModel = $orderIdOrModel;
        // 重新计算每个商品的佣金
        $itemCommisions = [];
        foreach ($oderModel->items as $item) {
            $itemCommisions = array_merge($itemCommisions, static::restoreSaleRewardCommissionByItem($oderModel, $item));
        }
        // 汇总每个商品的佣金
        $memberMoneys = [];
        foreach ($itemCommisions as $item) {
            if (!$item['member_id']) continue;
            if (!$memberMoneys[$item['member_id']]) $memberMoneys[$item['member_id']] = $item['money'];
            else $memberMoneys[$item['member_id']] += $item['money'];
        }
        // 重新写回总佣金值
        $oldCommision = json_decode($oderModel->agent_sale_reward_commision, true);
        if (!is_array($oldCommision)) return [];
        if (!count($oldCommision)) return [];
        foreach ($oldCommision as $key => $val) {
            if (!$val['member_id']) continue;
            $oldCommision[$key]['money'] = $memberMoneys[$val['member_id']];
            FinanceModel::whereIn('status', [\YZ\Core\Constants::FinanceStatus_Freeze, \YZ\Core\Constants::FinanceStatus_Invalid])
                ->where([
                    'order_id' => $oderModel->id,
                    'type' => \YZ\Core\Constants::FinanceType_AgentCommission,
                    'sub_type' => \YZ\Core\Constants::FinanceSubType_AgentCommission_SaleReward,
                    'member_id' => $val['member_id']
                ])
                ->update(['money' => $memberMoneys[$val['member_id']], 'money_real' => $memberMoneys[$val['member_id']]]);
        }
        $oderModel->agent_sale_reward_commision = json_encode($oldCommision);
        $oderModel->save();
        return $oldCommision;
    }

    /**
     * 将代理佣金的金额写回去，目前是用在当订单完全售后的情况下，将佣金金额更新回去，好让前后台有相应的展示
     * @return mixed
     */
    public static function restoreSaleRewardCommissionByItem($orderIdOrModel, $orderItemIdOrOrderItemModel)
    {
        if (is_numeric($orderIdOrModel)) $oderModel = OrderModel::find($orderIdOrModel);
        else $oderModel = $orderIdOrModel;
        if (is_numeric($orderItemIdOrOrderItemModel)) $oderItemModel = OrderItemModel::find($orderItemIdOrOrderItemModel);
        else $oderItemModel = $orderItemIdOrOrderItemModel;
        if ($oderItemModel && $oderItemModel->after_sale_over_num >= $oderItemModel->num) {
            $oldCommision = json_decode($oderItemModel->agent_sale_reward_commision, true);
            if (!is_array($oldCommision)) return [];
            if (!count($oldCommision)) return [];
            foreach ($oldCommision as $key => $val) {
                if ($val['member_id']) {
                    $money = $val['unit_money'] * $oderItemModel->num;
                    $oldCommision[$key]['money'] = $money;
                }
            }
            $oderItemModel->agent_sale_reward_commision = json_encode($oldCommision);
            $oderItemModel->save();
            return $oldCommision;
        }
    }
}