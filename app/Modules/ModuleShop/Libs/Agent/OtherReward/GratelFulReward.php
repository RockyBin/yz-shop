<?php

namespace App\Modules\ModuleShop\Libs\Agent\OtherReward;

use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use App\Modules\ModuleShop\Libs\Agent\AgentOtherReward;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Model\AgentOtherRewardModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\Order;
use YZ\Core\Logger\Log;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\AgentOtherRewardSettingModel;

/**
 * 代理感恩奖
 */
class GratelFulReward
{
    /**
     * 添加感恩奖的奖金
     * @param  $orderId 订单ID
     * @type 其他奖的type
     */
    static function calcGrateFul($orderId, $setting)
    {
        $baseSetting = AgentBaseSetting::getCurrentSiteSetting();
        // 如果此订单已经生成感恩奖了，就不允许再次生成奖
        $agentOtherRewardCount = AgentOtherRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('order_id', $orderId)
            ->count();
        if ($agentOtherRewardCount > 0) return false;
        if ($baseSetting->level > 0 && $setting->status == 1) {
            //  先把一级代理佣金拿出来
            $order = OrderModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('id', $orderId)
                ->first();
            if ($order->agent_order_commision) {
                $agentOrderCommision = json_decode($order->agent_order_commision, true);
                // 获取支付 代理分佣的金额 (未经计算的原始值) 扣减后代理分后的金额  一级代理的会员ID（即分钱的人）
                $agentCommisionInfo = static::getAgentRewardPayMoneyAndMember($agentOrderCommision, $setting->commision);
                $money = $agentCommisionInfo['money'];// 代理分佣的金额
                $commisionItemMoney = $agentCommisionInfo['commisionItemMoney'];
                $memberId = $agentCommisionInfo['memberId'];// 一级代理的会员ID
                // 获取支付 分销商分佣的金额（原始，未经计算的） 以及 一级代理的会员ID（即分钱的人）
                if ($memberId > 0 && $order->commission) {
                    $distributionCommission = json_decode($order->commission, true);
                    if ($distributionCommission) {
                        $disCommission = static::getDistributionCommissionPayMoney($distributionCommission, $memberId, $setting->commision);
                        $disCommissionMoney = $disCommission['disCommissionMoney'];
                        $disCommissionReward = $disCommission['money'];
                    }
                }
                // 所有平级代理拿出来
                $allSameAgents = AgentHelper::getSameLevelMember($memberId);
                if ($allSameAgents && $setting->people_num > 0 && $money > 1) {
                    //分钱才去改 更改订单代理的分佣金额
                    $order->agent_order_commision = json_encode($agentOrderCommision);
                    // 更改订单分销的分佣的金额
                    if ($disCommissionReward) {
                        $order->commission = json_encode($distributionCommission);
                    }
                    $order->save();
                    //分钱才去改  扣除订单代理的分红
                    static::changeOriginRewardFinance($memberId, $order->id, CoreConstants::FinanceType_AgentCommission, CoreConstants::FinanceSubType_AgentCommission_Order, $commisionItemMoney);
                    $sameAgents = array_slice($allSameAgents, 0, $setting->people_num);
                    foreach ($sameAgents as $member) {
                        $sameAgentsMemberChain[] = $member['id'];
                    }
                    // 感恩奖分的总额
                    $rewardTotalMoney = $money * ($setting->commision / 100);
                    // 若是此一级代理在这订单有分销佣金则需要拿取部分分销佣金分发
                    if ($disCommissionReward) {
                        // 更改财务记录 扣除原分销佣金
                        static::changeOriginDistributionCommissionFinance($memberId, $order->id, CoreConstants::FinanceType_Commission, $disCommissionMoney);
                        // 感恩奖累加分销佣金
                        $rewardTotalMoney += ($disCommissionReward * ($setting->commision / 100));
                    }
                    $i = 0;
                    foreach ($sameAgents as $item) {
                        // 分到最后一人的时候直接全部分给他，不需再乘以比例
                        // 奖励金额是个数列 奖励金额 = 总的奖励金额 * 分成比例的N次方 * (1-分成比例）  比例是百分比
                        $i++;
                        $commision = $setting->commision / 100;
                        if ($i == $setting->people_num || $i == count($sameAgents)) {
                            $rewardMoney = $rewardTotalMoney * pow($commision, $i - 1);
                        } else {
                            $rewardMoney = $rewardTotalMoney * pow($commision, $i - 1) * (1 - $commision);

                        }
                        //分到最后不足2分钱，按照一分钱来算
                        if ($rewardMoney < 2) $rewardMoney = 1;
                        // 添加额外奖的记录
                        $agentOtherReward = new AgentOtherRewardModel();
                        $agentOtherReward->site_id = getCurrentSiteId();
                        $agentOtherReward->type = Constants::AgentOtherRewardType_Grateful;
                        $agentOtherReward->reward_member_id = $item['id'];
                        $agentOtherReward->pay_reward_member_id = $memberId;
                        $agentOtherReward->order_id = $orderId;
                        $agentOtherReward->status = $order->has_agent_order_commision;
                        $agentOtherReward->reward_money = intval($rewardMoney);
                        $agentOtherReward->reward_setting = json_encode(['commision' => $setting->commision, 'people_num' => $setting->people_num, 'chain' => $sameAgentsMemberChain, 'origin_reward_money' => intval($rewardMoney)]);
                        $agentOtherReward->created_at = date('Y-m-d H:i:s');
                        if ($order->has_agent_order_commision == 2) {
                            $agentOtherReward->success_at = date('Y-m-d H:i:s');
                        }
                        $agentOtherReward->save();
                        // 添加财务记录
                        $fin = new Finance();
                        $finInfo = [
                            'site_id' => getCurrentSiteId(),
                            'member_id' => $item['id'], // 得奖人
                            'type' => CoreConstants::FinanceType_AgentCommission,
                            'tradeno' => 'AOR_' . $orderId . randInt(1000),
                            'pay_type' => CoreConstants::PayType_Commission,
                            'sub_type' => CoreConstants::FinanceSubType_AgentCommission_OtherReward,
                            'in_type' => CoreConstants::FinanceInType_Commission,
                            'order_id' => $orderId,
                            'order_type' => CoreConstants::FinanceOrderType_Normal,
                            'operator' => '',
                            'terminal_type' => getCurrentTerminal(),
                            'money' => intval($rewardMoney),
                            'money_real' => intval($rewardMoney),
                            'is_real' => 0,
                            'created_at' => date('Y-m-d H:i:s'),
                            'about' => '代理订单感恩奖，订单号：' . $orderId,
                            'status' => $order->has_agent_order_commision == 2 ? CoreConstants::FinanceStatus_Active : CoreConstants::FinanceStatus_Freeze,// 因为分佣节点有可能是付款后，如果分佣状态是结算成功，直接把财务记录转为有效。
                        ];
                        if ($finInfo['status'] == CoreConstants::FinanceStatus_Active) $finInfo['active_at'] = date('Y-m-d H:i:s');
                        $fin->add($finInfo);
                        // 分钱分到1分钱就退出
                        if ($rewardMoney <= 1) break;
                    }
                }
            }
        }
    }

