<?php

namespace App\Modules\ModuleShop\Libs\Shop;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Supplier\SupplierGroupBuyingShopOrder;
use App\Modules\ModuleShop\Libs\Supplier\SupplierShopOrder;

/**
 * 购物订单的工场类，一般用在不同场景下返回不同类型的订单
 * Class ShopOrderFactory
 * @package App\Modules\ModuleShop\Libs\Shop
 */
class ShopOrderFactory
{
    /**
     * 根据订单id创建订单
     * @param string|OrderModel $orderIdOrModel
     * @param bool $initProduct 是否初始化商品信息
     * @return IShopOrder
     * @throws \Exception
     */
    public static function createOrderByOrderId($orderIdOrModel, $initProduct = true): IShopOrder
    {
        // 根据id去查找订单
        if($orderIdOrModel instanceof OrderModel){
            $order = $orderIdOrModel;
        }else {
            $order = OrderModel::query()
                ->where('site_id', getCurrentSiteId())
                ->where('id', $orderIdOrModel)
                ->first();
        }
        if (!$order) {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
        $type = $order->type;
        if ($type == Constants::OrderType_Normal && !$order->supplier_member_id) {
            $shopOrder = new NormalShopOrder();
            $shopOrder->initByOrderId($order, $initProduct);
        } elseif ($type == Constants::OrderType_Normal && $order->supplier_member_id) {
            $shopOrder = new SupplierShopOrder();
            $shopOrder->initByOrderId($order, $initProduct);
        } elseif ($type == Constants::OrderType_GroupBuying && !$order->supplier_member_id) {
            $shopOrder = new GroupBuyingShopOrder();
            $shopOrder->initByOrderId($order, $initProduct);
        } elseif ($type == Constants::OrderType_GroupBuying && $order->supplier_member_id) {
            $shopOrder = new SupplierGroupBuyingShopOrder();
            $shopOrder->initByOrderId($order, $initProduct);
        } else {
            throw new \Exception('order type error');
        }
        return $shopOrder;
    }

    /**
     * 根据类型创建新的订单
     * @param $memberId
     * @param int $type
     * @param array $params
     * @return IShopOrder
     * @throws \Exception
     */
    public static function createOrderByType($memberId, $type = Constants::OrderType_Normal, $params = [], $isSupplier = false): IShopOrder
    {
        if ($type == Constants::OrderType_Normal && !$isSupplier) {
            $order = new NormalShopOrder($memberId);
        } elseif ($type == Constants::OrderType_Normal && $isSupplier) {
            $order = new SupplierShopOrder($memberId);
        } elseif ($type == Constants::OrderType_GroupBuying && !$isSupplier) {
            $order = new GroupBuyingShopOrder($memberId, $params);
        } elseif ($type == Constants::OrderType_GroupBuying && $isSupplier) {
            $order = new SupplierGroupBuyingShopOrder($memberId, $params);
        } else {
            throw new \Exception('order type error');
        }
        return $order;
    }
}