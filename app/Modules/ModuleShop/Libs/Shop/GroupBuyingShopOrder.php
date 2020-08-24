<?php
/**
 * 拼团订单
 * User: liyaohui
 * Date: 2020/4/8
 * Time: 17:17
 */

namespace App\Modules\ModuleShop\Libs\Shop;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuying;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingConstants;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingProductsModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSettingModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSkusModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemDiscountModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use Illuminate\Support\Facades\DB;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Locker\Locker;
use YZ\Core\Model\PointModel;

class GroupBuyingShopOrder extends BaseShopOrder
{
    private $_isHead = 0; // 是否是团长
    private $_groupBuyingSettingId = 0; // 活动设置id
    private $_groupBuyingId = 0; // 当前团id
    private $_isSuccess = false; // 是否成团了

    /**
     * GroupBuyingShopOrder constructor.
     * @param int $memberId
     * @param array $params
     */
    public function __construct($memberId = 0, $params = [])
    {
        parent::__construct($memberId);
        $this->setOrderType(Constants::OrderType_GroupBuying);
        if ($params['is_head']) {
            $this->setIsHead();
        }
        $this->_groupBuyingSettingId = $params['group_buying_setting_id'];
        $this->_groupBuyingId = $params['group_buying_id'];
    }

    /**
     * @param $orderIdOrModel
     * @param bool $initProduct 是否需要初始化商品
     * @return mixed|void
     * @throws \Exception
     */
    public function initByOrderId($orderIdOrModel, $initProduct = true)
    {
        parent::initByOrderId($orderIdOrModel, $initProduct);
        // 如果不需要初始化商品数据 这里需要初始化配置信息
        if (!$initProduct) {
            $items = $this->_orderModel->items;
            if ($this->_orderModel->type_status == Constants::OrderType_GroupBuyingStatus_Yes) {
                $this->_isSuccess = true;
            }
            // 先读取缓存
            $snapShotInfo = json_decode($items[0]['snapshot'], true);
            if ($snapShotInfo['group_buying_sku']) {
                $groupBuyingSku = $snapShotInfo['group_buying_sku'];
            } else {
                $groupBuyingSku = GroupBuyingSkusModel::query()
                    ->where('site_id', $this->_orderModel->site_id)
                    ->where('id', $items[0]->activity_sku_id)
                    ->first();
            }
            if ($groupBuyingSku) {
                $this->setIsHead();
                $this->_groupBuyingSettingId = $groupBuyingSku['group_buying_setting_id'];
            } else {
                throw new \Exception('group buying sku no found');
            }
        }
    }

    /**
     * 获取当前活动配置
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getGroupBuyingSetting()
    {
        $setting = GroupBuyingSetting::getSetting($this->_groupBuyingSettingId);
        if (!$setting) {
            throw new \Exception(trans('shop-front.activity.cant_found_activity'));
        }
        return $setting;
    }

    /**
     * 检测活动状态
     * @return array|bool
     * @throws \Exception
     */
    public function checkActivitySetting()
    {
        $setting = $this->getGroupBuyingSetting();
        if ($setting->is_delete == 1) {
            return makeApiResponse(1003, trans('shop-front.activity.cant_found_activity'));
        }
        // 判断活动是否开始
        $status = GroupBuyingSetting::getStatus($setting->start_time, $setting->end_time);
        if ($status == GroupBuyingConstants::GroupBuyingStatus_Ready) {
            return makeApiResponse(1002, trans('shop-front.activity.activity_no_start'));
        } elseif ($status == GroupBuyingConstants::GroupBuyingStatus_End) {
            return makeApiResponse(1005, trans('shop-front.activity.activity_end'));
        }
        return true;
    }

    /**
     * 检测当前团状态
     * @return array|bool
     * @throws \Exception
     */
    public function checkGroupBuyingStatus()
    {
        // 判断当前团是否结束
        if ($this->_groupBuyingId) {
            $groupBuying = GroupBuyingModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('id', $this->_groupBuyingId)
                ->first();
            if (!$groupBuying) {
                return makeApiResponse(1006, trans('shop-front.activity.cant_found_activity'));
            }
            // 已经成团的 不能加入
            if ($groupBuying->status == GroupBuyingConstants::GroupBuyingTearmStatus_Yes) {
                return makeApiResponse(1007, trans('shop-front.activity.activity_end'));
            }
            $now = time();
            $endTime = strtotime($groupBuying->end_time);
            // 当前团已经结束
            if ($endTime < $now) {
                return makeApiResponse(1008, trans('shop-front.activity.activity_end'));
            }
            $setting = $this->getGroupBuyingSetting();
            if ($setting->type == GroupBuyingConstants::GroupBuyingType_OldWithNew) {
                // 老会员不能参加
                if (GroupBuying::checkOldMember($this->_memberId)) {
                    return makeApiResponse(1009, trans('shop-front.activity.unqualified_group_buying'));
                }
            }
        }
        return true;
    }