    /**
     *  获取支付 代理分佣的金额 (未经计算的原始值) 扣减后代理分后的金额  一级代理的会员ID（即分钱的人）
     * @param  $agentOrderCommision 代理分佣的具体数据
     * @param  $commision 感恩奖分红比率
     * @return  array ['money','commisionItemMoney','memberId'];
     */
    static function getAgentRewardPayMoneyAndMember(&$agentOrderCommision, $commision): array
    {
        $CommisionitemMoney = 0;
        $memberId = 0;
        foreach ($agentOrderCommision as &$Commisionitem) {
            if ($Commisionitem['agent_level'] == 1) {
                $money = $Commisionitem['money'];
                $memberId = $Commisionitem['member_id'];
                if ($money > 1) {
                    $Commisionitem['money'] = intval($money - ($money * ($commision / 100)));
                    $CommisionitemMoney = $Commisionitem['money'];
                }
            }
        };
        return ['money' => $money, 'commisionItemMoney' => $CommisionitemMoney, 'memberId' => $memberId];
    }

    /**
     *  获取支付 获取支付 分销分佣的金额 (未经计算的原始值) 扣减感恩奖后分销商的金额（付奖人最后获得的分销佣金）
     * @param  $distributionCommission 代理分佣的具体数据
     * @param  $memberId 一级代理的MemberId
     * @param  $commision 感恩奖分红比率
     * @return ['money','disCommissionMoney'] money 分销
     */
    static function getDistributionCommissionPayMoney(&$distributionCommission, $memberId, $commision): array
    {
        $disCommissionMoney = 0;
        $money = 0;
        foreach ($distributionCommission as &$disCommissionItem) {
            if ($disCommissionItem['member_id'] == $memberId && $disCommissionItem['money'] > 1) {
                $money = $disCommissionItem['money'];
                $disCommissionItem['money'] = intval($money - ($money * ($commision / 100)));
                $disCommissionMoney = $disCommissionItem['money'];
            }
        }
        return ['money' => $money, 'disCommissionMoney' => $disCommissionMoney];
    }

