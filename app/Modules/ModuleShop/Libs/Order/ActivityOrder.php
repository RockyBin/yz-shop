<?php
/**
 * 活动订单的处理逻辑 主要是列表详情等的特殊数据输出
 * User: liyaohui
 * Date: 2020/4/15
 * Time: 14:52
 */

namespace App\Modules\ModuleShop\Libs\Order;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuying;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingConstants;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSkusModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemDiscountModel;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use App\Modules\ModuleShop\Libs\Shop\GroupBuyingShopOrder;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use YZ\Core\Model\MemberModel;

class ActivityOrder
{
    /**
     * 活动订单列表数据处理
     * @param \Illuminate\Database\Eloquent\Collection|static[] $list
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function activityOrderListData($list)
    {
        if ($list->count()) {
            $list = self::groupBuyingOrderListData($list);
        }
        return $list;
    }

    /**
     * 订单详情处理
     * @param \Illuminate\Database\Eloquent\Model|null|object|static $order
     * @return mixed
     * @throws \Exception
     */
    public static function activityOrderInfoData($order)
    {
        if ($order->getOrderType() == Constants::OrderType_GroupBuying) {
            return self::groupBuyingOrderInfoData($order);
        } else {
            return $order->getOrderModel();
        }
    }

    /**
     * 拼团订单列表数据处理
     * @param \Illuminate\Database\Eloquent\Collection|static[] $list
     * @return mixed
     */
    public static function groupBuyingOrderListData($list)
    {
        $activityId = $list->where('type', Constants::OrderType_GroupBuying)
            ->pluck('activity_id')->toArray();
        if ($activityId) {
            $groupBuyingList = GroupBuyingModel::query()
                ->where('site_id', getCurrentSiteId())
                ->whereIn('id', $activityId)
                ->select([
                    'id',
                    'need_people_num',
                    'current_people_num',
                    'group_product_id',
                    'status'
                ])
                ->get()->keyBy('id');
            foreach ($list as &$order) {
                $order['group_buying_info'] = $groupBuyingList[$order->activity_id];
            }
        }
        return $list;
    }

    /**
     * 拼团订单详情数据
     * @param $order
     * @return mixed
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function groupBuyingOrderInfoData($order)
    {
        $orderModel = $order->getOrderModel();
        // 获取团长优惠
        $orderDiscount = OrderItemDiscountModel::query()
            ->where('site_id', $orderModel->site_id)
            ->where('order_id', $orderModel->id)
            ->get();
        if ($orderDiscount->count()) {
            $headDiscountPrice = $orderDiscount->sum('discount_price');
            $orderModel->group_head_discount_price = moneyCent2Yuan($headDiscountPrice);
        }
        // 拼团只有一个商品
        $items = $orderModel->items;
        // 先读取缓存
        $snapShotInfo = json_decode($items[0]['snapshot'], true);
        if ($snapShotInfo['group_buying_sku']) {
            $groupBuyingSku = $snapShotInfo['group_buying_sku'];
        } else {
            $groupBuyingSku = GroupBuyingSkusModel::query()
                ->where('site_id', $orderModel->site_id)
                ->where('id', $items[0]->activity_sku_id)
                ->first();
            if (!$groupBuyingSku) {
                throw new \Exception('group buying sku no found');
            }
        }
//        $orderProduct = $order->getThisProductList()[0];
        $orderModel->group_product_id = $groupBuyingSku['group_product_id'];
        // 获取活动详情
        $setting = $order->getGroupBuyingSetting();
        $orderModel->group_setting_status = GroupBuyingConstants::GroupBuyingStatus_End;
        // 活动还存在的 去获取相关数据
        if ($setting) {
            $orderModel->group_setting_status = GroupBuyingSetting::getStatus($setting->start_time, $setting->end_time);
        } else {
            // 活动不存在的
            return $orderModel;
        }
        $orderModel->group_setting_id = $setting->id;
        // 说明是没有加入团的 未付款订单
        if (!$orderModel->activity_id) {
            return $orderModel;
        }
        $groupBuying = GroupBuyingModel::query()->where('id', $orderModel->activity_id)->first();
        // 未付款的 直接返回
        if ($orderModel->status == Constants::OrderStatus_NoPay) {
            return $orderModel;
        }
        $orderModel->group_need_people_num = $groupBuying->need_people_num;
        $orderModel->group_current_people_num = $groupBuying->current_people_num;
        // 已付款处理
        // 已付款 未成团
        if ($orderModel->status == Constants::OrderStatus_OrderPay && $orderModel->type_status == Constants::OrderType_GroupBuyingStatus_No) {
            // 返回剩余成团时间
            $payTime = strtotime($groupBuying->end_time) - time();
            if ($payTime > 0) {
                $orderModel->group_end_time = $payTime;
            } else {
                // 成团时间超时
                // 没有开启模拟成团
                if (!$setting->open_mock_group) {
                    // 前台显示取消订单
//                    $shopOrder = ShopOrderFactory::createOrderByOrderId($orderModel->id);
//                    $shopOrder->cancel();
                    $orderModel->status = Constants::OrderStatus_Cancel;
                    $orderModel->type_status = Constants::OrderType_GroupBuyingStatus_Fail;
                    $orderModel->group_end_time = 0;
                } else {
                    // 开启模拟成团
                    GroupBuying::mockGroupBuyingSuccess($groupBuying->id);
                    $orderModel->type_status = Constants::OrderType_GroupBuyingStatus_Yes;
                    $orderModel->group_end_time = 0;
                }
            }
        }
        // 获取拼团会员头像
        // 获取头像
        $orderModel->group_memberIds  = json_decode($groupBuying->member_ids, true);
        $memberIds = json_decode($groupBuying->member_ids, true);
        // 处理凑团插队头像的问题
        $memberIds = array_filter($memberIds,function ($value){
            return $value != 0;
        });
        $memberIds = array_slice($memberIds, 0, 2); // 最多返回两个头像
        // 处理凑团插队头像的问题
        $memberIds = array_filter($memberIds,function ($value){
            return $value != 0;
        });
        $headurl = MemberModel::query()
            ->where('site_id', $orderModel->site_id)
            ->whereIn('id', $memberIds)
            ->orderByRaw("find_in_set(id, '" . implode(',', $memberIds) . "')")
            ->pluck('headurl');
        $orderModel->group_member_headurl = $headurl;
        return $orderModel;
    }
}