    /**
     * 检测是否已经在当前团
     * @return bool
     */
    public function checkInCurrentGroupBuying()
    {
        if ($this->_groupBuyingId) {
            $order = OrderModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('member_id', $this->_memberId)
                ->where('activity_id', $this->_groupBuyingId)
                ->where('type_status', Constants::OrderType_GroupBuyingStatus_No)
                ->first();
            return !!$order;
        }
        return false;
    }

    /**
     * 设置优惠券 要判断是否可以使用优惠券
     * @param  int $couponItemId 优惠券的ID
     * @return mixed|void
     * @throws \Exception
     */
    public function setCouponID($couponItemId)
    {
        $setting = $this->getGroupBuyingSetting();
        if ($setting->open_coupon) {
            parent::setCouponID($couponItemId);
        }
    }

    /**
     * 计算积分 要判断是否是否可以使用积分
     * @param bool $use
     * @param bool $getCanUse
     * @return mixed
     * @throws \Exception
     */
    public function calPoint($use = true, $getCanUse = false)
    {
        $setting = $this->getGroupBuyingSetting();
        if ($setting->open_point) {
            return parent::calPoint($use, $getCanUse);
        } else {
            return parent::calPoint(false, false);
        }
    }

    /**
     * 是否是团长的订单
     * @return bool
     */
    public function isHead()
    {
        return $this->_isHead;
    }

    /**
     * 设置当前是不是团长
     * @param int $isHead
     */
    public function setIsHead($isHead = 1)
    {
        $this->_isHead = $isHead;
    }

    /**
     * 计算订单的产品金额(不含优惠部分)
     * @return mixed
     */
    public function calProductMoney()
    {
        $this->productMoney = 0;
        foreach ($this->_productList as $item) {
            $this->productMoney += $item->calPrice();
        }
        return $this->productMoney;
    }

    /**
     * 获取拼团订单的金额 团长价 只是获取
     * @return int
     */
    public function calProductGroupMoney()
    {
        $productMoney = 0;
        foreach ($this->_productList as $item) {
            $productMoney += $item->calGroupBuyingPrice();
        }
        return $productMoney;
    }

    /**
     * 计算当前的团长优惠金额
     * @return int|void
     * @throws \Exception
     */
    public function calOtherDiscount()
    {
        $setting = $this->getGroupBuyingSetting();
        if ($this->isHead() && $setting->open_head_discount) {
            $headDiscount = 0;
            foreach ($this->_productList as $item) {
                $headDiscount += $item->calHeadDiscount();
            }
            $this->otherDiscount = $headDiscount;
        }
        return $this->otherDiscount;
    }


    /**
     * 支付失败时的操作
     * @param \Exception $e
     */
    public function payFail($e)
    {
        try {
            // 只有这几种状态才去关闭订单
            if (in_array($e->getCode(), [1002, 1005, 1006, 1008])) {
                $this->cancel();
            }
        } catch (\Exception $e) {

        }
    }

    /**
     * 检测拼团状态是否可以支付
     * @param $payInfo
     * @throws \Exception
     */
    public function payBeforeCheck($payInfo)
    {
        // 正常订单的检查
        parent::payBeforeCheck($payInfo);
        // 拼团的相关检查
        $checkSetting = $this->checkActivitySetting();
        if ($checkSetting !== true) {
            throw new \Exception($checkSetting['msg'], $checkSetting['code']);
        }
        $checkGroupBuyingStatus = $this->checkGroupBuyingStatus();
        // 已经在团里的 已成团也可以继续支付
        if ($checkGroupBuyingStatus !== true && $checkGroupBuyingStatus['code'] != 1007) {
            throw new \Exception($checkGroupBuyingStatus['msg'], $checkGroupBuyingStatus['code']);
        }
    }