    /**
     *  获取产生退款后 获取支付 分销分佣的金额 (未经计算的原始值) 扣减感恩奖后分销商的金额（付奖人最后获得的分销佣金）
     * @param  $distributionCommission 代理分佣的具体数据
     * @param  $memberId 一级代理的MemberId
     * @param  $commision 感恩奖分红比率
     * @return ['money','disCommissionMoney'] money 分销
     */
    static function getAfterSaleDistributionCommissionPayMoney(&$distributionCommission, $memberId, $commision, $order): array
    {
        // 分销的退款 佣金的计算 并不是重新用产品计算的，所以当感恩奖产生之后，分销的佣金退款是会产生错误的
        // 需要用order_item里面的金额进行重新计算
        $disCommissionMoney = 0;
        $money = 0;
        // 计算分佣总金额
        $totalCommissionMoney = 0;
        $orderItem = $order->items()->get();
        foreach ($orderItem as $item) {
            if ($item->commission) {
                $itemCommission = json_decode($item->commission, true);
                foreach ($itemCommission as $commission) {
                    if ($memberId == $commission['member_id']) {
                        $totalCommissionMoney += $commission['money'];
                    }
                }
            }
        }
        $disCommissionMoney = intval($totalCommissionMoney - ($totalCommissionMoney * ($commision / 100)));
        // 更改订单的commission
        foreach ($distributionCommission as &$disCommissionItem) {
            if ($disCommissionItem['member_id'] == $memberId && $disCommissionItem['money'] > 1) {
                $disCommissionItem['money'] = $disCommissionMoney;
            }
        }

        return ['money' => $totalCommissionMoney, 'disCommissionMoney' => $disCommissionMoney];
    }

