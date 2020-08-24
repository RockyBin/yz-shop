<?php

namespace App\Modules\ModuleShop\Libs\Distribution;

use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\FinanceModel;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;

/**
 * 此类用来计算分销金额
 */
class Distribution
{
    private $_parents = null; //分销推荐关系的链条
    private $_agentBaseSetting = null;

    /**
     * 根据购买者会会员ID计算链条内相应人员的分销所得金额
     * @param $memberId 购买者的会员ID
     * @param $money 产品的最终售价
     * @param $costMoney 产品的成本价
     * @param $buyNum 订购的数量
     * @param DistributionConfig $config 分销配置
     * @return array
     */
    public function calDistributionMoney($memberId, $money, $costMoney, $buyNum, DistributionConfig $config): array
    {
        if (!$this->_agentBaseSetting) {
            $this->_agentBaseSetting = AgentBaseSetting::getCurrentSiteSetting();
        }
        //根据 $memberId 查找相应上家，如果开启分销内购，购买者自己作为一级，否则上家作为一级，如此类推
        //查找当前会员的相关信息
        $members = $this->findParentMembers($memberId, $config);
        if (!is_array($members)) return [];
        $totalMoney = $config->type ? $money : $money - $costMoney; //总共有多少钱可以分
        if ($totalMoney < 0) $totalMoney = 0;
        $assignedMoney = 0;
        $memberMoneys = [];
        $chain = [$memberId];

        $floorLevel = 0; //分销层级
        foreach ($members as $key => $mem) {
            //如果关闭了代理可以同时获得分销佣金的选项，将当前会员的佣金设置为0，达到不给他分销佣金的目的
            if ($this->_agentBaseSetting->get_distribution_commision < 1 && intval($mem['agent_level'])) {
                $mem['commission'] = 0;
            }
            //2019-07-24需求改为非分销商时不退出分佣过程，但非分销商的佣金为0
            $chainTmp = array_reverse(array_unique($chain)); //记录某笔佣金的贡献者链条，方便后面做统计分析
            if (!$mem['is_distributor']) $moneyTemp = 0;
            else {
                if ($mem['amountType']) { //分固定金额时
                    $moneyTemp = $mem['commission'] * $buyNum;
                } else { //按比例分时
                    $moneyTemp = intval($totalMoney * ($mem['commission'] / 100));
                    if ($assignedMoney + $moneyTemp >= $totalMoney) {
                        $moneyTemp = $totalMoney - $assignedMoney;
                        if ($moneyTemp > 0) {
                            $assignedMoney += $moneyTemp;
                        }
                        if ($moneyTemp < 0) {
                            $moneyTemp = 0;
                        }
                    } else {
                        $assignedMoney += $moneyTemp;
                    }
                }
            }
            $memberMoneys[] = ['member_id' => $mem['member_id'], 'money' => $moneyTemp, 'unit_money' => intval($moneyTemp / $buyNum), 'chain' => $chainTmp, 'floor_level' => ++$floorLevel];
            $chain[] = $mem['member_id'];
        }

        return $memberMoneys;
    }