    /**
     * 创建新的拼团
     * @return GroupBuyingModel
     * @throws \Exception
     */
    public function createGroupBuying()
    {
        $model = new GroupBuyingModel();
        $model->site_id = $this->_orderModel->site_id;
        // 获取当前购买商品的活动商品id 每个订单只有一个商品
        $product = $this->_productList;
        $productId = $product[0]->getGroupProductId();
        $groupProduct = GroupBuyingProductsModel::query()
            ->where('site_id', $this->_orderModel->site_id)
            ->where('id', $productId)
            ->where('group_buying_setting_id', $this->_groupBuyingSettingId)
            ->first();
        $model->group_product_id = $productId;
        // 新创建的 当前会员一定是团长
        $model->head_member_id = $this->_memberId;
        $setting = $this->getGroupBuyingSetting();
        // 计算结束时间
        $endTime = strtotime('+' . $setting->close_day . ' day +' . $setting->close_hour . ' hour +' . $setting->close_minute . ' minute', time());
        // 如果活动结束时间更早 则是按活动结束时间
        $settingEndTime = strtotime($setting->end_time);
        if ($settingEndTime < $endTime) {
            $endTime = $settingEndTime;
        }
        $model->end_time = date('Y-m-d H:i:s', $endTime);
        $model->need_people_num = $setting->people_num;
        $model->current_people_num = 1;
        $model->group_buying_setting_id = $this->_groupBuyingSettingId;
        $model->status = GroupBuyingConstants::GroupBuyingTearmStatus_No;
        $model->snapshot = $this->getGroupBuyingSnapshot($setting, $groupProduct); // 活动设置快照
        $model->member_ids = json_encode([$this->_memberId]);
        $model->created_at = date('Y-m-d H:i:s');
        $model->save();
        $this->_groupBuyingId = $model->id;
        return $model;
    }

    /**
     * @param GroupBuyingSettingModel $setting
     * @param GroupBuyingProductsModel $groupProduct
     * @return string
     */
    public function getGroupBuyingSnapshot($setting, $groupProduct)
    {
        // 获取sku数据
        $skus = GroupBuyingSkusModel::query()
            ->where('group_product_id', $groupProduct->id)
            ->get();
        return json_encode([
            'setting' => $setting->toArray(),
            'product' => $groupProduct->toArray(),
            'sku' => $skus->toArray()
        ]);
    }

    /**
     * 支付成功之后更新拼团数据
     * @return GroupBuyingModel|\Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function payAfterUpdateGroupBuying()
    {
        // 如果有id 说明已经创建了团 是加入拼团的过程
        if ($this->_groupBuyingId) {
            // 获取当前团信息
            $groupBuying = GroupBuyingModel::query()
                ->where('site_id', $this->_orderModel->site_id)
                ->where('id', $this->_groupBuyingId)
                ->first();
            $groupBuying->current_people_num += 1;
            // 更新参团会员id
            $member_ids = json_decode($groupBuying->member_ids, true);
            array_push($member_ids, $this->_memberId);
            $groupBuying->member_ids = json_encode($member_ids);
            // 没有成团的 要判断是否成团
            if (
                $groupBuying->status == GroupBuyingConstants::GroupBuyingTearmStatus_No
                && $groupBuying->need_people_num <= $groupBuying->current_people_num
            ) {
                $groupBuying->status = GroupBuyingConstants::GroupBuyingTearmStatus_Yes;
                $groupBuying->success_at = date('Y-m-d H:i:s');
                // 成团后的操作
                $this->_isSuccess = true;
            } elseif ($groupBuying->status == GroupBuyingConstants::GroupBuyingTearmStatus_Yes) {
                $this->_isSuccess = true;
            }
            $groupBuying->save();
            return $groupBuying;
        } else {
            // 没有id 说明是新建的团
            return $this->createGroupBuying();
        }
    }

    /**
     * 支付成功后 更新订单数据
     * @param $payInfo
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function payAfterUpdateOrderSave($payInfo)
    {
        parent::payAfterUpdateOrderSave($payInfo);
        // 如果成团了 处理成团后的操作
        if ($this->_isSuccess) {
            $this->groupBuyingSuccess();
        }
    }

    /**
     * 拼团成功后的处理
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function groupBuyingSuccess()
    {
        // 处理成团后的订单
        GroupBuying::groupBuyingSuccessAfter($this->_groupBuyingId);
    }

    /**
     * 拼团成功后 更新订单的相关数据
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function groupBuyingSuccessAfterUpdate($isSuccess = null)
    {
        try {
            if ($isSuccess !== null) {
                $this->_isSuccess = $isSuccess;
            }
            DB::beginTransaction();
            $this->payAfterUpdateOther();
            DB::commit();
            if ($this->_isSuccess) {
                parent::payAfterBindInvite();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 支付成功后是否需要绑定关系等 拼团成功后已经做了处理
     * baseShopOrder 里面已经调用过一次 这里置为空 防止最后一个人重复调用
     */
    public function payAfterBindInvite()
    {

    }