    /**
     * 退款时计算感恩奖的奖金 退款是的计算需要用当时的比率
     * @param  $orderId 订单ID
     * @type 其他奖的type
     */
    static function calcAfterSaleReward($orderId)
    {
        //  先把一级代理佣金拿出来
        $order = OrderModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('id', $orderId)
            ->first();
        $agentOtherReward = AgentOtherRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('order_id', $orderId)
            ->where('type', Constants::AgentOtherRewardType_Grateful)
            ->first();
        // 感恩奖已发放的话或者找不到任何奖励记录，不做任何处理
        if ($agentOtherReward->status == 2 || !$agentOtherReward) return false;
        $setting = json_decode($agentOtherReward->reward_setting, true);
        $commision = $setting['commision'] / 100;
        if ($order->agent_order_commision && $agentOtherReward) {
            $agentOrderCommision = json_decode($order->agent_order_commision, true);
            //获取支付 代理分佣的金额 (未经计算的原始值) 扣减后代理分后的金额  一级代理的会员ID（即分钱的人）
            $agentCommisionInfo = static::getAgentRewardPayMoneyAndMember($agentOrderCommision, $setting['commision']);
            $money = $agentCommisionInfo['money'];// 代理分佣的金额
            $commisionItemMoney = $agentCommisionInfo['commisionItemMoney'];
            $memberId = $agentCommisionInfo['memberId'];// 一级代理的会员ID
            static::changeOriginRewardFinance($memberId, $order->id, CoreConstants::FinanceType_AgentCommission, CoreConstants::FinanceSubType_AgentCommission_Order, $commisionItemMoney);
            //  获取支付 获取支付 代理分佣的金额 (未经计算的原始值) 分销售分佣的金额（已经是扣减过感恩奖的金额）
            if ($memberId > 0 && $order->commission) {
                $distributionCommission = json_decode($order->commission, true);
                if ($distributionCommission) {
                    $disCommission = static::getAfterSaleDistributionCommissionPayMoney($distributionCommission, $memberId, $setting['commision'], $order);
                    $disCommissionMoney = $disCommission['disCommissionMoney'];
                    $disCommissionReward = $disCommission['money'];
                }
            }
            // 更改订单代理的分佣金额
            $order->agent_order_commision = json_encode($agentOrderCommision);
            // 更改订单分销的分佣金额
            $order->commission = json_encode($distributionCommission);
            $order->save();
            // 因为退款 分红已重新计算，所以奖金也需要重新计算
            $agentOtherRewardQuery = AgentOtherRewardModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('order_id', $orderId)
                ->where('type', Constants::AgentOtherRewardType_Grateful);
            if ($setting['chain']) {
                $agentOtherRewardList = $agentOtherRewardQuery->orderByRaw("find_in_set(id,'" . implode(',', $setting['chain']) . "')");
            }
            $agentOtherRewardList = $agentOtherRewardQuery->get();
            $agentOtherRewardCount = $agentOtherRewardQuery->count();
            $rewardTotalMoney = $money * $commision;
            // 若是此一级代理在这订单有分销佣金则需要拿取部分分销佣金分发
            if ($disCommissionReward) {
                // 更改财务记录 扣除原分销佣金
                static::changeOriginDistributionCommissionFinance($memberId, $order->id, CoreConstants::FinanceType_Commission, $disCommissionMoney);
                // 感恩奖累加分销佣金
                $rewardTotalMoney += ($disCommissionReward * $commision);
            }
            $i = 0;
            if ($agentOtherRewardList) {
                foreach ($agentOtherRewardList as $item) {
                    $i++;
                    if ($i == $agentOtherRewardCount) {
                        $rewardMoney = $rewardTotalMoney * pow($commision, $i - 1);
                    } else {
                        $rewardMoney = $rewardTotalMoney * pow($commision, $i - 1) * (1 - $commision);
                    }
                    $rewardCommision [] = ['id' => $item['id'], 'reward_money' => intval($rewardMoney)];
                    static::changeOriginRewardFinance($item['reward_member_id'], $order->id, CoreConstants::FinanceType_AgentCommission, CoreConstants::FinanceSubType_AgentCommission_OtherReward, intval($rewardMoney));
                }
                (new AgentOtherRewardModel())->updateBatch($rewardCommision);
            }

        }

    }

    static function changeStatus($type, $orderId)
    {
        $order = OrderModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('id', $orderId)
            ->first();
        $type = myToArray($type);
        $reward = AgentOtherRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('order_id', $orderId)
            ->whereIn('type', $type)
            ->get();
        foreach ($reward as $item) {
            $updateInfo[] = ['id' => $item->id, 'status' => $order->has_agent_order_commision];
        }
        (new AgentOtherRewardModel())->updateBatch($updateInfo);
    }


    /**
     * 更改代理原分红的财务记录
     * @param  $memberId 要更改的会员ID
     * @param  $orderId 订单ID
     * @param  $type 订单type
     * @param  $subType 订单的Subtype
     * @param  $money 更改的金额
     */
    static function changeOriginRewardFinance($memberId, $order_id, $type, $subType, $money)
    {
        FinanceModel::query()->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->where('type', $type)
            ->where('order_id', $order_id)
            ->where('sub_type', $subType)
            ->update(['money' => $money, 'money_real' => $money]);
    }


