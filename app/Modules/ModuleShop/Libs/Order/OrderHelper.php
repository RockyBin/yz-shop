<?php

namespace App\Modules\ModuleShop\Libs\Order;

use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use App\Modules\ModuleShop\Libs\Agent\AgentOtherReward;
use App\Modules\ModuleShop\Libs\Distribution\DistributionConfig;
use App\Modules\ModuleShop\Libs\Model\AgentParentsModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemDiscountModel;
use App\Modules\ModuleShop\Libs\Model\OrderMembersHistoryModel;
use App\Modules\ModuleShop\Libs\Model\OrderSnapshotModel;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use App\Modules\ModuleShop\Libs\Shop\IShopProduct;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Member;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\MemberParentsModel;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Distribution\Distribution;
use App\Modules\ModuleShop\Libs\Agent\AgentReward;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentCommission;

class OrderHelper
{
    private $siteId = -1;

    /**
     * 初始化
     * Order constructor.
     * @param int $siteId
     */
    public function __construct($siteId = 0)
    {
        if ($siteId) {
            $this->siteId = $siteId;
        } else if ($siteId == 0) {
            $this->siteId = Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 根据订单状态，获取单个会员的订单金额和数量
     * @param $status
     * @param $memberId
     * @return array|mixed
     */
    public function countSingleMember($status, $memberId)
    {
        $result = $this->count($status, $memberId);
        if ($result && $result[$memberId]) {
            return $result[$memberId];
        } else {
            return [
                'member_id' => $memberId,
                'money' => 0,
                'times' => 0,
            ];
        }
    }

    /**
     * 根据订单状态，统计相关会员的订单金额和数量
     * @param $status 订单状态
     * @param array $memberIds 会员id，不传代表统计所有用户
     * @return array
     */
    public function count($status, $memberIds = [])
    {
        $status = myToArray($status);
        $memberIds = myToArray($memberIds);
        if (count($status) == 0) return [];

        // 构造搜索条件
        $query = OrderModel::query()->whereIn('status', $status);
        if ($this->siteId) {
            $query->where('site_id', $this->siteId);
        }
        if (count($memberIds) > 0) {
            $query->whereIn('member_id', $memberIds);
        }
        $list = $query->select('member_id', DB::raw('sum(money) as money'), DB::raw('count(1) as times'))
            ->groupBy('member_id')
            ->get();

        $result = [];
        foreach ($list as $item) {
            $result[$item['member_id']] = $item;
        }
        return $result;
    }

    /**
     * 根据会员ID 或者订单状态获取订单ID
     * @param $status
     * @param $memberId
     * @param $includeSub 是否包含此会员的下级会员的订单（主要用于重新计算分销时）
     * @return array|mixed
     */
    public function getOrder($status = [], $memberIds = [], bool $includeSub = false)
    {
        $query = OrderModel::query()->where('site_id', $this->siteId);
        if ($status) {
            $status = myToArray($status);
            $query->whereIn('status', $status);
        }
        if ($memberIds) {
            $memberIds = myToArray($memberIds);
            if (!$includeSub) $query->whereIn('member_id', $memberIds);
            if ($includeSub) {
                $setting = DistributionSetting::getCurrentSiteSetting();
                $configMaxLevel = intval($setting->level);
                $rawSql = "(member_id in (" . implode(',', $memberIds) . ") or member_id in (select id from tbl_member where ";
                for ($i = 1; $i <= $configMaxLevel; $i++) {
                    $rawSql .= "tbl_member.invite$i in (" . implode(',', $memberIds) . ")";
                    if ($i < $configMaxLevel) $rawSql .= " OR ";
                }
                $rawSql .= "))";
                $query->whereRaw($rawSql);
            }
        }
        $orderId = $query->select(['id'])->get()->toArray();
        return $orderId;
    }

    /**
     * 根据订单数据计算订单的情况
     * @param $orderId
     * @return array
     */
    public function parseStatusWithOrderItem($orderId)
    {
        $hasDelivery = false; // 是否有发过货
        $hasAfterSaleIng = false; // 是否有售后中的
        $hasAfterSaleOver = false; // 是否有售后成功的
        $allDone = true; // 是否全部处理完毕
        $allDoneOrIng = true; // 是否全部处理完毕或处理中
        $allAfterSaleOver = true; // 是否全部售后成功

        // 读取订单产品数据
        $list = OrderItemModel::query()
            ->where('site_id', $this->siteId)
            ->where('order_id', $orderId)
            ->select('delivery_status', 'num', 'after_sale_num', 'after_sale_over_num')
            ->get();
        // 计算数据
        $hasDealRemain = false; // 是否有未做过处理的
        foreach ($list as $item) {
            $num = intval($item->num);
            $deliveryStatus = intval($item->delivery_status);
            $afterSaleNum = intval($item->after_sale_num);
            $afterSaleOverNum = intval($item->after_sale_over_num);
            if ($afterSaleNum > 0) {
                $hasAfterSaleIng = true;
            }
            if ($afterSaleOverNum > 0) {
                $hasAfterSaleOver = true;
            }
            if ($deliveryStatus > 0) {
                $hasDelivery = true;
            } else {
                if ($num > $afterSaleNum + $afterSaleOverNum) {
                    $hasDealRemain = true;
                }
            }
            if ($hasDealRemain) {
                $allDoneOrIng = false;
            }
            if ($deliveryStatus == 0 && $num > $afterSaleOverNum) {
                $allDone = false;
            }
            if ($afterSaleOverNum < $num) {
                $allAfterSaleOver = false;
            }
        }

        return [
            'hasDelivery' => $hasDelivery, // 是否有发过货
            'hasAfterSaleIng' => $hasAfterSaleIng, // 是否有售后中的
            'hasAfterSaleOver' => $hasAfterSaleOver, // 是否有售后成功的
            'allDone' => $allDone, // 是否全部处理完毕
            'allDoneOrIng' => $allDoneOrIng, // 是否全部处理完毕或处理中
            'allAfterSaleOver' => $allAfterSaleOver, // 是否全部售后成功
        ];
    }

    /**
     * 是否可以设置为收货（全部）
     * @param $orderId
     * @return bool
     */
    public function canSetReceipt($orderId)
    {
        if (empty($orderId)) return false;

        $order = new Order($this->siteId);
        $order->setOrder($orderId);
        $status = Constants::OrderStatus_NoPay;
        if ($order->checkExist()) {
            $status = intval($order->getModel()->status);
        } else {
            return false;
        }

        // 状态为已发货，并且全部处理完毕，才能设置为已收货状态
        if ($status == Constants::OrderStatus_OrderSend) {
            $data = $this->parseStatusWithOrderItem($orderId);
            if ($data['allDone'] && $data['hasDelivery']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $orderId
     * @return bool
     * 为发货和售后处理订单状态（只对 待发货 和 待收货 状态进行处理）
     */
    public function updateStatusForSendReceive($orderId)
    {
        if (empty($orderId)) return false;

        $order = new Order($this->siteId);
        $order->setOrder($orderId);
        $status = Constants::OrderStatus_NoPay;
        if ($order->checkExist()) {
            $status = intval($order->getModel()->status);
        } else {
            return false;
        }

        $updateData = [];
        $data = $this->parseStatusWithOrderItem($orderId);
        $hasDelivery = $data['hasDelivery']; // 是否有发过货
        $allDone = $data['allDone']; // 是否全部处理完毕
        $allDoneOrIng = $data['allDoneOrIng']; // 是否全部处理完毕或处理中
        $allAfterSaleOver = $data['allAfterSaleOver']; // 是否全部售后成功

        // 处理代发货和已发货
        if (in_array($status, [Constants::OrderStatus_OrderPay, Constants::OrderStatus_OrderSend])) {
            if ($hasDelivery) {
                // 订单发货状态
                if ($allDone) {
                    $updateData['delivery_status'] = Constants::OrderDeliveryStatus_Yes;
                    // 全部处理完毕，设定已发货时间，让自动任务好跑
                    $updateData['send_at'] = date('Y-m-d H:i:s');
                } else {
                    $updateData['delivery_status'] = Constants::OrderDeliveryStatus_Part;
                }
                // 订单状态
                if ($allDoneOrIng) {
                    $updateData['status'] = Constants::OrderStatus_OrderSend;
                } else {
                    $updateData['status'] = Constants::OrderStatus_OrderPay;
                }
            }
        }
        // 处理全部售后成功
        if ($allAfterSaleOver && in_array(intval($status), [Constants::OrderStatus_OrderPay, Constants::OrderStatus_OrderSend, Constants::OrderStatus_OrderReceive, Constants::OrderStatus_OrderSuccess])) {
            // 如果全部售后成功，直接进入交易关闭
            $updateData['status'] = Constants::OrderStatus_OrderClosed;
            $updateData['delivery_status'] = Constants::OrderDeliveryStatus_No;
            unset($updateData['send_at']);
        }
        // 更新数据
        if (count($updateData) > 0) {
            // 更新发货状态
            if (array_key_exists('delivery_status', $updateData)) {
                $order->getModel()->delivery_status = $updateData['delivery_status'];
            }
            // 更新状态
            if (array_key_exists('status', $updateData)) {
                // 交易关闭交由后续 finish 处理
                if (intval($updateData['status']) != Constants::OrderStatus_OrderClosed) {
                    $order->getModel()->status = $updateData['status'];
                }
            }
            // 更新发货时间
            if (array_key_exists('send_at', $updateData)) {
                $order->getModel()->send_at = $updateData['send_at'];
            }
            $order->getModel()->save();
            // 如果是交易关闭
            if (intval($updateData['status']) == Constants::OrderStatus_OrderClosed) {
                $shopOrder = ShopOrderFactory::createOrderByOrderId($orderId);
                $shopOrder->finish(1);
                // 将分销佣金和代理佣金的相应金额写回去...
                Distribution::restoreCommissionByOrder($orderId);
                AgentReward::restoreOrderCommissionByOrder($orderId);
                AgentReward::restoreSaleRewardCommissionByOrder($orderId);
                AgentOtherReward::restoreAgentRewardCommissionByOrder(Constants::AgentOtherRewardType_Grateful, $orderId);
				AreaAgentCommission::restoreCommissionByOrder($orderId);
            }
        }
        return true;
    }

    /**
     * 更新订单评论状态
     * @param $orderId
     * @return bool
     */
    public function updateCommentStatus($orderId)
    {
        if (empty($orderId)) return false;
        // 订单评论状态已变更，不可逆转
        $orderModel = OrderModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('id', $orderId)
            ->where('comment_status', Constants::OrderCommentStatus_CanComment)
            ->first();
        if (!$orderModel) return false;
        // 检查是否有还未评论的商品
        $orderItemCount = OrderItemModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId())
            ->where('order_id', $orderId)
            ->where('comment_status', Constants::OrderItemCommentStatus_NoComment)
            ->count();
        if ($orderItemCount == 0) {
            // 看有没有评论过的商品，有则视为全部评价完，否则视为禁止评论
            $hasCommentCount = OrderItemModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('order_id', $orderId)
                ->where('comment_status', Constants::OrderItemCommentStatus_HasComment)
                ->count();
            OrderModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $orderId)
                ->where('comment_status', Constants::OrderCommentStatus_CanComment)
                ->update([
                    'comment_status' => $hasCommentCount ? Constants::OrderCommentStatus_AllComment : Constants::OrderCommentStatus_ForbidComment,
                ]);
        } else {
            return false;
        }
    }

    /**
     * 计算剩余的时间
     * @param $time
     * @param int $day
     * @param int $hour
     * @param int $min
     * @return bool|int
     */
    public static function timeRemain($time, $day = 0, $hour = 0, $min = 0)
    {
        if ($time && ($day != 0 || $hour != 0 || $min != 0)) {
            // 创建时间加上支付时间 计算出最后支付时间
            $carbon = Carbon::parse($time);
            $carbon->addDay(intval($day))->addHour(intval($hour))->addMinute(intval($min));
            // 和现在的时间对比 计算出剩余支付时间
            $remain = $carbon->timestamp - time();
            return $remain > 0 ? $remain : 0;
        } else {
            return 0;
        }
    }

    /**
     * 记录下订单购买者的关系历史数据
     * @param $orderId
     * @param bool $reset
     */
    public static function buildOrderMembersHistory($orderId, $reset = false)
    {
        if (!$orderId) return;
        $siteId = Site::getCurrentSite()->getSiteId();
        $distributionSetting = DistributionSetting::getCurrentSiteSetting();
        // 获取付过款的订单
        $orderModel = OrderModel::query()->where('site_id', $siteId)
            ->whereIn('status', BaseShopOrder::getPaidStatusList())
            ->where('id', $orderId)
            ->select('id', 'member_id', 'status', 'commission', 'agent_order_commision', 'has_commission', 'has_agent_order_commision')
            ->first();
        if (!$orderModel) return;
        if ($reset) {
            // 清理旧数据
            OrderMembersHistoryModel::query()->where('site_id', $siteId)
                ->where('order_id', $orderId)
                ->delete();
        } else {
            // 如果有数据了，就不再处理
            $dataExist = OrderMembersHistoryModel::query()->where('site_id', $siteId)
                ->where('order_id', $orderId)
                ->count();
            if ($dataExist > 0) return;
        }
        $memberId = $orderModel->member_id;
        $insertDataList = [];
        $commissionMembers = [];
        // 获取哪些用户获得分佣
        if ($orderModel->has_commission) {
            $commissionParent = Distribution::findCommissionParent($memberId);
            if ($commissionParent) {
                foreach ($commissionParent as $v) {
                    $commissionMembers[] = $v['id'];
                }
            }
        }
        $member = new Member($memberId);
        $memberModel = $member->getModel();
        // 处理推荐关系
        $insertDataList[] = [
            'site_id' => $siteId,
            'order_id' => $orderId,
            'member_id' => $memberId,
            'level' => 0,
            'type' => Constants::OrderMembersHistoryType_Member,
            'has_commission' => $memberModel->is_distributor == 1 ? 1 : 0,
            'calc_distribution_performance' => $distributionSetting->calc_performance_valid_condition == 0 ? 0 : 1
        ];
        $memberParentsList = MemberParentsModel::query()->where('site_id', $siteId)
            ->where('member_id', $memberId)
            ->orderBy('level')
            ->groupBy('parent_id')
            ->get();
        foreach ($memberParentsList as $memberParentsItem) {
            $insertDataList[] = [
                'site_id' => $siteId,
                'order_id' => $orderId,
                'member_id' => $memberParentsItem->parent_id,
                'level' => $memberParentsItem->level,
                'type' => Constants::OrderMembersHistoryType_Member,
                'has_commission' => in_array($memberParentsItem->parent_id, $commissionMembers) ? 1 : 0,
                'calc_distribution_performance' => 1 // 这个值只对于自购来说，其他链上的人都应该计算
            ];
        }

        // 处理代理关系
        $memberModel = (new Member($memberId))->getModel();
        // 如果不是代理则不去插入自身的数据
        if ($memberModel->agent_level > 0) {
            $setting = AgentBaseSetting::getCurrentSiteSetting();
            // 如果设置了自购不统计到代理业绩 则不去添加自身的数据
            if ($setting->internal_purchase_performance) {
                $insertDataList[] = [
                    'site_id' => $siteId,
                    'order_id' => $orderId,
                    'member_id' => $memberId,
                    'level' => 0,
                    'type' => Constants::OrderMembersHistoryType_Agent,
                    'has_commission' => $orderModel->has_agent_order_commision != 0 ? 1 : 0,
                    'calc_distribution_performance' => 1 // 这个值只对于分销来说，与代理无关，但插入的时候需要一致
                ];
            }
        }
        $agentParentsList = AgentParentsModel::query()->where('site_id', $siteId)
            ->where('member_id', $memberId)
            ->orderBy('level')
            ->groupBy('parent_id')
            ->get();
        $agentOrderCommissionMembers = [];
        // 获取哪些用户获得分红
        $members = AgentHelper::getParentAgents($memberId);
        if ($orderModel->has_agent_order_commision && $members['normal']) {
            foreach ($members['normal'] as $item) {
                $agentOrderCommissionMembers[] = $item['id'];
            }
        }
        foreach ($agentParentsList as $agentParentsItem) {
            $insertDataList[] = [
                'site_id' => $siteId,
                'order_id' => $orderId,
                'member_id' => $agentParentsItem->parent_id,
                'level' => $agentParentsItem->level,
                'type' => Constants::OrderMembersHistoryType_Agent,
                'has_commission' => in_array($agentParentsItem->parent_id, $agentOrderCommissionMembers) ? 1 : 0,
                'calc_distribution_performance' => 1 // 这个值只对于分销来说，与代理无关，但插入的时候需要一致
            ];
        }
        // 批量插入
        if (count($insertDataList) > 0) {
            DB::table('tbl_order_members_history')->insert($insertDataList);
        }
    }

    /**
     * （请慎用）重置整个站点的 order_member_history （用最新的推荐关系）
     * @param bool $reset
     */
    public static function buildOrderMembersHistoryForSite($reset = true)
    {
        $orderIdList = OrderModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())
            ->whereIn('status', Constants::getPaymentOrderStatus())
            ->select('id')
            ->get()->pluck('id');
        foreach ($orderIdList as $orderId) {
            self::buildOrderMembersHistory($orderId, $reset);
        }
    }

    /**
     * 在下单时，根据供应商对订单商品进行分组
     * @param $orderProductList IShopProduct 列表
     * @return array 用供应商会员ID进行分组后的商品列表 ['供应商会员ID' => [],'供应商会员ID2' => []]
     */
    public static function splitSupplierProduct($orderProductList){
        $supplier = [];
        foreach ($orderProductList as $item) {
            if(!$supplier[$item->supplierMemberId]){
                $supplier[$item->supplierMemberId] = [];
            }
            $supplier[$item->supplierMemberId][] = $item;
        }
        return $supplier;
    }

    /**
     * 拆单时，重新算一下订单是否为虚拟商品的标志
     * @return int
     */
    private static function getVirtualFlagForSplit($itemList)
    {
        $realNum = 0; //实体商品数量
        $virtualNum = 0; //虚拟商品数量
        foreach ($itemList as $item) {
            if ($item->is_virtual) $virtualNum++;
            else $realNum++;
        }
        if ($realNum && $virtualNum) return 2;
        elseif ($virtualNum === count($itemList)) return 1;
        else return 0;
    }

    /**
     * 拆分供应商订单
     * @param string|OrderModel $orderIdOrModel 订单号或OrderModel实例
     * @return array|boolean 如果需要拆单，就返回拆分后的订单，否则返回 false
     */
    public static function splitSupplierOrder($orderIdOrModel){
        if($orderIdOrModel instanceof OrderModel) $orderModel = $orderIdOrModel;
        else $orderModel = OrderModel::find($orderIdOrModel);
        $orderItems = $orderModel->items;
        $disCountItems = OrderItemDiscountModel::query()->where('order_id',$orderModel->id)->get();
        $supplierFreight = json_decode($orderModel->supplier_freight,true);
        $supplier = [];
        foreach ($orderItems as $item) {
            if(!$supplier[$item->supplier_member_id]){
                $supplier[$item->supplier_member_id] = ['orderInfo' => [],'items' => []];
            }
            $disCountItem = $disCountItems->where('item_id',$item->id)->first();
            $supplier[$item->supplier_member_id]['orderInfo']['money'] += $item->real_price * $item->num;
            if($disCountItem) $supplier[$item->supplier_member_id]['orderInfo']['money'] -= $item->discount_price;
            $supplier[$item->supplier_member_id]['orderInfo']['point'] += $item->point_used;
            $supplier[$item->supplier_member_id]['orderInfo']['point_money'] += $item->point_money;
            $supplier[$item->supplier_member_id]['orderInfo']['coupon_money'] += $item->coupon_money;
            $supplier[$item->supplier_member_id]['orderInfo']['product_cost'] += $item->cost * $item->num;
            $supplier[$item->supplier_member_id]['orderInfo']['product_money'] += $item->price * $item->num;
            $supplier[$item->supplier_member_id]['orderInfo']['manual_discount'] += $item->manual_discount;
            $supplier[$item->supplier_member_id]['items'][] = $item;
        }
        foreach ($supplierFreight as $memberId => $value){
            $memberId = str_ireplace('supplier_','',$memberId);
            $supplier[$memberId]['orderInfo']['freight'] = $value;
        }
        //如果订单的供应商超过一个，才需要拆单
        if (count($supplier) < 2) {
            $orderModel->supplier_member_id = $orderItems->first()->supplier_member_id;
            $orderModel->save();
            return [$orderModel];
        }
        $oldOrderArr = $orderModel->toArray();
        $orderIndex = 0;
        $newOrders = [];
        $columns = Schema::getColumnListing('tbl_order');
        foreach($supplier as $memberId => $item){
            $orderIndex++;
            $newOrderModel = new OrderModel();
            //复制旧订单数据
            foreach($oldOrderArr as $key => $val){
                if(array_search($key,$columns) !== false) $newOrderModel->$key = $val;
            }
            //覆盖相应的值
            foreach($supplier[$memberId]['orderInfo'] as $key => $val){
                if(array_search($key,$columns) !== false) $newOrderModel->$key = $val;
            }
            //获取订单商品记录表ID
            $itemIds = [];
            $itemList = [];
            foreach($supplier[$memberId]['items'] as $key => $item){
                $itemIds[] = $item->id;
                $itemList[] = $item;
            }
            $virtualFlag = static::getVirtualFlagForSplit($itemList);
            //重写订单ID
            $newOrderModel->id = $orderModel->id.str_pad($orderIndex,2,"0",STR_PAD_LEFT);
            $newOrderModel->original_id = $orderModel->id;
            $newOrderModel->supplier_member_id = $memberId;
            $newOrderModel->supplier_freight = json_encode(['supplier_'.$memberId => $newOrderModel->freight]);
            $newOrderModel->virtual_flag = $virtualFlag;
            $newOrderModel->money += $newOrderModel->freight;
            $newOrderModel->save();
            //改写 OrderItemModel
            OrderItemModel::query()->whereIn('id',$itemIds)->update(['order_id' => $newOrderModel->id]);
            //改写 OrderItemDiscountModel
            OrderItemDiscountModel::query()->whereIn('item_id',$itemIds)->update(['order_id' => $newOrderModel->id]);
            $newOrders[] = $newOrderModel;
        }
        //记录旧订单信息
        $snapshot = ['order' => $orderModel->toArray(),'items' => $orderItems->toArray()];
        $snapshotModel = new OrderSnapshotModel();
        $snapshotModel->fill(['order_id' => $orderModel->id,'site_id' => $orderModel->site_id,'created_at' => Carbon::now(),'data' => json_encode($snapshot,JSON_UNESCAPED_UNICODE)]);
        $snapshotModel->save();
        //返回
        return $newOrders;
    }

    /**
     * 同步计算订单商品的实付单价和实付金额，这里只进行计算，需要在外部进行保存
     * @param OrderItemModel $itemModel
     * @param OrderItemDiscountModel $discountModel
     * @return OrderItemModel
     */
    public static function syncOrderItemPrice(OrderItemModel &$itemModel,OrderItemDiscountModel $discountModel){
        //同步订单商品的实付单价和实付金额
        $elseDiscount = 0;
        if($discountModel && $discountModel->discount_price){
            $elseDiscount = $discountModel->discount_price;
        }
        $itemModel->real_price = $itemModel->price - ($itemModel->point_money + $itemModel->coupon_money + $itemModel->manual_discount + $elseDiscount)/$itemModel->num;
        $itemModel->total_money = $itemModel->price * $itemModel->num - $itemModel->point_money - $itemModel->coupon_money - $itemModel->manual_discount - $elseDiscount;
        $itemModel->total_discount = $itemModel->point_money + $itemModel->coupon_money + $itemModel->manual_discount + $elseDiscount;
        return $itemModel;
    }
}