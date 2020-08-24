<?php

namespace App\Modules\ModuleShop\Libs\AreaAgent;

use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentBaseSettingModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use YZ\Core\Constants;
use YZ\Core\Finance\Finance;
use YZ\Core\Model\DistrictModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use YZ\Core\Model\FinanceModel;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentConstants;

/**
 * 此类用来计算分销金额
 */
class AreaAgentCommission
{
    /**
     * 根据订单ID，计算相应订单内商品的区域佣金
     * @param $memberId 订单购买者的会员ID
     * @param $areaId 订单收货人的区域ID
     * @param $money 产品的最终售价
     * @param $costMoney 产品的成本价
     * @param $buyNum 订购的数量
     * @param DistributionConfig $config 分销配置
     * @return array
     */
    public static function calCommissionMoney($memberId, $areaId, $money, $costMoney, $buyNum, AreaAgentCommissionConfig $config): array
    {
        $setting = AreaAgentBaseSettingModel::query()->where('site_id', getCurrentSiteId())->first();
        $members = static::findAreaAgentMembers($areaId, $config); //注意这里拿到代理链条必须比小到大排序
        if (!is_array($members)) return [];
        $totalMoney = $config->type ? $money : $money - $costMoney; //总共有多少钱可以分
        if ($totalMoney < 0) $totalMoney = 0;
        $assignedMoney = 0;
        $memberMoneys = [];
        $chain = [$memberId];
        foreach ($members as $key => $mem) {
            $chain[] = $mem['member_id'];
            //如果没有开启自购分佣
            if (!$setting->internal_purchase && $mem['member_id'] == $memberId) {
                $mem['commission'] = 0;
            }
            if ($config->amountType) { //分固定金额时
                $moneyTemp = $mem['commission'] * $buyNum;
            } else { //按比例分时
                $moneyTemp = intval($totalMoney * ($mem['commission'] / 100));
                //当采用固定比例时，要注意控制总比例不能大于100%
                if (intval($config->commissionMode) && $assignedMoney + $moneyTemp >= $totalMoney) {
                    $moneyTemp = $totalMoney - $assignedMoney;
                }
            }
            if (intval($config->commissionMode) === 0) {
                $moneyTemp -= $assignedMoney;
            }
            if ($moneyTemp < 0) $moneyTemp = 0;
            $assignedMoney += $moneyTemp;
            $memberMoneys[] = ['member_id' => $mem['member_id'], 'chain' => array_values(array_unique($chain)), 'money' => $moneyTemp, 'unit_money' => intval($moneyTemp / $buyNum), 'area_type' => $mem['area_type'], 'area_agent_level' => $mem['area_agent_level']];
        }
        return $memberMoneys;
    }

    /**
     * 根据最小范围的区域ID，找出此区域以及上级区域的区域代理会员信息
     * @param $miniAreaId
     * @return array [
     *  ['member_id' => 会员ID,'area_type' => 区域类型(province/city/district之一),'commission' => 此代理的佣金比例]
     * ]
     */
    public static function findAreaAgentMembers($miniAreaId, AreaAgentCommissionConfig $config)
    {
        //1：先查出当前最小区域的所有上级区域的ID
        $areaList = [$miniAreaId];
        $area = DistrictModel::query()->where('id', $miniAreaId)->first();
        while ($area && $area->parent_id) {
            $area = DistrictModel::query()->where('id', $area->parent_id)->first();
            if ($area) $areaList[] = $area->id;
        }
        //2: 查区域代理表，找出所有上面这些区域的代理，注意这里拿到代理链条必须比小到大排序，否则会影响分佣结果
        $agentList = AreaAgentModel::query()->where('site_id', getCurrentSiteId())->where(function ($query) use ($areaList) {
            $query->where('district', $areaList[0] ? $areaList[0] : -1)->where('area_type', AreaAgentConstants::AreaAgentLevel_District)
                ->orWhere(function ($query2) use ($areaList) {
                    $query2->where('city', $areaList[1] ? $areaList[1] : -1)->where('area_type', AreaAgentConstants::AreaAgentLevel_City);
                })->orWhere(function ($query3) use ($areaList) {
                    $query3->where('prov', $areaList[2] ? $areaList[2] : -1)->where('area_type', AreaAgentConstants::AreaAgentLevel_Province);
                });
        })->where('status', 1)->orderBy('area_type', 'asc')->get();

        $agents = [];
        foreach ($agentList as $item) {
            $arr = [];
            $arr['member_id'] = $item->member_id;
            $arr['area_agent_level'] = $item->area_agent_level;
            if ($item->area_type == 10) $arr['area_type'] = 'province';
            if ($item->area_type == 9) $arr['area_type'] = 'city';
            if ($item->area_type == 8) $arr['area_type'] = 'district';
            $commission = $config->getLevelCommission($item->area_agent_level);
            if (!$commission) $commission = $config->getLevelCommission(0);
            $arr['commission'] = $commission['commission'][$arr['area_type']];
            $agents[] = $arr;
        }
        return $agents;
    }