    /**
     * 更改原分销的财务记录
     * @param  $memberId 要更改的会员ID
     * @param  $orderId 订单ID
     * @param  $type 订单type
     * @param  $subType 订单的Subtype
     * @param  $money 更改的金额
     */
    static function changeOriginDistributionCommissionFinance($memberId, $order_id, $type, $money)
    {
        FinanceModel::query()->where('site_id', getCurrentSiteId())
            ->where('member_id', $memberId)
            ->where('type', $type)
            ->where('order_id', $order_id)
            ->update(['money' => $money, 'money_real' => $money]);
    }


    /**
     * 全部退款的时候要回滚佣金到最初
     * @param  $orderId 订单ID
     */
    static function restoreAgentRewardCommissionByOrder($orderId)
    {
        // 因为整单退款完成的时候，代理佣金重新回滚了，没有计算到感恩奖，所以这里要重新把感恩奖重新计算回去，不影响原来的逻辑
        //  先把一级代理佣金拿出来
        $order = OrderModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('id', $orderId)
            ->first();
        $agentOtherReward = AgentOtherRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('order_id', $orderId)
            ->where('type', Constants::AgentOtherRewardType_Grateful)
            ->first();
        if (!$agentOtherReward) return false;
        $setting = json_decode($agentOtherReward->reward_setting, true);
        $commision = $setting['commision'];
        if ($order->agent_order_commision) {
            $agentOrderCommision = json_decode($order->agent_order_commision, true);
            //获取支付 代理分佣的金额 (未经计算的原始值) 扣减后代理分后的金额  一级代理的会员ID（即分钱的人）
            $agentCommisionInfo = static::getAgentRewardPayMoneyAndMember($agentOrderCommision, $commision);
            $money = $agentCommisionInfo['money'];// 代理分佣的金额
            $commisionItemMoney = $agentCommisionInfo['commisionItemMoney'];
            $memberId = $agentCommisionInfo['memberId'];// 一级代理的会员ID
            static::changeOriginRewardFinance($memberId, $order->id, CoreConstants::FinanceType_AgentCommission, CoreConstants::FinanceSubType_AgentCommission_Order, $commisionItemMoney);
            // 获取支付 分销商分佣的金额（已经是扣减过感恩奖的金额） 以及 一级代理的会员ID（即分钱的人）
            if ($memberId > 0 && $order->commission) {
                $distributionCommission = json_decode($order->commission, true);
                if ($distributionCommission) {
                    $disCommission = static::getAfterSaleDistributionCommissionPayMoney($distributionCommission, $memberId, $commision,$order);
                    $disCommissionMoney = $disCommission['disCommissionMoney'];
                    static::changeOriginDistributionCommissionFinance($memberId, $order->id, CoreConstants::FinanceType_Commission, $disCommissionMoney);
                }
            }
            // 更改订单代理的分佣金额
            $order->agent_order_commision = json_encode($agentOrderCommision);
            // 更改订单分销的分佣金额
            $order->commission = json_encode($distributionCommission);
            $order->save();
        }

        $agentOtherRewardQuery = AgentOtherRewardModel::query()
            ->where('site_id', getCurrentSiteId())
            ->where('order_id', $orderId)
            ->where('type', Constants::AgentOtherRewardType_Grateful)
            ->get();
        $updateInfo = [];
        foreach ($agentOtherRewardQuery as $item) {
            $rewardSetting = json_decode($item->reward_setting, true);
            $updateInfo[] = ['id' => $item->id, 'reward_member_id' => $item->reward_member_id, 'reward_money' => $rewardSetting['origin_reward_money']];
        }
        if ($updateInfo) {
            // 回滚佣金记录
            (new AgentOtherRewardModel())->updateBatch($updateInfo);
            // 回滚财务记录
            foreach ($updateInfo as $updateItem) {
                static::changeOriginRewardFinance($updateItem['reward_member_id'], $orderId, CoreConstants::FinanceType_AgentCommission, CoreConstants::FinanceSubType_AgentCommission_OtherReward, $updateItem['reward_money']);
            }
        }
    }

}
