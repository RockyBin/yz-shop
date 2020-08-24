<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Order;

use App\Modules\ModuleShop\Libs\CloudStock\FrontTakeDeliveryOrder;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryOrderModel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Modules\ModuleShop\Libs\Order\Logistics;
use App\Modules\ModuleShop\Libs\Order\Order;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;


/**
 * 物流信息
 * Class LogisticsController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Order
 */
class LogisticsController extends BaseController
{
    /**
     * 单个物流信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            // 检查数据
            $id = intval($request->id);
            if ($id <= 0) {
                return makeApiResponseFail(trans('shop-front.common.data_error'));
            }
            $logistics = Logistics::find($id, $this->siteId);
            // 检查是否当前用户
            $model = $logistics->getModel();
            if (!$logistics->checkExist() || $model->member_id != $this->memberId) {
                return makeApiResponseFail(trans('shop-front.common.data_error'));
            }
            // 第三方查询地址
            $logisticsPage = $logistics->getSearchPage();
            $model->search_url = $logisticsPage['url'];
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $model);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 根据订单查找拆单物流列表
     * @param Request $request
     * @return array
     */
    public function getListByOrder(Request $request)
    {
        try {
            $orderId = $request->order_id;
            if (empty($orderId)) {
                return makeApiResponseFail(trans('shop-front.common.data_error'));
            }
            // 检查订单所属
            $order = Order::find($orderId, $this->siteId);
            if (!$order->checkExist() || $order->getModel()->member_id != $this->memberId) {
                return makeApiResponseFail(trans('shop-front.common.data_error'));
            }
            $orderItemList = $order->getItems();
            // 获取所有物流信息
            $logistics = New Logistics($this->siteId);
            $logisticsData = $logistics->getList([
                'member_id' => $this->memberId,
                'order_id' => $orderId,
                'page' => $request->page,
                'page_size' => $request->page_size
                // 'show_all' => true
            ]);
            $logisticsList = $logisticsData['list'];
            foreach ($logisticsList as $logisticsItem) {
                $logisticsId = $logisticsItem->id;
                $logisticsItem->products = new Collection();
                foreach ($orderItemList as $orderItem) {
                    if ($orderItem['logistics_id'] == $logisticsId) {
                        $logisticsItem->products->push([
                            "product_id" => $orderItem->product_id,
                            "sku_id" => $orderItem->sku_id,
                            "name" => $orderItem->name,
                            "image" => $orderItem->image,
                            "sku_names" => json_decode($orderItem->sku_names, true),
                            "num" => $orderItem->num,
                            "is_virtual" => $orderItem->is_virtual
                        ]);
                    }
                }
            }

            // 查找未发货的商品
            $noDeliverProductList = [];
            foreach ($orderItemList as $orderItem) {
                // 全部退款的商品不出现在未发货的商品中
                if (!$orderItem['delivery_status'] && ($orderItem['after_sale_num']+$orderItem['after_sale_over_num']) < $orderItem['num']) {
                    $noDeliverProductList[] = [
                        "product_id" => $orderItem->product_id,
                        "sku_id" => $orderItem->sku_id,
                        "name" => $orderItem->name,
                        "image" => $orderItem->image,
                        "sku_names" => json_decode($orderItem->sku_names, true),
                        "num" => $orderItem->num
                    ];
                }
            }

            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), [
                'list' => $logisticsList,
                'no_deliver_goods' => $noDeliverProductList,
                'total' => $logisticsData['total'],
                'last_page' => $logisticsData['last_page'],
                'current' => $logisticsData['current'],
                'page_size' => $logisticsData['page_size'],
            ]);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 根据订单查找拆单物流列表
     * @param Request $request
     * @return array
     */
    public function getListByCloudStockTakeDeliveryOrder(Request $request)
    {
        try {
            $orderId = $request->order_id;
            if (empty($orderId)) {
                return makeApiResponseFail(trans('shop-front.common.data_error'));
            }
            // 检查订单所属
            $order = FrontTakeDeliveryOrder::find($orderId, $this->siteId);
            $orderItemList = $order->getOrderItemList();
            if (!$orderItemList) {
                return makeApiResponseFail(trans('shop-front.common.data_error'));
            }
            // 获取所有物流信息
            $logistics = New Logistics($this->siteId);
            $logisticsData = $logistics->getList([
                'member_id' => $this->memberId,
                'order_id' => $orderId,
                'page' => $request->page,
                'page_size' => $request->page_size
                // 'show_all' => true
            ]);
            $logisticsList = $logisticsData['list'];
            foreach ($logisticsList as $logisticsItem) {
                $logisticsId = $logisticsItem->id;
                $logisticsItem->products = new Collection();
                foreach ($orderItemList as $orderItem) {
                    if ($orderItem['logistics_id'] == $logisticsId) {
                        $logisticsItem->products->push([
                            "product_id" => $orderItem->product_id,
                            "sku_id" => $orderItem->sku_id,
                            "name" => $orderItem->name,
                            "image" => $orderItem->image,
                            "sku_names" => json_decode($orderItem->sku_names, true),
                            "num" => $orderItem->num,
                            "is_virtual" => $orderItem->is_virtual
                        ]);
                    }
                }
            }

            // 查找未发货的商品
            $noDeliverProductList = [];
            foreach ($orderItemList as $orderItem) {
                if (!$orderItem['delivery_status']) {
                    $noDeliverProductList[] = [
                        "product_id" => $orderItem->product_id,
                        "sku_id" => $orderItem->sku_id,
                        "name" => $orderItem->name,
                        "image" => $orderItem->image,
                        "sku_names" => json_decode($orderItem->sku_names, true),
                        "num" => $orderItem->num,
                        "is_virtual" => $orderItem->is_virtual
                    ];
                }
            }

            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), [
                'list' => $logisticsList,
                'no_deliver_goods' => $noDeliverProductList,
                'total' => $logisticsData['total'],
                'last_page' => $logisticsData['last_page'],
                'current' => $logisticsData['current'],
                'page_size' => $logisticsData['page_size'],
            ]);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}