    /**
     * 添加区域代理订单佣金记录
     * @param $siteId 网站ID
     * @param $orderId 订单ID
     * @param array $commission 分佣表
     * @param $clearOld 清除旧的分佣记录，一般在对订单完全执行重新分佣时才需要设置为1
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function addOrderCommission($siteId, $orderId, array $commission, $clearOld = 0)
    {
        // 先删除此订单之前的财务记录
        FinanceModel::where(['type' => Constants::FinanceType_AreaAgentCommission, 'order_id' => $orderId])->delete();
        $setting = AreaAgentBaseSettingModel::query()->where('site_id', getCurrentSiteId())->first();
        // 查询佣金结算时机
        $commissionGrantTime = intval($setting->commision_grant_time);
        // 查询此订单是否已经有生效的正数佣金，如果有，表示此订单之前是付款后就结算佣金的，这时则不添加负数记录，以达到已发放的佣金不能再扣减的目的
        $activeCount = FinanceModel::where(['type' => Constants::FinanceType_AreaAgentCommission, 'status' => Constants::FinanceStatus_Active, 'sub_type' => Constants::FinanceSubType_AreaAgentCommission_Order, 'order_id' => $orderId])->where('money', '>', 0)->count('id');
        // 先将此订单之前的分佣记录删除
        if ($clearOld) FinanceModel::where(['type' => Constants::FinanceType_AreaAgentCommission, 'sub_type' => Constants::FinanceSubType_AreaAgentCommission_Order, 'order_id' => $orderId])->delete();
        // 记录新的记录
        $noticeFinanceIDs = [];
        $financeObj = new Finance();
        $batchNumber = date('YmdHis');
        foreach ($commission as $item) {
            if ($activeCount && $item['money'] < 0) continue;
            $finInfo = [
                'site_id' => $siteId,
                'member_id' => $item['member_id'],
                'type' => Constants::FinanceType_AreaAgentCommission,
                'sub_type' => Constants::FinanceSubType_AreaAgentCommission_Order,
                'pay_type' => Constants::PayType_Commission,
                'in_type' => Constants::FinanceInType_Commission,
                'tradeno' => $item['tradeno'] ? $item['tradeno'] : 'AREA_AGENT_ORDER_COMMISSION_' . $batchNumber . '_' . genUuid(8),
                'order_id' => $orderId,
                'terminal_type' => Constants::TerminalType_Unknown,
                'money' => $item['money'],
                'created_at' => date('Y-m-d H:i:s'),
                'about' => $item['about'] ? $item['about'] : '区域代理订单返佣，订单号：' . $orderId,
                'status' => $commissionGrantTime === 1 ? Constants::FinanceStatus_Freeze : Constants::FinanceStatus_Active
            ];
            if ($commissionGrantTime === 0) $finInfo['active_at'] = date('Y-m-d H:i:s');
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

        foreach ($noticeFinanceIDs as $noticeFinanceID) {
            $financeModel = FinanceModel::query()->where('site_id', $siteId)->where('id', $noticeFinanceID)->first();
            MessageNoticeHelper::sendMessageAreaAgentCommission($financeModel);
        }

    }

    /**
     * 扣减单种商品代理正常佣金，一般在发生售后时使用
     * @return mixed
     */
    public static function deductCommissionByItem($orderIdOrModel, $orderItemIdOrOrderItemModel)
    {
        // 查询此订单是否已经有生效的正数佣金，如果有，表示此订单之前是付款后就结算佣金的，这时则不添加负数记录，以达到已发放的佣金不能再扣减的目的
        if (is_numeric($orderIdOrModel)) $order = OrderModel::find($orderIdOrModel);
        else $order = $orderIdOrModel;
        $activeCount = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AreaAgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Active, 'order_id' => $order->id])->where('money', '>', 0)->count('id');
        if ($activeCount) return;
        if (is_numeric($orderItemIdOrOrderItemModel)) $model = $order->items->find($orderItemIdOrOrderItemModel);
        else $model = $orderItemIdOrOrderItemModel;
        if ($model && $model->after_sale_over_num > 0) {
            $oldCommission = json_decode($model->area_agent_commission, true);
            if (is_array($oldCommission) && count($oldCommission)) {
                $newCommission = [];
                $tradeNos = ['AREA_AGENT_ORDER_COMMISSION_REFUND_NO'];
                foreach ($oldCommission as $key => $val) {
                    if ($val['member_id']) {
                        $newItem = $val; //应该减掉的钱
                        $newItem['money'] = ($val['unit_money'] * $model->after_sale_over_num) * -1;
                        $newItem['about'] = "订单商品退款扣减区域代理返佣";
                        $newItem['tradeno'] = "AREA_AGENT_ORDER_COMMISSION_REFUND_" . $val['member_id'] . "_" . $model->id;
                        $tradeNos[] = $newItem['tradeno'];
                        $newCommissionMoney = $val['unit_money'] * ($model->num - $model->after_sale_over_num);
                        if ($oldCommission[$key]['money'] != $newCommissionMoney) {
                            $oldCommission[$key]['money'] = $newCommissionMoney;
                            $newCommission[] = $newItem;
                        }
                    }
                }

                $model->area_agent_commission = json_encode($oldCommission);
                $model->save();
                //改为不添加负数的记录了，直接更改原来的佣金记录的金额
                foreach ($newCommission as $item) {
                    $query = FinanceModel::query()
                        ->where(['status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'type' => \YZ\Core\Constants::FinanceType_AreaAgentCommission, 'order_id' => $order->id])
                        ->where('money', '>', 0)
                        ->where('member_id', $item['member_id'])
                        ->update([
                            'money' => DB::raw('money + ' . $item['money']),
                            'money_real' => DB::raw('money_real + ' . $item['money']),
                        ]);
                }
            }
            //更新订单本来的分佣字段
            $query = FinanceModel::query()->where(['status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'type' => \YZ\Core\Constants::FinanceType_AreaAgentCommission, 'order_id' => $order->id]);
            $query = $query->where('money', '>', 0)->select('member_id', 'order_id', 'money');
            $list = $query->get();
            if ($list) {
                $oldCommission = json_decode($order->area_agent_commission, true);
                if (is_array($oldCommission) && count($oldCommission)) {
                    foreach ($oldCommission as $key => $val) {
                        $foundObj = $list->where('member_id', $val['member_id'])->first();
                        $oldCommission[$key]['money'] = $foundObj->money ? $foundObj->money : 0;
                    }
                }
                $order->area_agent_commission = json_encode($oldCommission);
                $order->save();
            }
        }
    }

    /**
     * 将分销佣金的金额写回去，目前是用在当订单完全售后的情况下，将佣金金额更新回去，好让前后台有相应的展示
     * @return mixed
     */
    public static function restoreCommissionByOrder($orderIdOrModel)
    {
        if (is_numeric($orderIdOrModel)) $oderModel = OrderModel::find($orderIdOrModel);
        else $oderModel = $orderIdOrModel;
        // 重新计算每个商品的佣金
        $itemCommissions = [];
        foreach ($oderModel->items as $item) {
            $itemCommissions = array_merge($itemCommissions, static::restoreCommissionByItem($oderModel, $item));
        }
        // 汇总每个商品的佣金
        $memberMoneys = [];
        foreach ($itemCommissions as $item) {
            if (!$item['member_id']) continue;
            if (!$memberMoneys[$item['member_id']]) $memberMoneys[$item['member_id']] = $item['money'];
            else $memberMoneys[$item['member_id']] += $item['money'];
        }
        // 重新写回总佣金值
        $oldCommission = json_decode($oderModel->area_agent_commission, true);
        if (!is_array($oldCommission)) return [];
        if (!count($oldCommission)) return [];
        foreach ($oldCommission as $key => $val) {
            if (!$val['member_id']) continue;
            $oldCommission[$key]['money'] = $memberMoneys[$val['member_id']];
            FinanceModel::whereIn('status', [\YZ\Core\Constants::FinanceStatus_Freeze, \YZ\Core\Constants::FinanceStatus_Invalid])
                ->where(['order_id' => $oderModel->id, 'type' => \YZ\Core\Constants::FinanceType_AreaAgentCommission, 'member_id' => $val['member_id']])
                ->update(['money' => $memberMoneys[$val['member_id']], 'money_real' => $memberMoneys[$val['member_id']]]);
        }
        $oderModel->area_agent_commission = json_encode($oldCommission);
        $oderModel->save();
        return $oldCommission;
    }

    /**
     * 将佣金的金额写回去，目前是用在当订单完全售后的情况下，将佣金金额更新回去，好让前后台有相应的展示
     * @return mixed
     */
    public static function restoreCommissionByItem($orderIdOrModel, $orderItemIdOrOrderItemModel)
    {
        if (is_numeric($orderItemIdOrOrderItemModel)) $oderItemModel = OrderItemModel::find($orderItemIdOrOrderItemModel);
        else $oderItemModel = $orderItemIdOrOrderItemModel;
        if ($oderItemModel && $oderItemModel->after_sale_over_num >= $oderItemModel->num) {
            $oldCommission = json_decode($oderItemModel->area_agent_commission, true);
            if (!is_array($oldCommission)) return [];
            if (!count($oldCommission)) return [];
            foreach ($oldCommission as $key => $val) {
                if ($val['member_id']) {
                    if (!key_exists('unit_money', $val)) {
                        $val['unit_money'] = intval($val['money'] / $oderItemModel->num);
                        $oldCommission[$key]['unit_money'] = $val['unit_money'];
                    }
                    $money = $val['unit_money'] * $oderItemModel->num;
                    $oldCommission[$key]['money'] = $money;
                }
            }
            $oderItemModel->area_agent_commission = json_encode($oldCommission);
            $oderItemModel->save();
            return $oldCommission;
        }
    }

    /**
     * 激活订单的代理佣金，一般在订单完成时使用
     *
     * @param [type] $orderId 订单号
     * @return array 关于此订单的区域代理佣金汇总记录(已减掉因退款等原因扣除的佣金)
     */
    public static function activeCommissionStatusByOrder($orderId)
    {
        //正常代理佣金
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AreaAgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'sub_type' => Constants::FinanceSubType_AreaAgentCommission_Order, 'order_id' => $orderId]);
        $query->update(['status' => \YZ\Core\Constants::FinanceStatus_Active, 'active_at' => date('Y-m-d H:i:s')]);
        //更新订单关于佣金状态的记录
        OrderModel::query()->where('id', $orderId)->where('area_agent_commission_status', '=', '1')->update(['area_agent_commission_status' => \App\Modules\ModuleShop\Libs\Constants::OrderAreaAgentOrderCommissionStatus_Yes]);
        //返回佣金记录
        $financeList = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AreaAgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Active, 'sub_type' => Constants::FinanceSubType_AreaAgentCommission_Order, 'order_id' => $orderId])->get();
        return $financeList;
    }

    /**
     * 将订单的区域代理佣金变为失效，一般在订单全部退款时使用
     * 如果佣金发放时间为付款后，那佣金的之前的状态就不是 FinanceStatus_Freeze 状态，那此过程实际上是没有更新任何数据
     * @param [type] $orderId
     * @return boolean
     */
    public static function cancelCommissionByOrder($orderId)
    {
        //正常代理佣金
        $query = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AreaAgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'sub_type' => Constants::FinanceSubType_AreaAgentCommission_Order, 'order_id' => $orderId]);
        $query->update(['status' => \YZ\Core\Constants::FinanceStatus_Invalid, 'invalid_at' => date('Y-m-d H:i:s')]);
        //更新订单关于佣金状态的记录
        OrderModel::query()->where('id', $orderId)->where('area_agent_commission_status', '=', '1')->update(['area_agent_commission_status' => \App\Modules\ModuleShop\Libs\Constants::OrderAreaAgentOrderCommissionStatus_YesButInvalid]);
    }

    public static function deleteCommissionByOrder($orderId)
    {
        $ret = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_AreaAgentCommission, 'status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'sub_type' => Constants::FinanceSubType_AreaAgentCommission_Order, 'order_id' => $orderId])->delete();
        return $ret;
    }
}