<?php
/**
 * 前台订单业务类
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Libs\Order;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\AfterSaleItemModel;
use App\Modules\ModuleShop\Libs\Model\FreightTemplateModel;
use App\Modules\ModuleShop\Libs\Model\OrderConfigModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierModel;
use App\Modules\ModuleShop\Libs\Shop\IShopProduct;
use App\Modules\ModuleShop\Libs\Shop\NormalShopOrder;
use App\Modules\ModuleShop\Libs\Shop\NormalShopProduct;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use App\Modules\ModuleShop\Libs\Shop\ShopProductFactory;
use App\Modules\ModuleShop\Libs\Supplier\SupplierAdmin;
use YZ\Core\Constants as CodeConstants;
use YZ\Core\Member\Auth;
use YZ\Core\Site\Site;

class OrderFront
{
    protected $_order = null;
    protected $_memberId = 0;
    protected $_orderType = Constants::OrderType_Normal; // 订单类型 默认为普通订单

    /**
     * OrderFront constructor.
     * @param $memberId
     */
    public function __construct($memberId)
    {
        $this->_memberId = $memberId;
    }

    /**
     * 设置订单类型
     * @param $type
     */
    public function setOrderType($type)
    {
        $this->_orderType = $type;
    }

    /**
     * 获取订单类型
     * @return int
     */
    public function getOrderType()
    {
        return $this->_orderType;
    }

    /**
     * * 初始化单独的order
     * @param null $orderIdOrOrder
     * @param array $params 额外的参数
     * @throws \Exception
     */
    public function initOrder($orderIdOrOrder = null, $params = [])
    {
        $orderType = $this->getOrderType();
        if ($orderIdOrOrder && is_a($orderIdOrOrder, 'IShopOrder')) {
            $this->_order = $orderIdOrOrder;
        } else if ($orderIdOrOrder && is_string($orderIdOrOrder)) {
            $initProduct = true;
            if (isset($params['initProduct']) && !$params['initProduct']) {
                $initProduct = false;
            }
            $this->_order = ShopOrderFactory::createOrderByOrderId($orderIdOrOrder, $initProduct);
        } else {
            $this->_order = ShopOrderFactory::createOrderByType($this->_memberId, $orderType, $params, $isSupplier);
        }
        // 不属于当前会员的订单不能查看
        if ($this->_order && $this->_order->getThisMemberId() != $this->_memberId) {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
    }

    /**
     * 设置订单产品
     * @param $productList
     * @throws \Exception
     */
    public function setOrderProduct($productList)
    {
        $type = $this->getOrderType();
        foreach ($productList as $product) {
            if (isset($product['product_id']) && isset($product['sku_id']) && isset($product['num'])) {
                $shopProduct = ShopProductFactory::createShopProduct(
                    $product['product_id'],
                    $product['sku_id'],
                    $product['num'],
                    $type,
                    $this->_order->getOtherParams()
                );
                $this->_order->addProduct($shopProduct);
            } else {
                throw new \Exception(trans('shop-front.shop.data_error'));
            }
        }
    }

    /**
     * 获取当前baseShop order的实例
     * @return null|mixed
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * 获取已生成订单的信息
     * @return array
     * @throws \Exception
     */
    public function orderInfo()
    {
        $orderModel = $this->_order->getOrderModel();
        // 待付款的订单要算出付款倒计时 单位秒
        $payTime = 0; // 秒
        if ($orderModel->status == Constants::OrderStatus_NoPay) {
            // 订单支付时间配置
            $orderConfig = OrderConfigModel::find($orderModel->site_id);
            $payDay = $orderConfig->nopay_close_day ?: 0;
            $payHour = $orderConfig->nopay_close_hour ?: 0;
            $payMin = $orderConfig->nopay_close_minute ?: 0;
            // 超过支付时间 直接关闭该订单
            $payTime = OrderHelper::timeRemain($this->_order->getOrderModel()->created_at, $payDay, $payHour, $payMin);
            if ($payTime <= 0 && ($payDay > 0 || $payHour > 0 || $payMin > 0)) {
                $orderModel->status = Constants::OrderStatus_Cancel;
                // 执行取消订单
                $shopOrder = ShopOrderFactory::createOrderByOrderId($orderModel->id);
                $shopOrder->cancel();
            }
        }
        // 处理一下数据
        $orderModel->product_money = moneyCent2Yuan($orderModel->product_money);
        $orderModel->point_money = moneyCent2Yuan($orderModel->point_money);
        $orderModel->coupon_money = moneyCent2Yuan($orderModel->coupon_money);
        $orderModel->money = moneyCent2Yuan($orderModel->money);
        $orderModel->freight = moneyCent2Yuan($orderModel->freight);
        $orderModel->manual_discount = moneyCent2Yuan($orderModel->manual_discount);
        $orderModel->pay_type_text = CodeConstants::getPayTypeText($orderModel->pay_type, '支付', true);
        $orderModel->pay_status_text = $orderModel->pay_type_text; // 支付情况
        if (intval($orderModel->status) == Constants::OrderStatus_NoPay) {
            $orderModel->pay_status_text = '待支付';
        } else if (intval($orderModel->status) == Constants::OrderStatus_Cancel) {
            $orderModel->pay_status_text = '未支付';
        }
        $orderModel->comment_able = false; // 是否可评论
        $productCommentConfig = Site::getCurrentSite()->getConfig()->getProductCommentConfig();
        if ($productCommentConfig['product_comment_status'] && in_array(intval($orderModel->status), [Constants::OrderStatus_OrderReceive, Constants::OrderStatus_OrderSuccess, Constants::OrderStatus_OrderFinished]) && intval($orderModel->comment_status) == Constants::OrderCommentStatus_CanComment) {
            $orderModel->comment_able = true;
        }
        unset($orderModel->snapshot);
        $orderItem = $orderModel->items;
        $productIds = $orderItem->pluck('product_id')->all();
        // 查找售后信息
        $afterSale = AfterSaleItemModel::query()
            ->whereIn('product_id', $productIds)
            ->where('tbl_after_sale_item.order_id', $orderModel->id)
            ->leftJoin('tbl_after_sale', 'tbl_after_sale.id', 'tbl_after_sale_item.after_sale_id')
            ->whereIn('tbl_after_sale.status', [
                Constants::RefundStatus_Apply,
                Constants::RefundStatus_Agree,
                Constants::RefundStatus_Shipped,
                Constants::RefundStatus_Received,
                Constants::RefundStatus_Over
            ])
            ->select(['tbl_after_sale.status', 'tbl_after_sale_item.product_id', 'tbl_after_sale.id', 'tbl_after_sale_item.order_item_id'])
            ->get();
        $afterSaleCount = $afterSale->count();
        $afterSaleData = [];
        // 格式化一下数据 方便后面使用
        if ($afterSaleCount) {
            foreach ($afterSale as $item) {
                $afterSaleData[$item['order_item_id']] = [
                    'status' => $item['status'],
                    'after_sale_id' => $item['id']
                ];
            }
        }
        //批量售后按钮显示 false 不显示 true 显示
        $orderModel->batchAfterSale = false;
        $orderModel = ActivityOrder::activityOrderInfoData($this->_order);
        foreach ($orderItem as &$pro) {
            $pro['point_money'] = moneyCent2Yuan($pro['point_money']);
            $pro['coupon_money'] = moneyCent2Yuan($pro['coupon_money']);
            $pro['price'] = moneyCent2Yuan($pro['price']);
            if ($pro['sku_names']) {
                $pro['sku_names'] = json_decode($pro['sku_names'], true);
            } else {
                $pro['sku_names'] = [];
            }
            $pro['after_sale_status'] = null;
            $pro['after_sale_id'] = 0;
            unset($pro['snapshot']);
            // 不参与售后
            if($pro['product_after_sale_setting'] == 1){
                $pro['after_sale_status'] = false;
            }
            if ($afterSaleCount) {
                // 如果当前有售后
                if (array_key_exists($pro['id'], $afterSaleData)) {
                    $pro['after_sale_status'] = $afterSaleData[$pro['id']]['status'];
                    $pro['after_sale_id'] = $afterSaleData[$pro['id']]['after_sale_id'];
                }
            }
            if ($pro['after_sale_status'] === null && $orderModel->batchAfterSale == false) {
                $orderModel->batchAfterSale = true;
            }
        }
        //供应商信息
        if($orderModel->supplier_member_id > 0){
            $supplier = SupplierModel::query()->select('name')->where('member_id',$orderModel->supplier_member_id)->first();
            $orderModel->supplier_name = $supplier->name;
        }
        return [
            'orderInfo' => $orderModel,
            'supplier' => $supplier,
            'address' => Order::getAddressText(OrderModel::find($orderModel->id)),
            'payTime' => $payTime,
            'after_sale_status_text' => Constants::getAfterSaleStatusText() // 售后状态关联表
        ];
    }

    /**
     * 关闭未付款订单
     * @param string $msg 关闭原因
     */
    public function cancelOrder($msg = '')
    {
        $this->_order->cancel($msg);
    }

    /**
     * 确认收货
     * @param $orderId
     * @return array
     */
    public function orderConfirmReceipt($orderId)
    {
        $shopOrder = ShopOrderFactory::createOrderByOrderId($orderId, false);
        $order = $shopOrder->getOrderModel();
        if (in_array($order->status, [Constants::OrderStatus_OrderSuccess, Constants::OrderStatus_OrderReceive])) {
            return makeApiResponse(501, '商家已帮您确认收货');
        }
        $result = $shopOrder->receipt();
        if ($result) {
            return makeServiceResultSuccess(trans('shop-front.common.action_ok'));
        } else {
            return makeServiceResultFail(trans('shop-front.shop.order_status_error'));
        }
    }

    public function canDelivery(int $cityId)
    {
        if (!$this->_productModel->freight_id) return true;
        $mFreight = FreightTemplateModel::find($this->_productModel->freight_id);
        if ($mFreight && $mFreight->delivery_type != 1) return true;
        $areas = json_decode($mFreight->delivery_area, true);
        foreach ($areas as $item) {
            if (strpos($item['area'], strval($cityId)) !== false) return true;
        }
        return false;
    }

    /**
     * 获取产品列表中 不配送的地址id
     * @param array $productList 产品列表
     * @param array $address 地址列表
     * @return array
     */
    public static function getNoAvailableAddressIds($productList, $address)
    {
        // 获取产品的运费模板
        $freightArr = ProductModel::query()->whereIn('id', $productList)->pluck('freight_id');
        $freightArr = $freightArr->unique()->all();
        $freightTemplate = FreightTemplateModel::query()->whereIn('id', $freightArr)->select(['delivery_area', 'delivery_type'])->get();
        $noAvailable = [];
        foreach ($freightTemplate as $temp) {
            if ($temp['delivery_type'] != 1) {
                continue;
            }
            // 获取该运费模板的所有配送区域
            $areas = json_decode($temp['delivery_area'], true);
            $areas = collect($areas)->pluck('area')->all();
            $areas = implode(',', $areas);
            // 检测所有的地址是否有不可以配送的
            foreach ($address as $item) {
                // 已经检测到不配送的 不去重复检测
                if (in_array($item['id'], $noAvailable)) {
                    continue;
                }
                if (strpos($areas, strval($item['city'])) === false) {
                    $noAvailable[] = $item['id'];
                }
            }
        }
        return $noAvailable;
    }

    /**
     * 订单列表（前台用）
     * @param array $param
     * @param int $page
     * @param int $pageSize
     * @param int $siteId
     * @return array
     */
    public function orderList($param = [], $page = 1, $pageSize = 20, $siteId = 0)
    {
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;

        $query = OrderModel::query()->where('tbl_order.member_id', $this->_memberId);
        if ($siteId > 0) {
            $query->where('tbl_order.site_id', $siteId);
        }
        // 状态
        $status = $param['status'];
        if (is_numeric($status)) {
            $status = intval($status);
            $query->where('tbl_order.status', $status);
            if ($status == 1) {
                // 处理拼团的情况
                $query->whereIn('tbl_order.type_status', [0, Constants::OrderType_GroupBuyingStatus_Yes]);
            }
        } else if ($status) {
            $statusList = myToArray($status);
            if (count($statusList) > 0) {
                $query->whereIn('tbl_order.status', $statusList);
            }
        }
        // 评价状态
        $comment_status = $param['comment_status'];
        if (is_numeric($comment_status)) {
            $query->where('comment_status', intval($comment_status));
        }
        // 数据处理
        $total = $query->count(); // 总数
        $last_page = ceil($total / $pageSize); // 总页数
        // 分页，连带orderItem
        $query->forPage($page, $pageSize);
        $query->leftJoin('tbl_supplier','tbl_supplier.member_id','=','tbl_order.supplier_member_id');
        $orderList = $query->addSelect(['tbl_order.*','tbl_supplier.name as supplier_name'])
            ->orderBy('created_at', 'desc')
            ->with('items')
            ->get();
        $orderList = ActivityOrder::activityOrderListData($orderList);
        // 评论配置
        $productCommentConfig = Site::getCurrentSite()->getConfig()->getProductCommentConfig();
        // 处理数据
        foreach ($orderList as $order) {
            $order->snapshot = null;
            $order->product_cost = moneyCent2Yuan($order->product_cost);
            $order->product_money = moneyCent2Yuan($order->product_money);
            $order->point_money = moneyCent2Yuan($order->point_money);
            $order->coupon_money = moneyCent2Yuan($order->coupon_money);
            $order->money = moneyCent2Yuan($order->money);
            $order->freight = moneyCent2Yuan($order->freight);
            $order->status_text = Constants::getOrderStatusText(intval($order->status), $order->type_status);
            $order->comment_able = false; // 是否可评论
            if ($productCommentConfig['product_comment_status'] && in_array(intval($order->status), [Constants::OrderStatus_OrderReceive, Constants::OrderStatus_OrderSuccess, Constants::OrderStatus_OrderFinished]) && intval($order->comment_status) == Constants::OrderCommentStatus_CanComment) {
                $order->comment_able = true;
            }
            // 处理明细数据
            foreach ($order->items as $orderItem) {
                $orderItem->snapshot = null;
                $orderItem->commission = null;
                $orderItem->sku_names = json_decode($orderItem->sku_names, true);
                $orderItem->point_money = moneyCent2Yuan($orderItem->point_money);
                $orderItem->coupon_money = moneyCent2Yuan($orderItem->coupon_money);
                $orderItem->price = moneyCent2Yuan($orderItem->price);
                $orderItem->cost = moneyCent2Yuan($orderItem->cost);
                $orderItem->after_sale_status = AfterSaleItemModel::query()
                    ->where('tbl_after_sale_item.order_item_id', $orderItem->id)
                    ->leftJoin('tbl_after_sale', 'tbl_after_sale.id', 'tbl_after_sale_item.after_sale_id')
                    ->whereIn('tbl_after_sale.status', [
                        Constants::RefundStatus_Apply,
                        Constants::RefundStatus_Agree,
                        Constants::RefundStatus_Shipped,
                        Constants::RefundStatus_Received,
                        Constants::RefundStatus_Over
                    ])
                    ->select(['tbl_after_sale.status'])
                    ->first()->status;

            }
        }

        return [
            'list' => $orderList,
            'total' => $total,
            'last_page' => $last_page,
            'current' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 获取订单关闭文案列表
     * @return array
     */
    public static function getOrderCancelReasonList()
    {
        return [
            Constants::OrderCancelReason_NotLike => '不喜欢/不想要',
            Constants::OrderCancelReason_BuyError => '拍错了',
            Constants::OrderCancelReason_OtherBuyType => '有其他优惠购买方式',
        ];
    }

    /**
     * 获取未评价的明细数据
     * @return array
     */
    public function getNoCommentItemList()
    {
        $orderModel = $this->_order->getOrderModel();
        $orderItem = $orderModel->items;
        $list = [];
        if (intval($orderModel->comment_status) == Constants::OrderCommentStatus_CanComment && in_array(intval($orderModel->status), [Constants::OrderStatus_OrderReceive, Constants::OrderStatus_OrderSuccess, Constants::OrderStatus_OrderFinished])) {
            foreach ($orderItem as $item) {
                if (intval($item['comment_status']) == Constants::OrderItemCommentStatus_NoComment) {
                    $item['point_money'] = moneyCent2Yuan($item['point_money']);
                    $item['coupon_money'] = moneyCent2Yuan($item['coupon_money']);
                    $item['price'] = moneyCent2Yuan($item['price']);
                    if ($item['sku_names']) {
                        $item['sku_names'] = json_decode($item['sku_names'], true);
                    } else {
                        $item['sku_names'] = [];
                    }
                    unset($item['snapshot']);
                    unset($item['cost']);
                    $list[] = $item;
                }
            }
        }
        return $list;
    }
}