    /**
     * 支付成功后对订单的处理
     * @throws \Exception
     */
    public function payAfterUpdateOrderData()
    {
        parent::payAfterUpdateOrderData();
        $groupBuying = $this->payAfterUpdateGroupBuying();
        $this->_orderModel->activity_id = $groupBuying->id;
    }

    /**
     * 订单支付成功 不一定成团 所以 这里只做订单的数据保存 其他的操作成团后处理
     * @param $payInfo
     */
    public function payAfter($payInfo)
    {
        $this->payAfterUpdateOrderSave($payInfo);
        $this->payAfterAddProductSoldCount();
    }

    /**
     * 支付成功后 会员升级等相关处理
     */
    public function payAfterUpdateMemberAndCommission()
    {
        if ($this->_isSuccess) {
            parent::payAfterUpdateMemberAndCommission();
        }
    }

    /**
     * 扣减库存
     * @return array
     * @throws \Exception
     */
    public function reduceInventory()
    {
        $parent = parent::reduceInventory();
        if ($parent['code'] != 200) {
            return $parent;
        }
        // 如果受库存限制 则去减掉活动库存
        $setting = $this->getGroupBuyingSetting();
        if ($setting->open_inventory == 1) {
            $products = $this->_productList;
            foreach ($products as $product) {
                $locker = new Locker($product->getGroupProductLock(), 10);
                if ($locker->lock(5)) {
                    try {
                        GroupBuyingSkusModel::query()
                            ->where('site_id', getCurrentSiteId())
                            ->where('group_buying_setting_id', $this->_groupBuyingSettingId)
                            ->where('id', $product->getGroupSkuId())
                            ->decrement('group_inventory', $product->num);
                    } catch (\Exception $e) {
                        $locker->unlock();
                        throw $e;
                    }
                } else {
                    throw new \Exception('can not init group buying sku locker');
                }
            }
        }
        return $parent;
    }

    /**
     * 付款成功之后增加销量
     */
    public function payAfterAddProductSoldCount()
    {
        parent::payAfterAddProductSoldCount();
        $products = $this->_productList;
        foreach ($products as $item) {
            $sku = GroupBuyingSkusModel::query()
                ->where('id', $item->getGroupSkuId())
                ->first();
            // 添加拼团sku的销量
            $sku->increment('sold_num', $item->num);
            // 添加拼团商品的销量
            GroupBuyingProductsModel::query()
                ->where('id', $sku->group_product_id)
                ->increment('total_sold_num', $item->num);
        }
    }

    /**
     * 返回库存
     * @param bool $backSoldCount
     * @throws \Exception
     */
    public function backInventory($backSoldCount = false)
    {
        parent::backInventory($backSoldCount);
        // 如果受库存限制 则去返回活动库存
        $setting = $this->getGroupBuyingSetting();
        if ($setting->open_inventory == 1) {
            $products = $this->_productList;
            foreach ($products as $item) {
                $sku = GroupBuyingSkusModel::query()
                    ->where('id', $item->getGroupSkuId())
                    ->first();
                if ($backSoldCount) {
                    // 加库存 减销量
                    $sku->group_inventory += $item->num;
                    $sku->sold_num -= $item->num;
                    $sku->save();
                    // 减少拼团商品的销量
                    GroupBuyingProductsModel::query()
                        ->where('id', $sku->group_product_id)
                        ->decrement('total_sold_num', $item->num);
                } else {
                    $sku->increment('group_inventory', $item->num);
                }
            }
        }
    }

