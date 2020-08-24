<?php

namespace App\Modules\ModuleShop\Libs\Agent;

use App\Modules\ModuleShop\Libs\Agent\OtherReward\GratelFulReward;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Model\AgentOtherRewardModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\Order;
use YZ\Core\Model\BaseModel;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\AgentOtherRewardSettingModel;

/**
 * 代理其他奖励奖
 */
class AgentOtherReward
{

    /**
     * 获取分佣列表
     * @param  $type 奖金类型
     * @param  $orderId 订单ID
     * @type 其他奖的type
     */
    static function getList($params)
    {
        $showAll = $params['show_all'] ? true : false;
        $pageSize = $params['page_size'] ?: 20;
        $page = $params['page'] ?: 1;

        $query = AgentOtherRewardModel::query()
            ->from('tbl_agent_other_reward as aor')
            ->leftJoin('tbl_member as rm', 'rm.id', 'aor.reward_member_id')
            ->leftJoin('tbl_member as prm', 'prm.id', 'aor.pay_reward_member_id')
            ->leftJoin('tbl_order', 'tbl_order.id', 'aor.order_id')
            ->where('aor.site_id', getCurrentSiteId())
            ->selectRaw('aor.*,rm.name as reward_member_name,rm.headurl as reward_member_headurl,rm.nickname as reward_member_nickname,rm.mobile as reward_member_mobile,prm.name as pay_reward_member_name,prm.nickname as pay_reward_member_nickname,prm.mobile as pay_reward_member_mobile,prm.headurl as pay_reward_member_headurl,tbl_order.money,tbl_order.created_at as order_created_at');
        if (isset($params['status'])) {
            $query->where('aor.status', $params['status']);
        }
        // 下单时间开始
        if (trim($params['created_start'])) {
            $query->where('tbl_order.created_at', '>=', trim($params['created_start']));
        }
        // 下单时间结束
        if (trim($params['created_end'])) {
            $query->where('tbl_order.created_at', '<=', trim($params['created_end']));
        }

        if (isset($params['keyword'])) {
            $keyword = $params['keyword'];
            $query->where(function ($query) use ($keyword) {
                $query->orWhere('rm.nickname', 'like', '%' . trim($keyword) . '%');
                $query->orWhere('rm.name', 'like', '%' . trim($keyword) . '%');
                if (preg_match('/^\w+$/i', $keyword)) {
                    $query->orWhere('tbl_order.id', 'like', '%' . trim($keyword) . '%')->orWhere('rm.mobile', 'like', '%' . trim($keyword) . '%');
                }
            });
        }
        // 指定数据
        if ($params['ids']) {
            $ids = myToArray($params['ids']);
            if ($ids) {
                $showAll = true;
                $query->whereIn('aor.id', $ids);
            }
        }
        $query->orderByDesc('aor.id');
        $query->orderByDesc('tbl_order.created_at');

        $total = $query->count();
        if ($showAll) {
            $last_page = 1;
            $page=1;
            $pageSize = $total;
        } else {
            $query->forPage($page, $pageSize);
            $last_page = ceil($total / $pageSize);
        }
        $query->forPage($page, $pageSize);
        //输出-最后页数
        $list = $query->get();
        foreach ($list as $item) {
            $item->money = moneyCent2Yuan($item->money);
            $item->reward_money = moneyCent2Yuan($item->reward_money);
        }
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
    }

    /**
     * 计算其他奖的奖金(支付或者支付成功的时候调用)
     * 需要特别注意：这里有个坑，因为财务记录里面sub_type现在只用于感恩奖，所以如果扩展成1个站点有两个代理其他奖，那么需要扩展sub_type此字段或者用其他办法区分每一个奖
     * @param  $type 奖金类型
     * @param  $orderId 订单ID
     * @type 其他奖的type
     */
    static function calcReward(int $type, $orderId = null)
    {
        $setting = AgentOtherRewardSetting::getCurrentSiteSetting($type);
        switch (true) {
            case $type == Constants::AgentOtherRewardType_Grateful :
                GratelFulReward::calcGrateFul($orderId, $setting);
                break;
        }
    }

    /**
     * 退款是重新计算其他奖的奖金（退款是调用）
     * @param  $type 奖金类型
     * @param  $orderId 订单ID
     * @type 其他奖的type
     */
    static function calcAfterSaleReward(int $type, $orderId = null)
    {
        switch (true) {
            case $type == Constants::AgentOtherRewardType_Grateful :
                GratelFulReward::calcAfterSaleReward($orderId);
                break;
        }
    }


    static function restoreAgentRewardCommissionByOrder(int $type, $orderId = null)
    {
        switch (true) {
            case $type == Constants::AgentOtherRewardType_Grateful :
                GratelFulReward::restoreAgentRewardCommissionByOrder($orderId);
                break;
        }
    }


    /**
     * 计算感恩奖的奖金
     * @param  $orderId 订单ID
     * @type 其他奖的type
     */
    static function calcGrateFul($orderId, $setting)
    {
        $baseSetting = AgentBaseSetting::getCurrentSiteSetting();
        if ($baseSetting->level > 0 && $setting->status == 1) {
            //  先把一级代理佣金拿出来
            $order = OrderModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('id', $orderId)
                ->first();
            if ($order->agent_order_commision) {
                $agentOrderCommision = json_decode($order->agent_order_commision, true);
                $money = 0; // 要分佣的金额
                $memberId = 0; // 一级代理的会员ID
                foreach ($agentOrderCommision as &$Commisionitem) {
                    if ($Commisionitem['agent_level'] == 1) {
                        $money = $Commisionitem['money'];
                        $memberId = $Commisionitem['member_id'];
                        if ($money > 1) {
                            $Commisionitem['money'] = intval($money - ($money * ($setting->commision / 100)));
                            FinanceModel::query()->where('site_id', getCurrentSiteId())
                                ->where('member_id', $memberId)
                                ->where('type', CoreConstants::FinanceType_AgentCommission)
                                ->where('order_id', $orderId)
                                ->where('sub_type', CoreConstants::FinanceSubType_AgentCommission_Order)
                                ->update(['money' => $Commisionitem['money']]);
                        }
                    }
                };
                // 更改订单代理的分佣金额
                $order->agent_order_commision = json_encode($agentOrderCommision);
                $order->save();
                // 所有平级代理拿出来
                $allSameAgents = AgentHelper::getSameLevelMember($memberId);
                if ($allSameAgents && $setting->people_num > 0 && $money > 1) {
                    $sameAgents = array_slice($allSameAgents, 0, $setting->people_num);
                    // 感恩奖分的总额
                    $rewardTotalMoney = $money * ($setting->commision / 100);
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
                        if ($money > 1) {
                            // 添加额外奖的记录
                            $agentOtherReward = new AgentOtherRewardModel();
                            $money *= ($setting->commision / 100);
                            $agentOtherReward->site_id = getCurrentSiteId();
                            $agentOtherReward->type = Constants::AgentOtherRewardType_Grateful;
                            $agentOtherReward->reward_member_id = $item['id'];
                            $agentOtherReward->pay_reward_member_id = $memberId;
                            $agentOtherReward->order_id = $orderId;
                            $agentOtherReward->status = $order->has_agent_order_commision;
                            $agentOtherReward->reward_money = intval($rewardMoney);
                            $agentOtherReward->commision = $setting->commision;
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
                        }

                    }
                }
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
        if($updateInfo){
            (new AgentOtherRewardModel())->updateBatch($updateInfo);
        }
    }


}
