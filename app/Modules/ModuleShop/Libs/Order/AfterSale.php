<?php
/**
 * 会员端售后业务逻辑
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Libs\Order;

use App\Modules\ModuleShop\Libs\Agent\AgentReward;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\AfterSaleItemModel;
use App\Modules\ModuleShop\Libs\Model\AfterSaleModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemDiscountModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\StoreConfigModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierModel;
use App\Modules\ModuleShop\Libs\Point\PointGiveHelper;
use App\Modules\ModuleShop\Libs\Supplier\SupplierAdmin;
use App\Modules\ModuleShop\Libs\Supplier\SupplierGroupBuyingShopOrder;
use App\Modules\ModuleShop\Libs\Supplier\SupplierShopOrder;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;
use YZ\Core\Finance\FinanceHelper;
use Illuminate\Support\Collection;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use Complex\Exception;
use App\Modules\ModuleShop\Libs\Statistics\Statistics;

class AfterSale
{
    protected $_memberId = null;
    protected $_orderModel = null;
    protected $_orderItem = null;
    protected $_afterSaleModel = null;
    protected $_afterSaleItem = null;
    private $siteId = 0; // 站点ID

    public function __construct($memberId = 0, $siteId = 0)
    {
        $this->_memberId = $memberId;

        if ($siteId) {
            $this->siteId = $siteId;
        } else if ($siteId == 0) {
            $this->siteId = Site::getCurrentSite()->getSiteId();
        }
    }

    /**
     * 用id去初始化售后类
     * @param int $id
     * @throws \Exception
     */
    public function initAfterSaleById($id)
    {
        $afterSale = AfterSaleModel::where(['site_id' => $this->siteId])->find($id);
        if ($afterSale) {
            $this->_afterSaleModel = $afterSale;
            $this->initOrderModel($afterSale->order_id);
        } else {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
    }

    /**
     * 初始化订单模型
     * @param $orderId
     * @throws \Exception
     */
    public function initOrderModel($orderId)
    {
        $order = OrderModel::where(['site_id' => $this->siteId])->find($orderId);
        if ($order) {
            $this->_orderModel = $order;
        } else {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
    }

    public function getAfterSaleModel()
    {
        return $this->_afterSaleModel;
    }

    public function getItemModel()
    {
        // 如果不存在 就去获取
        if (!$this->_afterSaleItem && $this->_afterSaleModel) {
            $this->_afterSaleItem = $this->_afterSaleModel->items;
        }
        return $this->_afterSaleItem;
    }

    public function getOrderModel()
    {
        return $this->_orderModel;
    }

    public function getOrderItem()
    {
        // 如果不存在 就去获取
        if (!$this->_orderItem && $this->_orderModel) {
            $this->_orderItem = $this->_orderModel->items;
        }
        return $this->_orderItem;
    }

    /**
     * 创建售后时获取数据
     * @param array $items 订单item的id 和 num的关联数组 形如 ['id' => num]
     * @return array
     * @throws \Exception
     */
    public function getAfterSaleData($items)
    {
        // 先检测是否可以申请售后
        $this->canAfterSale($items, 0);
        $orderItems = $this->getOrderItem()->toArray();
        $order = $this->getOrderModel()->toArray();
        $afterSaleProductList = [];
        $totalMoney = 0;
        $maxTotalMoney = 0;
        $refundFreight = $this->getRefundFreight($items);
        $isAll = $this->isAllAfterSale($items);
        foreach ($orderItems as $item) {
            if ($items[$item['id']]) {
                $item['refund_money'] = $this->getProductRefundMoney($item['id']);
                // $totalMoney +=$item['refund_money'] * $items[$item['id']];
                $totalMoney += $this->getProductTotalRefundMoney($item['id'], $items[$item['id']]);
                // $maxTotalMoney += $item['refund_money'] * $item['num'];
                $maxTotalMoney += $this->getProductTotalRefundMoney($item['id'], $item['num']);
                $data['refund_freight'] = $refundFreight[$item['id']] ? moneyCent2Yuan($refundFreight[$item['id']]) : 0;
                $data['refund_money'] = moneyCent2Yuan($item['refund_money']);
                $item['sku_names'] = json_decode($item['sku_names'], true);
                $data['sku_names'] = implode(' ', $item['sku_names']);
                $data['price'] = moneyCent2Yuan($item['price'] - ($item['coupon_money'] / $item['num'] + $item['point_money'] / $item['num']));//这个单价需要减去优惠的
                $data['id'] = $item['id'];
                $data['product_id'] = $item['product_id'];
                $data['name'] = $item['name'];
                $data['image'] = $item['image'];
                $data['num'] = $item['num'];
                $data['delivery_status'] = $item['delivery_status'];
                //防止以后有特殊情况改变暂时多加一个参数控制前端是否能够更改退款数量  0:不可改，1：可改
                if ($item['delivery_status'] == 0) {
                    $data['can_change_num'] = 0;
                } else {
                    $data['can_change_num'] = 1;
                }

                $afterSaleProductList[] = $data;
            }
        }

        $totalMoney = $isAll ? moneyCent2Yuan($totalMoney + $order['freight']) : moneyCent2Yuan($totalMoney);
        $maxTotalMoney = $isAll ? moneyCent2Yuan($maxTotalMoney + $order['freight']) : moneyCent2Yuan($maxTotalMoney);
        $freight = moneyCent2Yuan($order['freight']);
        return [
            'product_list' => $afterSaleProductList,
            'total_money' => $totalMoney,
            'is_all' => $isAll,
            'refund_freight' => $freight,
            'max_total_money' => $maxTotalMoney
        ];
    }

    /**
     * 编辑售后
     * @param int $id 售后订单id
     * @param array $param 要修改的参数
     * @return bool
     * @throws \Exception
     */
    public function editAfterSale($id, $param)
    {
        $this->initAfterSaleById($id);
        // 申请中的才可以编辑
        if ($this->_afterSaleModel->status == Constants::RefundStatus_Apply) {
            // 只能上传3张图片
            if ($param['image']) {
                if (count($param['image']) > 3) {
                    throw new \Exception(trans("shop-front.shop.most_upload_3_image"));
                }
            }
            // 如果是未发货 全部退款的 只能保存有限的数据
            if ($this->_afterSaleModel->is_all_after_sale == 1) {
                $data['reason'] = $param['reason'];
                $data['content'] = $param['content'];
                $data['images'] = implode(',', $param['image']);
                return $this->_afterSaleModel->fill($data)->save();
            }
            // 每个售后订单暂时只有1个产品
            $afterSaleItemModel = $this->getItemModel()[0];
            // 获取订单item表中的id和num关联数组
            $orderItems = $this->getOrderItem();
            $orderItems = $orderItems->pluck('num', 'id');
            // 数量不能超过购买数量
            if ($param['num'] <= 0 || $orderItems[$afterSaleItemModel->order_item_id] < $param['num']) {
                throw new \Exception(trans("shop-front.shop.after_sale_number_error"));
            }
            // 删除没用的图片
            beforeSaveImage($this->_afterSaleModel->images, $param['image']);
            $money = $this->getProductRefundMoney($afterSaleItemModel->order_item_id);
            //$data['total_money']=$money * $param['num'];
            $data['total_money'] = $this->getProductTotalRefundMoney($afterSaleItemModel->order_item_id, $param['num']);
            $data['product_quantity'] = $param['num'];
            $data['type'] = $param['type'];
            $data['reason'] = $param['reason'];
            $data['content'] = $param['content'];
            $data['images'] = implode(',', $param['image']);

            // 保存数据
            $this->_afterSaleModel->fill($data)->save();
            $afterSaleItemModel = AfterSaleItemModel::find($afterSaleItemModel->id);
            $afterSaleItemModel->num = $param['num'];
            $afterSaleItemModel->money = $money;
            $afterSaleItemModel->point_money = $this->getProductRefundPointMoney($afterSaleItemModel->order_item_id, $param['num']);;
            $afterSaleItemModel->coupon_money = $this->getProductRefundCouponMoney($afterSaleItemModel->order_item_id, $param['num']);;
            $afterSaleItemModel->save();
            // 修改OrderItem数据
            $newOrderItems = $this->getOrderItem()->where('id', $afterSaleItemModel->order_item_id)->first();
            $newOrderItems->after_sale_num = $param['num'];
            $newOrderItems->save();
            return true;
        } else {
            throw new \Exception(trans("shop-front.shop.after_sale_can_not_edit"));
        }
    }

    /**
     * 填写售后物流信息
     * @param int $id 售后订单id
     * @param string $logisticsNo 物流单号
     * @param int $logisticsKey 物流公司名称的key
     * @param string $logisticsName 物流公司名称 当选择其他时才需要
     * @return mixed
     * @throws \Exception
     */
    public function editLogisticsInfo($id, $logisticsNo, $logisticsKey, $logisticsName = null)
    {
        $this->initAfterSaleById($id);
        $afterSaleModel = $this->getAfterSaleModel();
        // 同意售后的才可以填写物流
        if ($afterSaleModel->status == Constants::RefundStatus_Agree) {
            if (!$logisticsNo) {
                throw new \Exception(trans("shop-front.shop.complete_after_sale_logistics_info"));
            }
            if ($logisticsKey == Constants::ExpressCompanyCode_Other) {
                if (!$logisticsName) {
                    throw new \Exception(trans("shop-front.shop.complete_after_sale_logistics_info"));
                }
                $name = $logisticsName;
            } else {
                $name = Constants::getExpressCompanyText($logisticsKey);
            }
            $afterSaleModel->return_logistics_company = $logisticsKey;
            $afterSaleModel->return_logistics_name = $name;
            $afterSaleModel->return_logistics_no = $logisticsNo;
            // 修改状态为卖家已发货
            $afterSaleModel->status = Constants::RefundStatus_Shipped;
            $flag = $afterSaleModel->save();
            //同步订单记录关于售后的冗余数据
            static::syncOrderAfterSaleData($afterSaleModel->order_id);
            return $flag;
        } else {
            throw new \Exception(trans("shop-front.shop.data_error"));
        }
    }

    /**
     * 创建售后
     * @param $orderId 售后订单id
     * @param $items 要退款的产品item 形如 [['id' => 'num']]
     * @param $param 相关数据
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function createAfterSale($orderId, $items, $param)
    {
        // 只能上传3张图片
        if ($param['images']) {
            if (count($param['images']) > 3) {
                throw new \Exception(trans("shop-front.shop.most_upload_3_image"));
            }
        }
        $this->canAfterSale($items);
        $saveCreateAfterSale = $this->saveCreateAfterSale($orderId, $items, $param);
        if ($saveCreateAfterSale) {
            // 发送通知
            MessageNoticeHelper::sendMessageAfterSaleApply($this->getAfterSaleModel());
            return $saveCreateAfterSale;
        } else {
            return false;
        }

    }

    /**
     * 保存创建售后订单的数据
     * @param string $orderId 售后订单id
     * @param array $items 要退款的产品item 形如 [['id' => 'num']]
     * @param array $param 相关数据
     * @throws \Exception
     */
    public function saveCreateAfterSale($orderId, $items, $param)
    {
        $afterSaleMoney = $this->applyAfterSale($items);
        // 获取订单item表中的id和product_id关联数组
        $orderItems = $this->getOrderItem();
        //获取总订单
        $order = $this->getOrderModel();
        $orderItems = $orderItems->pluck('product_id', 'id');
        $siteId = Site::getCurrentSite()->getSiteId();
        $isAll = $this->isAllAfterSale($items);
        $isAll = $isAll ? 1 : 0;
        $afterSaleModel = null;
        $itemData = [];
        if ($isAll) {
            $total = 0;
            foreach ($items as $k => $n) {
                $total += $afterSaleMoney['total_money'][$k];
            }
        }
        foreach ($items as $key => $num) {
            // 还没有保存售后信息 或者 不是全部退款 则需要保存一下
            if ($afterSaleModel == null || !$isAll) {
                $data['id'] = generateAfterSaleId();
                $data['order_id'] = $orderId;
                $data['member_id'] = $this->_memberId;
                $data['site_id'] = $siteId;
                $data['status'] = Constants::RefundStatus_Apply;
                $data['refund_type'] = Constants::AfterSaleRefundType_Original;
                //如果是全部退款的，只生成一条退款记录，价格需要累加
                if ($isAll) {
                    //只有全单退款并且都未发货的时候，才退运费，运费只拿订单表(tbl_oreder)里面的运费
                    $data['total_refund_freight'] = $order->freight;
                    //此字段不含运费
                    $data['total_money'] = $total;
                    // $data['total_refund_freight'] += ($afterSaleMoney ? $afterSaleMoney['refund_freight'][$k] : 0);
                } else {
                    $data['total_money'] = $afterSaleMoney['total_money'][$key];
                    //$data['total_refund_freight'] = $afterSaleMoney ? $afterSaleMoney['refund_freight'][$key] : 0;
                }
                $data['product_quantity'] = $num;
                $data['type'] = $param['type'];
                $data['is_all_after_sale'] = $isAll;
                $data['reason'] = $param['reason'];
                $data['content'] = $param['content'];
                $data['images'] = implode(',', $param['image']);
                $data['receive_status'] = $param['receive_status'];
                $afterSaleModel = new AfterSaleModel();
                $afterSaleModel->fill($data)->save();
                $this->initAfterSaleById($afterSaleModel->id);
            }
            $afterSaleItemData = [
                'site_id' => $siteId,
                'order_id' => $orderId,
                'order_item_id' => $key,
                'product_id' => $orderItems[$key],
                'num' => $num,
                'money' => $afterSaleMoney['money'][$key],
                'refund_freight' => $afterSaleMoney['refund_freight'][$key],
                'coupon_money' => $afterSaleMoney['coupon_money'][$key],
                'point_money' => $afterSaleMoney['point_money'][$key],
            ];
            // 不是全部退货的 每个产品要生成一条售后记录
            if (!$isAll) {
                $afterSaleModel->items()->create($afterSaleItemData);
            }
            $itemData[] = $afterSaleItemData;
            //增加产品表里面的售后数量
            ProductModel::query()->where('id', $orderItems[$key])->increment('after_sale_count', intval($num));
        }

        // 全部退货的最后保存一下
        if ($isAll && $itemData) {
            $afterSaleModel->items()->createMany($itemData);
        }

        // 冗余到订单明细
        foreach ($itemData as $afterSaleItem) {
            $afterSaleNum = intval($afterSaleItem['num']);
            $orderItem = OrderItemModel::find($afterSaleItem['order_item_id']);
            if ($orderItem) {
                $orderItem->after_sale_num = intval($orderItem->after_sale_num) + $afterSaleNum;
                $orderItem->comment_status = Constants::OrderItemCommentStatus_ForbidComment; // 提交了售后就禁止评论
                $orderItem->save();
            }
        }
        //同步订单记录关于售后的冗余数据
        static::syncOrderAfterSaleData($afterSaleModel->order_id);
        // 处理订单评论状态
        $orderHelper = new OrderHelper();
        $orderHelper->updateCommentStatus($orderId);
        // 返回id
        return $afterSaleModel->id;
    }

    /**
     * 申请售后的处理 需先调用initOrderModel
     * @param array $items 要退款的产品item 形如 [['id' => 'num']]
     * @return array
     * @throws \Exception
     */
    public function applyAfterSale($items)
    {
        $this->canAfterSale($items);
        // 每个产品应退的钱
        $refundMoneyArray = [];
        $totalMoney = [];
        foreach ($items as $key => $num) {
            $refundMoneyArray[$key] = $this->getProductRefundMoney($key);
            $refundCouponMoneyArray[$key] = $this->getProductRefundCouponMoney($key, $num);
            $refundPonintMoneyArray[$key] = $this->getProductRefundPointMoney($key, $num);
            // $totalMoney += $refundMoneyArray[$key] * $num;
            $totalMoney[$key] = $this->getProductTotalRefundMoney($key, $num);
        }
        $refundFreight = $this->getRefundFreight($items);
        return [
            'money' => $refundMoneyArray,
            'coupon_money' => $refundCouponMoneyArray,
            'point_money' => $refundPonintMoneyArray,
            'refund_freight' => $refundFreight,
            'total_money' => $totalMoney
        ];
    }

    /**
     * 撤销售后申请
     * @throws \Exception
     */
    public function cancelAfterSale()
    {
        // 申请中的才可以撤销
        if ($this->_afterSaleModel->status == Constants::RefundStatus_Apply) {
            // 只能撤销一次 查找该订单中的该产品是否有撤销的售后
            $afterSaleItem = $this->getItemModel()[0];
            $hasCancel = AfterSaleModel::query()
                ->where('order_id', $afterSaleItem->order_id)
                ->where('status', Constants::RefundStatus_Cancel)
                ->whereHas('items', function ($query) use ($afterSaleItem) {
                    $query->where('order_item_id', $afterSaleItem->order_item_id);
                })
                ->get();
            if ($hasCancel->isNotEmpty()) {
                throw new \Exception(trans("shop-front.shop.after_sale_most_cancel_1"));
            } else {
                $this->_afterSaleModel->status = Constants::RefundStatus_Cancel;
                $this->_afterSaleModel->cancel_at = Carbon::now();
                // 撤销的时候产品的售后数量要减对应的数量
                $thisAfterSaleItem = $this->getItemModel();
                foreach ($thisAfterSaleItem as $k => $v) {
                    $orderItems = $this->getOrderItem()->where('id', $v->order_item_id)->first();
                    ProductModel::query()->where('id', $v->product_id)->decrement('after_sale_count', $orderItems->after_sale_num);
                    $orderItems->after_sale_num = 0;
                    $orderItems->save();
                }
                $saveResult = $this->_afterSaleModel->save();
                //同步订单记录关于售后的冗余数据
                static::syncOrderAfterSaleData($this->_afterSaleModel->order_id);
                // 如果全部售后成功，调整订单状态
                $orderHelper = new OrderHelper();
                $orderHelper->updateStatusForSendReceive($afterSaleItem->order_id);
                return $saveResult;
            }
        } else {
            throw new \Exception(trans("shop-front.shop.after_sale_status_can_not_cancel"));
        }
    }

    /**
     * 可退款金额和应退款金额
     * @param int $itemId
     * @return int
     */
    public function getProductRefundMoney($itemId)
    {
        $orderItems = $this->getOrderItem();
        $item = $orderItems->where('id', $itemId)->where('site_id', $this->siteId)->first();
        return $item->real_price;
        // 后面的不需要了，在下单时已经算好了商品的单价 2020-07-08 泉
        // 查找是否还有其他优惠 也要减去
        $discount = OrderItemDiscountModel::query()
            ->where('site_id', $this->siteId)
            ->where('item_id', $itemId)
            ->first();
        // 计算出订单中该产品的总价
        $totalMoney = $item['price'] * $item['num'] - $item['point_money'] - $item['coupon_money'];
        if ($discount) {
            $totalMoney -= $discount->discount_price;
        }
        // 产品实付单价
        $productPrice = floor($totalMoney / $item['num']);
//        $money = $productPrice * $num;
        return $productPrice;
    }

    /**
     * 获取可退总金额
     * @param int $itemId
     * @return int
     */
    public function getProductTotalRefundMoney($itemId, $num)
    {
        $orderItems = $this->getOrderItem();
        $item = $orderItems->where('id', $itemId)->where('site_id', $this->siteId)->first();
        return $item->real_price * $num;
        // 后面的不需要了，在下单时已经算好了商品的单价 2020-07-08 泉
        // 查找是否还有其他优惠 也要减去
        $discount = OrderItemDiscountModel::query()
            ->where('site_id', $this->siteId)
            ->where('item_id', $itemId)
            ->first();
        // 计算出订单中该产品的总价
        $totalMoney = $item['price'] * $item['num'] - $item['point_money'] - $item['coupon_money'];
        if ($discount) {
            $totalMoney -= $discount->discount_price;
        }
        $totalMoney = floor(($totalMoney / $item['num']) * $num);
        return $totalMoney;
    }

    /**
     * 可退的优惠券价格
     * @param int $itemId
     * @param int $num 退款的数量
     * @return int
     */
    public function getProductRefundCouponMoney($itemId, $num)
    {
        $orderItems = $this->getOrderItem();
        $item = $orderItems->where('id', $itemId)->where('site_id', $this->siteId)->first();
        //计算出此次退款此商品所需要退的优惠的价格
        $coupon_money = $item['coupon_money'] * ($num / $item['num']);
        return $coupon_money;
    }

    /**
     * 可退的积分的价格
     * @param int $itemId
     * @param int $num 退款的数量
     * @return int
     */
    public function getProductRefundPointMoney($itemId, $num)
    {
        $orderItems = $this->getOrderItem();
        $item = $orderItems->where('id', $itemId)->where('site_id', $this->siteId)->first();
        //计算出此次退款此商品所需要退的优惠的价格
        $point_money = $item['point_money'] * ($num / $item['num']);
        return $point_money;
    }

    /**
     * 可退运费金额
     * @param array $itemObj 要退款的产品item 形如 [['id' => 'num']]
     * @return array|int
     */
    public function getRefundFreight($itemObj)
    {
        // 判断订单状态 是否已发货 已发货的不退运费
        if ($this->_orderModel->status != Constants::OrderStatus_OrderPay) {
            return 0;
        }
        $orderItems = $this->getOrderItem();
        // 所有产品都要未发货 并且 要整个订单的产品都申请售后才可以退运费
        $freightArray = [];
        if ($this->isAllAfterSale($itemObj)) {
            $freightArray = $orderItems->pluck('freight', 'id');
            if (array_search(0, json_decode($freightArray, true))) {
                $arr = $freightArray;
                foreach ($arr as $k => &$v) {
                    $arr[$k] = 0;
                }
                $freightArray = new Collection($arr);
            }
        }
        return $freightArray;
    }

    /**
     * 是否是未发货状态的全部退货
     * @param array $items 要退款的产品item 形如 [['id' => 'num']]
     * @return bool
     */
    public function isAllAfterSale($items)
    {
        $orderItems = $this->getOrderItem();
        $isAll = true;
        // 未发货状态下的此订单的产品数量
        foreach ($orderItems as $item) {
            if (
                !isset($items[$item['id']])
                || $item['num'] != $items[$item['id']]
                || $item['delivery_status'] == Constants::OrderProductDeliveryStatus_Yes
            ) {
                $isAll = false;
            }
        }
        return $isAll;
    }

    /**
     * 检测订单是否可以申请售后 要先调用initOrderModel
     * @return bool
     * @throws \Exception
     */
    public function checkOrder()
    {
        if ($this->_orderModel) {
            // 可以申请售后的订单状态
            $canStatus = [
                Constants::OrderStatus_OrderPay,
                Constants::OrderStatus_OrderSend,
                Constants::OrderStatus_OrderReceive,
                Constants::OrderStatus_OrderSuccess,
            ];
            if (in_array($this->_orderModel->status, $canStatus)) {
                return true;
            }
        } else {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
        return false;
    }

    /**
     * 批量售后 检测订单中产品是否可以申请售后
     * @param array $itemIdArray 要检测的订单产品表 [id => num]
     * * @param int $checkStatus 是否需要坚持售后的状态 主要用于修改申请的时候
     * @return bool
     * @throws \Exception
     */
    public function checkOrderItem($itemIdArray, $checkStatus = 1)
    {
        if (!$this->_orderModel) {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
        $orderAfterSaleItem = $this->_orderModel->afterSaleItems;
        $orderItems = $this->getOrderItem();
        foreach ($itemIdArray as $id => $itemNum) {
            $item = $orderItems->where('id', $id)->first();
            if ($item->product_after_sale_setting == 1) {
                throw new \Exception('此商品不允许退款');
            }
        }
        $orderItems = $orderItems->pluck('num', 'id');
        // 检测是否已经申请过
        if ($orderAfterSaleItem->count()) {
            foreach ($itemIdArray as $itemId => $num) {
                $item = $orderAfterSaleItem->where('order_item_id', $itemId);
                // 申请数量是否超过
                if ($num <= 0 || $num > $orderItems[$itemId]) {
                    throw new \Exception(trans('shop-front.shop.after_sale_number_error'));
                }
                // 不需要检测售后状态
                if ($checkStatus != 1) {
                    continue;
                }
                $itemCount = $item->count();
                // 超过两次的 不可以再申请
                if ($itemCount >= 3) {
                    throw new \Exception(trans('shop-front.shop.product_cant_after_sale'));
                } // 要检测是否还在申请中
                else if ($itemCount) {
                    $item = $item->where('order_item_id', $itemId)->first();
                    // 查找对应的售后主表记录
                    $afterSale = AfterSaleModel::find($item->after_sale_id);
                    // 在申请中的 拒绝重复申请
                    if ($afterSale->status == Constants::RefundStatus_Apply) {
                        throw new \Exception(trans('shop-front.shop.product_cant_after_sale'));
                    }
                }
            }
        }
        return true;
    }

    /**
     * 检测是否可以申请售后
     * @param array $itemIdArray
     * @param int $checkStatus 是否需要检测售后的状态
     * @return bool
     * @throws \Exception
     */
    public function canAfterSale($itemIdArray, $checkStatus = 1)
    {
        // 检测订单是否可以申请售后
        if (!$this->checkOrder()) {
            throw new \Exception(trans('shop-front.shop.order_cant_after_sale'));
        }
        // 检测所选产品是否可以申请售后
        $this->checkOrderItem($itemIdArray, $checkStatus);
        return true;
    }

    /**
     * 上传售后图片
     * @param UploadedFile $image
     * @return string
     * @throws \Exception
     */
    public static function uploadAfterSaleImage(UploadedFile $image)
    {
        $rootPath = Site::getSiteComdataDir('', true);
        // 保存路径
        $savePath = '/afterSale/' . date('YM') . '/';
        // 保存名称
        $saveName = 'after-sale' . time() . str_random(5);

        $img = new FileUpload($image, $rootPath . $savePath);
        $extension = $img->getFileExtension();
        // 保存大图小图
        $img->reduceImageSize(800, $saveName);
        return $savePath . $saveName . '.' . $extension;
    }

    /**
     * 获取售后页面需要的文案
     * @return array
     */
    public static function getAfterSaleText()
    {
        // 退款原因
        $refundReason = [
            Constants::AfterSaleReason_ProductError => "发错货/少件/空包/包装破损",
            Constants::AfterSaleReason_ProductQuality => "商品质量问题",
            Constants::AfterSaleReason_ProductDescError => "实物与商品描述不符",
            Constants::AfterSaleReason_Consensus => "买卖双方协商一致"
        ];
        // 退货原因
        $returnProducts = [
            Constants::AfterSaleReason_NotLike => "不喜欢/不想要",
            Constants::AfterSaleReason_TimeoutDelivery => "未按约定时间发货",
            Constants::AfterSaleReason_NotDelivered => "快递/物流一直未送到",
            Constants::AfterSaleReason_Refusal => "货物破损已拒签",
            Constants::AfterSaleReason_QualityProblems => "产品质量存在问题",
            Constants::AfterSaleReason_EffectNotMatch => "效果与描述不符"
        ];
        // 售后类型
        $type = [
            Constants::AfterSaleType_Refund => '只退款',
            Constants::AfterSaleType_ReturnProduct => '退货退款'
        ];
        // 是否收到货
        $isReceive = [
            Constants::AfterSale_ReceiveNo => '未收到货',
            Constants::AfterSale_ReceiveYes => '已收到货'
        ];
        return [
            'refund_reason' => $refundReason,
            'return_products_reason' => $returnProducts,
            'type' => $type,
            'is_receive' => $isReceive
        ];
    }

    /**
     * 获取售后详情
     * @param string $id
     * @param boolean 因前台详情与后台详情显示文案不一样
     * @return array
     * @throws \Exception
     */
    public function getAfterSaleInfo($id, $front = false)
    {
        $this->initAfterSaleById($id);
        $afterSaleModel = $this->getAfterSaleModel()->toArray();
        $items = $this->getItemModel();
        $order = $this->getOrderModel();
        $reason = Constants::getAfterSaleReceiveText($afterSaleModel['receive_status']) . ' ' . Constants::getAfterSaleReasonText($afterSaleModel['reason']);
        $member = \DB::table('tbl_member')->where(['site_id' => $this->siteId, 'id' => $afterSaleModel['member_id']])->first();
        $supplier = \DB::table('tbl_supplier')->where(['site_id' => $this->siteId, 'member_id' => $order['supplier_member_id']])->first();
        $finance = \DB::table('tbl_finance')->where(['site_id' => $this->siteId, 'order_id' => $afterSaleModel['order_id'], 'tradeno' => $order->transaction_id, 'status' => '1'])->first();
        // 只能撤销一次 查找该订单中的该产品是否有撤销的售后
        $afterSaleItem = $this->getItemModel()[0];
        $hasCancel = AfterSaleModel::query()
            ->where('order_id', $afterSaleItem->order_id)
            ->where('status', Constants::RefundStatus_Cancel)
            ->whereHas('items', function ($query) use ($afterSaleItem) {
                $query->where('order_item_id', $afterSaleItem->order_item_id);
            })
            ->get();
        // 售后订单基本信息
        $data = [
            'order_id' => $afterSaleModel['order_id'],
            'images' => $afterSaleModel['images'] ? explode(',', $afterSaleModel['images']) : '',
            'status' => $afterSaleModel['status'],
            'after_sale_status' => $this->getAfterSaleStatus($afterSaleModel['status'], $afterSaleModel['type']),
            'status_text' => $front
                ? Constants::getDetailAfterSaleStatusText($this->getAfterSaleStatus($afterSaleModel['status'], $afterSaleModel['type']), $afterSaleModel['refuse_msg'])
                :
                Constants::getFrontAfterSaleStatusText($this->getAfterSaleStatus($afterSaleModel['status'], $afterSaleModel['type'])),
            'id' => $afterSaleModel['id'],
            'created_at' => $afterSaleModel['created_at'],
            'reason_status' => $afterSaleModel['reason'],
            'reason' => $reason,
            'refuse_msg' => $afterSaleModel['refuse_msg'],
            'type' => Constants::getAfterSaleTypeText($afterSaleModel['type']),
            'type_status' => $afterSaleModel['type'],//售后类型
            'order_type_status' => $order->type_status,
            'refund_type' => Constants::getAfterSaleRefundTypeText($afterSaleModel['refund_type']),
            'actual_amount' => $afterSaleModel['is_all_after_sale'] == 1 ? moneyCent2Yuan($afterSaleModel['total_money'] + $afterSaleModel['total_refund_freight']) : moneyCent2Yuan($afterSaleModel['total_money']), //实付金额 小计+运费
            'content' => $afterSaleModel['content'],
            'remark' => $order->remark,
            'receive_status' => $afterSaleModel['receive_status'],
            'nickname' => $member->nickname,
            'name' => $member->name,
            'member_id' => $member->id,
            'mobile' => $member->mobile,
            'headurl' => $member->headurl,
            'order_created_at' => $order->created_at,//下单时间
            'order_terminal_type' => $order->terminal_type,//下单终端类型
            'pay_created_at' => $finance->created_at,//支付时间
            'order_status' => $order->status,
            'order_type' => $order->type,
            'tradeno' => $finance->tradeno,//流水号
            'pay_type' => $finance->pay_type,//支付渠道
            'return_logistics_company' => $afterSaleModel['return_logistics_company'],
            'return_logistics_name' => $afterSaleModel['return_logistics_name'],
            'return_logistics_no' => $afterSaleModel['return_logistics_no'],
            'is_all_after_sale' => $afterSaleModel['is_all_after_sale'],
            'total_refund_freight' => moneyCent2Yuan($afterSaleModel['total_refund_freight']),
            'real_money' => $afterSaleModel['real_money'] ? moneyCent2Yuan($afterSaleModel['real_money']) : 0,
            'return_logistics_web' => 'https://m.ickd.cn/result.html#no=' . $afterSaleModel['return_logistics_no'] . '&com=auto',
            'have_cancel' => $hasCancel->isNotEmpty(), //此订单是否撤销过，用于控制撤销的显示按钮，true为撤销过
            'cancel_at' => $afterSaleModel['cancel_at'], // 售后撤销时间
            'is_cancel' => $afterSaleModel['status'] == Constants::RefundStatus_Cancel, // 售后是否已撤销
            'refund_address_info' => null, // 退货地址信息
            'supplier_member_id' => $order->supplier_member_id,
            'supplier_name' => $supplier ? $supplier->name : ''
        ];
        // 等待用户退货状态，需要获取退货地址
        if ($data['after_sale_status'] == 3) {
            if ($order->supplier_member_id > 0) {
                $refundAddress = SupplierModel::where(['member_id' => $order->supplier_member_id])
                    ->select(['refunds_contacts', 'refunds_mobile', 'refunds_address', 'refunds_description'])
                    ->first();
            } else {
                $refundAddress = StoreConfigModel::where(['site_id' => $this->siteId])
                    ->select(['refunds_contacts', 'refunds_mobile', 'refunds_address', 'refunds_description'])
                    ->first();
            }
            if ($refundAddress && $refundAddress['refunds_address']) {
                $data['refund_address_info'] = $refundAddress;
            }
        }
        $productList = [];
        // 售后产品信息
        $orderItems = $this->getOrderItem();
        //优惠
        $preferential = 0;
        foreach ($items as $item) {
            $orderItem = $orderItems->where('id', '=', $item['order_item_id'])->first();
            $product['total_num'] = $orderItem['num'];
            $product['image'] = $orderItem['image'];
            $product['product_id'] = $orderItem['product_id'];
            $product['name'] = $orderItem['name'];
            $product['sku_name'] = json_decode($orderItem['sku_names'], true);
            $product['sku_name'] = implode(' ', $product['sku_name']);
            $product['refund_num'] = $item['num'];
            $product['item_id'] = $item['order_item_id'];
            // 计算出当前的实际优惠
            $orderItem['total_discount'] = $orderItem['total_discount'] / $product['total_num'] * $product['refund_num'];
            $product['preferential'] = $product['discount'] = moneyCent2Yuan($orderItem['total_discount']);
            $product['cost'] = moneyCent2Yuan($orderItem['cost']);
            $product['price'] = moneyCent2Yuan($orderItem['price']);
            // 计算出当前的实际金额小计
            $orderItem['total_money'] = $orderItem['total_money'] / $product['total_num'] * $product['refund_num'];
            $product['subtotal'] = moneyCent2Yuan($orderItem['total_money']);
            $product['num'] = $item['num'];
            $product['can_change_num'] = $orderItem['delivery_status'] == 0 ? 0 : 1;
            $product['delivery_status'] = $orderItem['delivery_status'];
            $product['supplier_member_id'] = $supplier ? $supplier->member_id : 0;
            $product['supplier_name'] = $supplier ? $supplier->name : '';
            $product['supplier_price'] = moneyCent2Yuan($orderItem['supplier_price']);
            $product['is_virtual'] = $orderItem['is_virtual'];
            $productList[] = $product;
            $preferential += $orderItem['total_discount'];
        }
        $data['products'] = $productList;
        $data['subtotal'] = moneyCent2Yuan($afterSaleModel['total_money']);    //小计=商品总额-优惠
        $data['preferential'] = moneyCent2Yuan($preferential);
        return $data;
    }

    /**
     * 获取用户的售后订单列表
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getAfterSaleList($page = 1, $pageSize = 10)
    {
        $list = AfterSaleModel::query()
            ->leftJoin('tbl_order', 'tbl_order.id', 'tbl_after_sale.order_id')
            ->leftJoin('tbl_supplier', 'tbl_order.supplier_member_id', 'tbl_supplier.member_id')
            ->where('tbl_after_sale.member_id', $this->_memberId)
            ->forPage($page, $pageSize + 1)
            ->select(['tbl_after_sale.id', 'tbl_after_sale.type as type', 'tbl_after_sale.status', 'tbl_order.supplier_member_id', 'tbl_supplier.name as supplier_name'])
            ->orderBy('tbl_after_sale.created_at', 'desc')
            ->get();

        foreach ($list as &$item) {
            $itemData = \DB::table('tbl_after_sale_item')
                ->leftJoin('tbl_order_item', 'tbl_order_item.id', '=', 'order_item_id')
                ->where(['tbl_after_sale_item.site_id' => $this->siteId, 'after_sale_id' => $item->id])->select(['tbl_order_item.*', 'tbl_after_sale_item.num as after_sale_item_num', 'tbl_after_sale_item.coupon_money as after_sale_item_coupon_money', 'tbl_after_sale_item.point_money as after_sale_item_point_money'])
                ->select(['tbl_after_sale_item.num as refund_num', 'tbl_order_item.num as buy_num', 'name', 'image', 'sku_names'])
                ->get();
            foreach ($itemData as $items) {
                $items->sku_names = implode(' ', json_decode($items->sku_names));
            }
            $item->item_list = new Collection($itemData);
            //1 申请退款  2 申请退货 3 等待买家退货 4 退款成功 5 待审核 6 审核不通过 7 退款关闭
            $item->after_sale_status = $this->getAfterSaleStatus($item->status, $item->type);
        }
        // 是否有下一页
        $nextPage = 0;
        if (count($list) > $pageSize) {
            $nextPage = $page + 1;
        }
        return [
            'list' => $list,
            'next_page' => $nextPage,
            'curr_page' => $page
        ];
    }

    /**
     * 根据RP状态合并
     * @param $params
     * $status 退款状态
     * $type 退款还是退货
     * @return array
     */
    public function getAfterSaleStatus($status, $type)
    {
        //1 申请退款  2 申请退货 3 等待买家退货 4 等待卖家收货 5 退款成功 6 待审核 7 审核不通过 8 退款关闭
        if ($type == Constants::AfterSaleType_Refund && $status == Constants::RefundStatus_Apply) {
            $after_sale_status = 1;
        } elseif ($type == Constants::AfterSaleType_ReturnProduct && $status == Constants::RefundStatus_Apply) {
            $after_sale_status = 2;
        } elseif ($type == Constants::AfterSaleType_ReturnProduct && $status == Constants::RefundStatus_Agree) {
            $after_sale_status = 3;
        } elseif ($type == Constants::AfterSaleType_ReturnProduct && $status == Constants::RefundStatus_Shipped) {
            $after_sale_status = 4;
        } elseif ($status == Constants::RefundStatus_Over) {
            $after_sale_status = 5;
        } elseif ($type == Constants::AfterSaleType_ReturnProduct && $status == Constants::RefundStatus_Received) {
            $after_sale_status = 6;
        } elseif ($status == Constants::RefundStatus_Reject) {
            $after_sale_status = 7;
        } elseif ($status == Constants::RefundStatus_Cancel) {
            $after_sale_status = 8;
        }
        return $after_sale_status;
    }

    /**
     * 查询列表
     * @param $params
     * @return array
     */
    public function getList($params)
    {
        $showAll = $params['show_all'] || ($params['ids'] && strlen($params['ids'] > 0)) ? true : false; // 是否显示所有，导出功能用，默认False

        $page = max(1, intval($params['page']));
        $pageSize = intval($params['page_size']);
        if ($pageSize < 1) $pageSize = 20;

        $query = AfterSaleModel::query();
        $query->leftJoin('tbl_order', 'tbl_order.id', '=', 'tbl_after_sale.order_id');
        $query->leftJoin('tbl_supplier as supplier', 'tbl_order.supplier_member_id', '=', 'supplier.member_id');
        $query->leftJoin('tbl_member as member', 'tbl_after_sale.member_id', '=', 'member.id');
        $query->leftJoin('tbl_finance as finance', 'finance.id', '=', 'finance_id');
        $query->Select(['tbl_after_sale.*', 'member.nickname', 'member.name', 'member.mobile', 'member.headurl', 'tbl_after_sale.status as after_sale_status',
            'tbl_after_sale.id as after_sale_id', 'tbl_after_sale.type as after_sale_type', 'tbl_order.type as order_type', 'tbl_order.pay_type','tbl_order.status as order_status',
            'tbl_order.terminal_type', 'tbl_order.supplier_member_id', 'supplier.name as supplier_name',
            'tbl_after_sale.created_at as after_sale_created_at', 'finance.active_at', 'finance.tradeno']);
        //$query ->addSelect('tbl_after_sale.*,tbl_order.terminal_type');
        // 构造关联与查询条件
        $this->setQuery($query, $params);
        // 总数
        $total = $query->count();
        if ($total > 0 && $showAll) {
            $page = 1;
            $pageSize = $total;
        }
        $last_page = ceil($total / $pageSize); // 总页数
        // 分页
        $query->forPage($page, $pageSize);
        // 查询结果
        $list = $query->orderBy('tbl_after_sale.created_at', 'desc')->get();
        $afterSaleIds = [];
        foreach ($list as $item) {
            $afterSaleIds[] = $item->after_sale_id;
        }
        if (count($afterSaleIds)) {
            $itemList = \DB::table('tbl_after_sale_item')
                ->leftJoin('tbl_order_item', 'tbl_order_item.id', '=', 'order_item_id')
                ->leftJoin('tbl_order_item_discount', 'tbl_order_item_discount.item_id', '=', 'order_item_id')
                ->leftJoin('tbl_product_skus', 'tbl_order_item.sku_id', '=', 'tbl_product_skus.id')
                ->leftJoin('tbl_supplier', 'tbl_supplier.member_id', '=', 'tbl_order_item.supplier_member_id')
                ->where('tbl_after_sale_item.site_id', $this->siteId)
                ->whereIn('after_sale_id', $afterSaleIds)
                ->select(['tbl_order_item.*', 'tbl_supplier.name as supplier_name', 'tbl_after_sale_item.after_sale_id', 'tbl_after_sale_item.num as after_sale_item_num', 'tbl_after_sale_item.coupon_money as after_sale_item_coupon_money', 'tbl_after_sale_item.point_money as after_sale_item_point_money', 'tbl_after_sale_item.money', 'tbl_order_item.num as order_item_num', 'tbl_order_item.point_money as order_item_point_money', 'tbl_order_item.coupon_money as order_item_coupon_money', 'tbl_product_skus.serial_number'])->get();
        }

        $list = $this->convertOutput($list, $itemList);

        return [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 数据转换
     * @param $list
     * @param $itemList
     * @return mixed
     */
    public function convertOutput($list, $itemList)
    {
        foreach ($list as &$item) {
            if ($item->real_money) {
                $item->real_money = moneyCent2Yuan($item->real_money);
            }
            $item->item_list = new Collection();
            for ($i = 0; $i < $itemList->count(); $i++) {
                $subItem = $itemList[$i];
                // 如果属于当前订单
                if ($subItem->after_sale_id == $item->after_sale_id) {
                    // 把匹配的从数组中剔除，以便后续运算更快
                    $productData = $itemList->splice($i, 1)[0];
                    if ($productData->refund_freight) {
                        $productData->refund_freight = moneyCent2Yuan($productData->refund_freight);
                    }
                    if ($productData->sku_names) {
                        $productData->sku_names = json_decode($productData->sku_names, true);
                        $productData->sku_names = implode(' ', $productData->sku_names);
                    }
                    // 计算出售后的实际优惠金额
                    $discount = $productData->total_discount / $productData->order_item_num * $productData->after_sale_item_num;
                    $productData->preferential = $productData->discount = moneyCent2Yuan($discount);
                    //小计=（单价*购买数量-积分优惠-优惠券优惠-活动优惠(暂指团长优惠)）/购买数量*退款数量
                    //这样做是为了能减少除法带来小数的误差，尽量跟商城订单一致
                    $subTotal = $productData->total_money / $productData->order_item_num * $productData->after_sale_item_num;
                    $productData->subtotal = moneyCent2Yuan($subTotal);
                    if ($productData->money) {
                        $productData->money = moneyCent2Yuan($productData->money);
                    }
                    if ($productData->price) {
                        $productData->price = moneyCent2Yuan($productData->price);
                    }
                    if ($productData->cost) {
                        $productData->cost = moneyCent2Yuan($productData->cost);
                    }
                    if ($productData->supplier_price) {
                        $productData->supplier_price = moneyCent2Yuan($productData->supplier_price);
                    }
                    $i = $i - 1;
                    $item->item_list->push($productData);
                }
            }
            $item->status_text = Constants::getFrontAfterSaleStatusText($this->getAfterSaleStatus($item->after_sale_satus, $item->after_sale_type));
            $item->after_sale_status = $this->getAfterSaleStatus($item->after_sale_status, $item->after_sale_type);
            $item->status_text = Constants::getFrontAfterSaleStatusText($item->after_sale_status);
            $item->actual_amount = $item->is_all_after_sale == 1 ? moneyCent2Yuan($item->total_money + $item->total_refund_freight) : moneyCent2Yuan($item->total_money); //实付金额 小计+运费
            if ($item->total_refund_freight) {
                $item->total_refund_freight = moneyCent2Yuan($item->total_refund_freight);
            }
        }

        return $list;
    }

    /**
     * 统计数量
     * @param $params
     * @return int
     */
    public function count($params)
    {
        $query = AfterSaleModel::query();
        $query->leftJoin('tbl_order', 'tbl_order.id', '=', 'tbl_after_sale.order_id');
        $query->leftJoin('tbl_member as member', 'tbl_after_sale.member_id', '=', 'member.id');
        // 构造关联与查询条件
        $this->setQuery($query, $params);
        return $query->count();
    }

    /**
     * 设置查询条件
     * @param $query
     * @param $params
     */
    function setQuery($query, $params)
    {
        //所属网站
        $query->where('tbl_after_sale.site_id', '=', $this->siteId);
        // 自营还是供应商
        if (array_key_exists('is_supplier', $params) && intval($params['is_supplier']) > -1) {
            $isSupplier = intval($params['is_supplier']);
            if ($isSupplier === 0) $query->where('tbl_order.supplier_member_id', 0);
            else $query->where('tbl_order.supplier_member_id', '>', 0);
        }
        //会员昵称
        if (trim($params['nickname'])) {
            $query->where('member.nickname', 'like', '%' . trim($params['nickname']) . '%');
        }
        // 会员手机
        if (trim($params['mobile'])) {
            $query->where('member.mobile', 'like', '%' . trim($params['mobile']) . '%');
        }
        //订单号id
        if ($params['order_id']) {
            $query->where('tbl_order.id', 'like', '%' . trim($params['order_id']) . '%');
        }
        //退款号id
        if ($params['id']) {
            $query->where('tbl_after_sale.id', 'like', '%' . trim($params['id']) . '%');
        }
        // 指定供应商
        if (array_key_exists('supplier_member_id', $params)) {
            $supplierMemberId = intval($params['supplier_member_id']);
            $query->where('tbl_order.supplier_member_id', $supplierMemberId);
        }
        if ($params['keyword']) {
            $keyword = $params['keyword'];
            $asciiKeyword = preg_replace('/[^\w]/', '', $keyword);
            $searchType = intval($params['search_type']);
            $query->where(function ($query) use ($searchType, $keyword, $asciiKeyword) {
                if ($searchType === 0 && $asciiKeyword) {
                    //搜索订单号
                    $query->orWhere('tbl_order.id', 'like', '%' . trim($asciiKeyword) . '%');
                    $query->orWhere('tbl_after_sale.id', 'like', '%' . trim($asciiKeyword) . '%');
                } elseif ($searchType === 1) {
                    //搜索买家
                    $query->orWhere('member.nickname', 'like', '%' . trim($keyword) . '%');
                    $query->orWhere('member.name', 'like', '%' . trim($keyword) . '%');
                    if ($asciiKeyword) $query->orWhere('member.mobile', 'like', '%' . trim($asciiKeyword) . '%');
                } elseif ($searchType === 2) {
                    //搜索供应商
                    $query->orWhere('supplier.name', 'like', '%' . trim($keyword) . '%');
                }
            });
        }
        //下单类型
        if (isset($params['terminal_type']) && $params['terminal_type'] >= 0) {
            $query->where('tbl_order.terminal_type', $params['terminal_type']);
        }
        // 下单时间
        if ($params['order_created_at_start']) {
            $query->where('tbl_order.created_at', '>=', $params['order_created_at_start']);
        }
        if ($params['order_created_at_end']) {
            $query->where('tbl_order.created_at', '<=', $params['order_created_at_end']);
        }
        // 申请时间
        if ($params['after_sale_created_at_start']) {
            $query->where('tbl_after_sale.created_at', '>=', $params['after_sale_created_at_start']);
        }
        if ($params['after_sale_created_at_end']) {
            $query->where('tbl_after_sale.created_at', '<=', $params['after_sale_created_at_end']);
        }

        // 状态
        if ($params['status'] !== '') {
            $status = myToArray($params['status']);
            if (count($status) > 0) {
                $query->whereIn('tbl_after_sale.status', $status);
            }
        }
        // 状态
        if ($params['type'] !== '') {
            $type = myToArray($params['type']);
            if (count($type) > 0) {
                $query->whereIn('tbl_after_sale.type', $type);
            }
        }
        // 订单类型
        if ($params['order_type'] !== '' && $params['order_type'] >= 0) {
            $type = myToArray($params['order_type']);
            if (count($type) > 0) {
                $query->whereIn('tbl_order.type', $type);
            }
        }
        // ids
        if ($params['ids']) {
            $ids = myToArray($params['ids']);
            if (count($ids) > 0) {
                $query->whereIn('tbl_after_sale.id', $ids);
            }
        }
    }

    /**
     * 获取订单中可以申请售后的产品列表
     * @return array
     */
    public function getCanAfterSaleProductList()
    {
        $orderAfterSaleItem = $this->_orderModel->afterSaleItems;
        $orderItems = $this->getOrderItem();
        $productList = []; // 可以申请售后的产品
        // 检测是否已经申请过
        foreach ($orderItems as $orderItem) {
            //如果此商品属于分销商品则不允许退款
//            if ($orderItem->is_commission_product == 1) {
//                continue;
//            }
            //如果此商品设置了单独不参与退款则不允许退款
            if ($orderItem->product_after_sale_setting == 1) {
                continue;
            }
            $item = $orderAfterSaleItem->where('order_item_id', $orderItem['id']);
            $itemCount = $item->count();
            // 超过一次的 不可以再申请
            if ($itemCount > 1) {
                continue;
            } // 只申请过一次的 要检测是否还在申请中
            else if ($itemCount) {
                $item = $item->where('order_item_id', $orderItem['id'])->first();
                // 查找对应的售后主表记录
                $afterSale = AfterSaleModel::find($item->after_sale_id);
                // 在申请中的 拒绝重复申请
                if ($afterSale->status === Constants::RefundStatus_Apply || $afterSale->status === Constants::RefundStatus_Over) {
                    continue;
                }
            }
            $skuNames = json_decode($orderItem['sku_names'], true);
            $product = [
                'id' => $orderItem['id'],
                'name' => $orderItem['name'],
                'image' => $orderItem['image'],
                'sku_name' => $skuNames ? implode(' ', $skuNames) : '',
                'num' => $orderItem['num'],
                'delivery_status' => $orderItem['delivery_status']
            ];
            $productList[] = $product;
        }
        return $productList;
    }

    /**
     * 编辑退款状态（审核）
     * @param $params
     * @param int $supplierId 供应商ID，如果不为0或空，则会验证此订单是否属于此供应商
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function editStatus($params, $supplierId = 0)
    {
        $this->initAfterSaleById($params['id']);
        $financeHelp = new FinanceHelper();
        $order = $this->getOrderModel();
        if ($supplierId && $order->supplier_member_id != $supplierId) {
            throw new \Exception("没有更改此售后单的权限");
        }
        if ($supplierId && $this->_afterSaleModel->status != Constants::RefundStatus_Shipped) {
            throw new \Exception("供应商只有确认收货的权限");
        }
        $afterSaleItems = $this->getItemModel()->toArray();
        $refundFinanceId = 0;
        // 退款退货申请中
        if ($this->_afterSaleModel->status == Constants::RefundStatus_Apply || $this->_afterSaleModel->status == Constants::RefundStatus_Received) {
            // 分状态处理
            if (($params['status'] == 1 && $this->_afterSaleModel->type == Constants::AfterSaleType_Refund)
                ||
                ($params['status'] == 1 && $this->_afterSaleModel->type == Constants::AfterSaleType_ReturnProduct && $this->_afterSaleModel->status == Constants::RefundStatus_Received)) {
                // 更改订单状态（是否有售后）
                $order->has_after_sale = 1;
                $order->save();
                // 更改订单子列表状态
                foreach ($afterSaleItems as $k => $v) {
                    $orderItems = $this->getOrderItem()->where('id', $v['order_item_id'])->first();
                    $order_items = $orderItems->toArray();
                    // after_sale_over_num 成功售后数量
                    $afterSaleItemNum = intval($v['num']);
                    // 剩余的售后中的数量
                    $afterSaleNum = intval($orderItems->after_sale_num) > $afterSaleItemNum ? intval($orderItems->after_sale_num) - $afterSaleItemNum : 0;
                    $orderItems->after_sale_over_num = intval($orderItems->after_sale_over_num) + $afterSaleItemNum;
                    if ($orderItems->after_sale_over_num > $orderItems->num) $orderItems->after_sale_over_num = $orderItems->num;
                    $orderItems->after_sale_num = $afterSaleNum;
                    $this->_afterSaleModel->real_money = moneyYuan2Cent(abs($params['money']));
                    $orderItems->save();
                    $sku_id = $this->_afterSaleModel->is_all_after_sale ? 0 : $order_items['sku_id'];
                }
                //退款
                $refundReason = Constants::getAfterSaleReceiveText($this->_afterSaleModel->receive_status) . ' ' . Constants::getAfterSaleReasonText($this->_afterSaleModel->reason);
                $refundFinanceId = $financeHelp->refund($this->_afterSaleModel->member_id, $order->id, $order->pay_type, $order->transaction_id, $sku_id, moneyYuan2Cent(abs($params['money'])), $refundReason, $v['after_sale_id']);
                $order->increment('after_sale_money', -abs(moneyYuan2Cent($params['money'])));
                $shopOrder = ShopOrderFactory::createOrderByOrderId($order->id);
                // 处理分销佣金退还
                $shopOrder->deductDistributionCommision();
                // 处理分销佣金状态
                FinanceHelper::commissionChangeStatusByOrder($order->id);
                // 订单类代理佣金退还
                $shopOrder->deductAgentOrderCommision();
                $shopOrder->deductAgentSaleRewardCommision();
                // 代理佣金其他奖退款
                $shopOrder->deductAgentOtherRewardCommision();
                // 退还区域代理佣金
                $shopOrder->deductAreaAgentCommission();
                // 供应商结算处理
                $shopOrder->deductSettleData($this->_afterSaleModel);

                $this->_afterSaleModel->status = Constants::RefundStatus_Over;
                $this->_afterSaleModel->finance_id = $refundFinanceId;
            } else if ($params['status'] == 1 && $this->_afterSaleModel->type == Constants::AfterSaleType_ReturnProduct) {
                // 退货
                $this->_afterSaleModel->status = Constants::RefundStatus_Agree;
            } else {
                $this->_afterSaleModel->status = Constants::RefundStatus_Reject;
                $this->_afterSaleModel->refuse_msg = $params['refuse_msg'];
                foreach ($afterSaleItems as $k => $v) {
                    $orderItems = $this->getOrderItem()->where('id', $v['order_item_id'])->first();
                    $orderItems->after_sale_num = 0;
                    $orderItems->save();
                    //增加产品表里面的售后数量
                    ProductModel::query()->where('id', $v['product_id'])->decrement('after_sale_count', intval($v['num']));
                }
            }
            $this->_afterSaleModel->save();
            // 计算要扣除的赠送积分，这个要在状态变更保存之后
            PointGiveHelper::DeductForConsumeRefund($this->_afterSaleModel);
            // 如果全部售后成功，调整订单状态
            $orderHelper = new OrderHelper();
            $orderHelper->updateStatusForSendReceive($order->id);
            if ($this->_afterSaleModel->status == Constants::RefundStatus_Over) {
                // 退款扣除业绩（支付后）
                AgentReward::buildOrderPerformanceForAfterSale($order->id);
            }
            // 统计数据
            if ($this->_afterSaleModel->status == Constants::RefundStatus_Over) {
                $statistics = new Statistics($order->id);
                //退款需要扣除
                $statistics->calcMemberStatistics($this->_afterSaleModel);
            }
        } elseif ($this->_afterSaleModel->status == Constants::RefundStatus_Shipped) {
            $this->_afterSaleModel->status = Constants::RefundStatus_Received;
            $this->_afterSaleModel->save();
        }

        //同步订单记录关于售后的冗余数据
        static::syncOrderAfterSaleData($this->_afterSaleModel->order_id);

        // 发送通知
        $orderStatus = intval($this->_afterSaleModel->status);
        if ($orderStatus == Constants::RefundStatus_Agree) {
            // 同意退货通知
            MessageNoticeHelper::sendMessageGoodsRefundAgree($this->_afterSaleModel);
        } else if ($orderStatus == Constants::RefundStatus_Reject) {
            if (intval($this->_afterSaleModel->type) == Constants::AfterSaleType_ReturnProduct) {
                // 拒绝退货通知
                MessageNoticeHelper::sendMessageGoodsRefundReject($this->_afterSaleModel);
            } else {
                // 拒绝退款通知
                MessageNoticeHelper::sendMessageMoneyRefundReject($this->_afterSaleModel);
            }
        } else if ($orderStatus == Constants::RefundStatus_Over) {
            // 退款成功通知
            MessageNoticeHelper::sendMessageMoneyRefundSuccess($this->_afterSaleModel);
            // 余额变更通知
            if (intval($order->pay_type) == \YZ\Core\Constants::PayType_Balance && $refundFinanceId) {
                MessageNoticeHelper::sendMessageBalanceChange(FinanceModel::find($refundFinanceId));
            }
        }

        return true;
    }

    /**
     * 根据售后记录同步订单记录的售后冗余数据(售后状态和售后类型)
     *
     * @param string $orderId
     * @return void
     */
    public static function syncOrderAfterSaleData($orderId)
    {
        //根据最新的一条 非拒绝和已取消状态 的售后记录作为基准
        $query = \DB::table('tbl_after_sale')->where('order_id', $orderId)->whereNotIn('status', [2, -1]);
        $query->orderBy('id', 'desc');
        $afterSale = $query->first();
        $orderModel = \DB::table('tbl_order')->find($orderId);
        if ($afterSale) {
            $after_sale_status = $afterSale->status;
            $after_sale_type = $afterSale->type;
        } else {
            $after_sale_status = -1;
            $after_sale_type = -1;
        }
        \DB::table('tbl_order')->where('id', $orderId)->update(['after_sale_status' => $after_sale_status, 'after_sale_type' => $after_sale_type]);
    }
}