    /**
     * 判断订单是否为分销订单，只判断相关人员是否有分销资格，不管最终是否有分到钱
     * @param $memberId 购买者的会员ID
     * @param $productList 订单产品列表
     * @return bool
     */
    public function isDistributionOrder($memberId, $productList)
    {
        $config = DistributionConfig::getGlobalDistributionConfig();
        if ($config->maxLevel < 1) return false; //如果整体关闭了分销，认为都不是分销订单
        /*
        if ($productList) {
            //标识此订单是否有产品是开启了分销规则
            $productIsCommissionflag = false;
            foreach ($productList as $item) {
                //如果有产品不等于-1，说明有产品开启了分销规则
                if ($item->fenxiaoRule != -1) {
                    $productIsCommissionflag = true;
                    break;
                }
            }
            //如果此标识为false，证明此订单不是分销订单
            if (!$productIsCommissionflag) return false;
        }
        */
        $member = new Member($memberId);
        $memberModel = $member->getModel();
        // 如果自身又是分销商，证明此单一定是分销订单
        if($memberModel->is_distributor == 1) return true;
        $members = $this->findCommissionParent($memberId);
        if (is_array($members)) {
            // 分佣断层也会有分佣去分  所以 检测当前关系链条中有分销商 当前订单即为分销订单
            foreach ($members as $m) {
                if ($m['is_distributor'] == 1) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 找出指定会员的相应可能可以分钱的相应上级
     * @param $memberId
     * @return array 返回此会员的上 N 级
     * 返回的数组格式
     * [
     *  [
     *      'member_id' => 会员ID,
     *      'internal' => 是否开启内购,
     *      'is_distributor' => 就否分销商,
     *      'level' => 分销等级,
     *      'commission' => 等级拥金设置,
     *  ]
     * ]
     */
    public function findParentMembers($memberId, DistributionConfig $config)
    {
        if (!is_array($this->_parents)) {
            $member = new \YZ\Core\Member\Member($memberId);
            $mModel = $member->getModel();
            $this->_parents = [];
            if ($mModel) {
                if($mModel->is_distributor > 0) $parentId = $config->internalPurchase ? $memberId : $mModel->invite1; //只有当前购买商品的会员是分销员时，才启用内购的条件
                else $parentId = $mModel->invite1;
                for ($i = 1; $i <= $config->maxLevel && $parentId; $i++) {
                    $mparent = new \YZ\Core\Member\Member($parentId);
                    $mParentModel = $mparent->getModel();
                    if ($mParentModel) {
                        $item = [];
                        $item['member_id'] = $mParentModel->id;
                        $item['internal'] = $config->internalPurchase;
                        $item['agent_level'] = $mParentModel->agent_level;
                        $distributor = new Distributor($mParentModel->id);
                        if ($distributor->getModel()) {
                            $item['is_distributor'] = $mParentModel->is_distributor;
                            /*
                            $levelInfo = $distributor->getModel()->levelInfo;
                            if(!$levelInfo) $levelInfo = DistributionLevel::getDefaultLevel();
                            if($levelInfo) {
                                $item['level'] = $levelInfo->id;
                                $item['level_name'] = $levelInfo->name;
                                //$commission = json_decode($levelInfo->commission,true);
                                $commission = $config->getLevelCommission($levelInfo->id);
                                $item['commission'] = $commission[strval($i)];
                            }*/
                            $commission = $config->getLevelCommission($distributor->getModel()->level);
                            if (!$commission) $commission = $config->getLevelCommission(0);
                            $item['level'] = $distributor->getModel()->level;
                            $item['commission'] = $commission['commission'][strval($i)];
                            $item['amountType'] = $commission['amountType'];
                        }
                        $this->_parents[] = $item;
                    }
                    $parentId = $mParentModel->invite1;
                }
            }
        }
        return $this->_parents;
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
        $activeCount = FinanceModel::where(['type' => \YZ\Core\Constants::FinanceType_Commission, 'status' => \YZ\Core\Constants::FinanceStatus_Active, 'order_id' => $order->id])->where('money', '>', 0)->count('id');
        if ($activeCount) return;
        if (is_numeric($orderItemIdOrOrderItemModel)) $model = $order->items->find($orderItemIdOrOrderItemModel);
        else $model = $orderItemIdOrOrderItemModel;
        if ($model && $model->after_sale_over_num > 0) {
            $oldCommision = json_decode($model->commission, true);
            if (is_array($oldCommision) && count($oldCommision)) {
                $newCommision = [];
                $tradeNos = ['ORDER_COMMISSION_REFUND_NO'];
                foreach ($oldCommision as $key => $val) {
                    if ($val['member_id']) {
                        $newItem = $val; //应该减掉的钱
                        $newItem['money'] = ($val['unit_money'] * $model->after_sale_over_num) * -1;
                        $newItem['about'] = "订单商品退款扣减分销佣金";
                        $newItem['tradeno'] = "ORDER_COMMISSION_REFUND_" . $val['member_id'] . "_" . $model->id;
                        //兼容旧数据，旧的数据里没有unit_money这个字段，这里自动生成一下
                        if (!key_exists('unit_money', $val)) {
                            $val['unit_money'] = intval($val['money'] / $model->num);
                            $oldCommision[$key]['unit_money'] = $val['unit_money'];
                        }
                        $tradeNos[] = $newItem['tradeno'];
                        $newCommisionMoney = $val['unit_money'] * ($model->num - $model->after_sale_over_num);
                        if ($oldCommision[$key]['money'] != $newCommisionMoney) {
                            $oldCommision[$key]['money'] = $newCommisionMoney;
                            $newCommision[] = $newItem;
                        }
                    }
                }
                $model->commission = json_encode($oldCommision);
                $model->save();
                //FinanceModel::where(['status' => \YZ\Core\Constants::FinanceStatus_Freeze])->whereIn('tradeno',$tradeNos)->delete();
                //改为不添加负数的记录了，直接更改原来的佣金记录的金额
                foreach ($newCommision as $item) {
                    $query = FinanceModel::query()
                        ->where(['status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'type' => \YZ\Core\Constants::FinanceType_Commission, 'order_id' => $order->id])
                        ->where('money', '>', 0)
                        ->where('member_id', $item['member_id'])
                        ->update([
                            'money' => DB::raw('money + ' . $item['money']),
                            'money_real' => DB::raw('money_real + ' . $item['money']),
                        ]);
                }
            }
            //更新订单本来的分佣字段
            $query = FinanceModel::query()->where(['status' => \YZ\Core\Constants::FinanceStatus_Freeze, 'type' => \YZ\Core\Constants::FinanceType_Commission, 'order_id' => $order->id]);
            $query = $query->where('money', '>', 0)->select('member_id', 'order_id', 'money');
            $list = $query->get();
            if ($list) {
                $oldCommision = json_decode($order->commission, true);
                if (is_array($oldCommision) && count($oldCommision)) {
                    foreach ($oldCommision as $key => $val) {
                        $foundObj = $list->where('member_id', $val['member_id'])->first();
                        $oldCommision[$key]['money'] = $foundObj->money ? $foundObj->money : 0;
                    }
                }
                $order->commission = json_encode($oldCommision);
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
        $itemCommisions = [];
        foreach ($oderModel->items as $item) {
            $itemCommisions = array_merge($itemCommisions, static::restoreCommissionByItem($oderModel, $item));
        }
        // 汇总每个商品的佣金
        $memberMoneys = [];
        foreach ($itemCommisions as $item) {
            if (!$item['member_id']) continue;
            if (!$memberMoneys[$item['member_id']]) $memberMoneys[$item['member_id']] = $item['money'];
            else $memberMoneys[$item['member_id']] += $item['money'];
        }
        // 重新写回总佣金值
        $oldCommision = json_decode($oderModel->commission, true);
        if (!is_array($oldCommision)) return [];
        if (!count($oldCommision)) return [];
        foreach ($oldCommision as $key => $val) {
            if (!$val['member_id']) continue;
            $oldCommision[$key]['money'] = $memberMoneys[$val['member_id']];
            FinanceModel::whereIn('status', [\YZ\Core\Constants::FinanceStatus_Freeze, \YZ\Core\Constants::FinanceStatus_Invalid])
                ->where(['order_id' => $oderModel->id, 'type' => \YZ\Core\Constants::FinanceType_Commission, 'member_id' => $val['member_id']])
                ->update(['money' => $memberMoneys[$val['member_id']], 'money_real' => $memberMoneys[$val['member_id']]]);
        }
        $oderModel->commission = json_encode($oldCommision);
        $oderModel->save();
        return $oldCommision;
    }

    /**
     * 将分销佣金的金额写回去，目前是用在当订单完全售后的情况下，将佣金金额更新回去，好让前后台有相应的展示
     * @return mixed
     */
    public static function restoreCommissionByItem($orderIdOrModel, $orderItemIdOrOrderItemModel)
    {
        if (is_numeric($orderIdOrModel)) $oderModel = OrderModel::find($orderIdOrModel);
        else $oderModel = $orderIdOrModel;
        if (is_numeric($orderItemIdOrOrderItemModel)) $oderItemModel = OrderItemModel::find($orderItemIdOrOrderItemModel);
        else $oderItemModel = $orderItemIdOrOrderItemModel;
        if ($oderItemModel && $oderItemModel->after_sale_over_num >= $oderItemModel->num) {
            $oldCommision = json_decode($oderItemModel->commission, true);
            if (!is_array($oldCommision)) return [];
            if (!count($oldCommision)) return [];
            foreach ($oldCommision as $key => $val) {
                if ($val['member_id']) {
                    if (!key_exists('unit_money', $val)) {
                        $val['unit_money'] = intval($val['money'] / $oderItemModel->num);
                        $oldCommision[$key]['unit_money'] = $val['unit_money'];
                    }
                    $money = $val['unit_money'] * $oderItemModel->num;
                    $oldCommision[$key]['money'] = $money;
                }
            }
            $oderItemModel->commission = json_encode($oldCommision);
            $oderItemModel->save();
            return $oldCommision;
        }
    }

    /**
     * 寻找该会员链条中的分销商
     * @return array
     */
    public static function findCommissionParent($memberId)
    {
        $member = new Member($memberId);
        $distributionConfig = new DistributionConfig();
        $memberModel = $member->getModel();
        $commissionParent = [];
        for ($i = 1; $i <= $distributionConfig->maxLevel; $i++) {
            if ($memberModel->{'invite' . $i} != 0) $parentMembers[] = $memberModel->{'invite' . $i};
        }
        if ($parentMembers) {
            $commissionParent = MemberModel::query()
                ->whereIn('id', $parentMembers)
                ->where('is_distributor', 1)
                ->select('is_distributor', 'id')
                ->get()
                ->toArray();
        }
        return $commissionParent;
    }
}