    /**
     * 根据订单id 初始化的时候 设置其他参数
     * @param array $params 订单中item列表
     * @throws \Exception
     */
    public function initByOrderIdOtherParams($params = null)
    {
        // 已经有拼团id 说明已经开团
        if ($this->_orderModel->activity_id) {
            $groupBuying = GroupBuyingModel::query()
                ->where('site_id', $this->_orderModel->site_id)
                ->where('id', $this->_orderModel->activity_id)
                ->first();
            if (!$groupBuying) {
                throw new \Exception('group buying no found');
            }
            $this->_groupBuyingId = $groupBuying->id;
            $this->_groupBuyingSettingId = $groupBuying->group_buying_setting_id;
            if ($groupBuying->head_member_id == $this->_memberId) {
                $this->setIsHead();
            }
        } else {
            $groupBuyingSku = GroupBuyingSkusModel::query()
                ->where('site_id', $this->_orderModel->site_id)
                ->where('id', $params[0]->activity_sku_id)
                ->first();
            if ($groupBuyingSku) {
                $this->setIsHead();
                $this->_groupBuyingSettingId = $groupBuyingSku->group_buying_setting_id;
            } else {
                throw new \Exception('group buying sku no found');
            }
        }
    }

    /**
     * 获取其他参数 暂时给根据id初始化订单时用
     * @return array
     */
    public function getOtherParams()
    {
        return [
            'is_head' => $this->isHead()
        ];
    }

    /**
     * 保存订单数据
     * @param array $params 额外保存的数据 分为两个 order_data 和 product_data
     * @return string
     * @throws \Exception
     */
    public function saveOrderData($params = [])
    {
        $orderId = parent::saveOrderData($params);
        // 有团长优惠 要保存优惠信息
        if ($this->isHead() && $this->getGroupBuyingSetting()->open_head_discount == 1) {
            // 先获取团长优惠的价格
            $productDiscountPrice = [];
            // 获取到优惠的金额
            foreach ($this->_productList as $pro) {
                $productDiscountPrice[$pro->skuId] = $pro->calHeadDiscount();
            }
            $items = $this->_orderModel->items;
            $discount = [];
            foreach ($items as $item) {
                $discount[] = [
                    'site_id' => $item->site_id,
                    'item_id' => $item->id,
                    'order_id' => $orderId,
                    'discount_price' => $productDiscountPrice[$item->sku_id]
                ];
            }
            if ($discount) {
                //因为有观察者，所以不用用批量操作
                foreach ($discount as $item) {
                    $model = new OrderItemDiscountModel();
                    $model->fill($item);
                    $model->save();
                }
            }
        }
        return $orderId;
    }

    /**
     * 关闭前的数据更新
     */
    public function cancelBeforeUpdate()
    {
        $this->_orderModel->type_status = Constants::OrderType_GroupBuyingStatus_Fail;
    }

    /**
     * 订单关闭
     * @param string $msg
     * @return mixed|void
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function cancel($msg = '')
    {
        // 未付款订单 直接走原来的流程
        if ($this->_orderModel->status == Constants::OrderStatus_NoPay) {
            parent::cancel($msg);
        } elseif (
            $this->_orderModel->status == Constants::OrderStatus_OrderPay
            && $this->_orderModel->type_status == Constants::OrderType_GroupBuyingStatus_No
        ) {
            DB::beginTransaction();
            try {
                // 已支付 未成团 此时取消一般是超时自动取消
                //更改状态
                $this->_orderModel->status = Constants::OrderStatus_Cancel;
                // 取消原因
                $this->_orderModel->cancel_message = $msg;
                $this->_orderModel->end_at = date('Y-m-d H:i:s');
                $this->cancelBeforeUpdate();
                $this->_orderModel->save();
                //退积分
                PointModel::where('out_id', $this->_orderModel->id)
                    ->where('point', '=', $this->_orderModel->point * -1)
                    ->where('out_type', \YZ\Core\Constants::PointInOutType_OrderPay)
                    ->delete();
                $this->orderCloseAfter(1);
                // 需要退款
                FinanceHelper::refund(
                    $this->_orderModel->member_id,
                    $this->_orderModel->id,
                    $this->_orderModel->pay_type,
                    $this->_orderModel->transaction_id,
                    0,
                    $this->_orderModel->money,
                    " 未成团订单自动取消，退款",
                    0
                );
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw new \Exception(trans('shop-front.refund.cancel_order_fail') . ' msg:' . $e->getMessage());
            }
        } elseif (
            $this->_orderModel->status == Constants::OrderStatus_Cancel
        ) {
            // 已经关闭的订单 如果子状态没改 只改子状态即可
            if ($this->_orderModel->type_status == Constants::OrderType_GroupBuyingStatus_No) {
                $this->cancelBeforeUpdate();
                $this->_orderModel->save();
            }
        } else {
            throw new \Exception(trans('shop-front.refund.nocancel_1'));
        }
    }
}