<?php
/**
 * 后台提货订单业务逻辑
 * User: liyaohui
 * Date: 2019/8/24
 * Time: 17:49
 */

namespace App\Modules\ModuleShop\Libs\CloudStock;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\LogisticsModel;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Model\CloudStockSkuLogModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryOrderItemModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryOrderModel;
use App\Modules\ModuleShop\Libs\Order\Logistics;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use Illuminate\Foundation\Bus\DispatchesJobs;

class AdminTakeDeliveryOrder
{
    use DispatchesJobs;
    protected $_siteId = 0;

    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
    }

    /**
     * 获取列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getList($params = [], int $page = 1, int $pageSize = 20)
    {
        $showAll = $params['show_all'] || ($params['ids'] && strlen($params['ids'] > 0)) ? true : false; // 是否显示所有，导出功能用，默认False

        $query = CloudStockTakeDeliveryOrderModel::query()->from('tbl_cloudstock_take_delivery_order as order')
            ->where('order.site_id', $this->_siteId)
            ->leftJoin('tbl_member as m', 'm.id', 'order.member_id');
        // 状态
        if (isset($params['status']) && is_numeric($params['status'])) {
            $query->where('order.status', $params['status']);
        }
        // 关键词搜索
        if (isset($params['keyword']) && trim($params['keyword']) !== '') {
            $keyword = '%' . $params['keyword'] . '%';
            $keywordStr = $params['keyword'];
            $query->where(function ($q) use ($keyword, $keywordStr) {
                $q->where('m.nickname', 'like', $keyword);
                $q->orWhere('m.name', 'like', $keyword);
                if (preg_match('/^\w+$/i', $keywordStr)) {
                    $q->orWhere('order.id', 'like', $keyword)
                        ->orWhere('m.mobile', 'like', $keyword);
                }
            });
        }
        // 提货时间
        if (isset($params['created_at_start']) && trim($params['created_at_start']) !== '') {
            $query->where('order.created_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end']) && trim($params['created_at_end']) !== '') {
            $query->where('order.created_at', '<=', $params['created_at_end']);
        }
        // ids 用于导出
        if ($params['ids']) {
            $ids = myToArray($params['ids']);
            if (count($ids) > 0) {
                $query->whereIn('order.id', $ids);
            }
        }

        $total = $query->count();
        $lasePage = ceil($total / $pageSize);
        $page = $page < 1 ? 1 : $page;
        $page = $page > $lasePage ? $lasePage : $page;
        if ($total > 0 && $showAll) {
            $page = 1;
            $pageSize = $total;
        }
        $list = $query->orderByDesc('order.created_at')
            ->forPage($page, $pageSize)
            ->select([
                'order.id as order_id',
                'm.mobile',
                'm.nickname',
                'm.headurl',
                'm.name',
                'order.member_id',
                'order.created_at',
                'order.status',
                'order.product_num',
                'order.country',
                'order.prov',
                'order.city',
                'order.area',
                'order.receiver_address',
                'order.receiver_name',
                'order.receiver_tel',
                'order.remark',
                'order.virtual_flag'
            ])
            ->get();
        $list = $this->formatOrderList($list);
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lasePage,
            'list' => $list
        ];
    }

    /**
     * 获取提货订单详情
     * @param $orderId
     * @return array
     * @throws \Exception
     */
    public function getOrderInfo($orderId)
    {
        if (!$orderId) {
            throw new \Exception('订单ID错误');
        }
        $order = CloudStockTakeDeliveryOrderModel::query()
            ->from('tbl_cloudstock_take_delivery_order as order')
            ->leftJoin('tbl_member as m', 'order.member_id', 'm.id')
            ->where('order.site_id', $this->_siteId)
            ->where('order.id', $orderId)
            ->select(['order.*', 'm.nickname', 'm.mobile', 'm.name', 'm.headurl'])
            ->first();
        if ($order) {
            $order->status_text = self::getOrderStatusText($order->status);
            $order->cancel_message = Constants::getCloudStockOrderCancelReasonText($order->cancel_message);
            $order->freight = moneyCent2Yuan($order->freight);
            // 查找订单中商品列表
            $productList = CloudStockTakeDeliveryOrderItemModel::query()
                ->from('tbl_cloudstock_take_delivery_order_item as item')
                ->leftJoin('tbl_logistics as logistics', 'item.logistics_id', 'logistics.id')
                ->where('item.order_id', $orderId)
                ->where('item.site_id', $this->_siteId)
                ->select([
                    'item.name as product_name',
                    'item.image',
                    'item.sku_names',
                    'item.sku_id',
                    'item.product_id',
                    'item.num',
                    'item.delivery_status',
                    'item.logistics_id',
                    'logistics.logistics_company',
                    'logistics.logistics_name',
                    'logistics.logistics_no',
                    'item.id',
                    'item.is_virtual'
                ])
                ->get();
            return [
                'order_info' => $order,
                'product_list' => $this->formatOrderProductList($productList, $order->status)
            ];
        } else {
            throw new \Exception('订单不存在');
        }
    }

    /**
     * 订单发货
     * @param string $orderId 订单id
     * @param array $itemIds 要发货的item id数组 为空则整个订单发货
     * @param array $delivery 物流信息
     * @return bool
     * @throws \Exception
     */
    public function deliver($orderId, $itemIds = [], $delivery)
    {
        try {
            DB::beginTransaction();
            $orderModel = $this->getOrderModel($orderId);
            // 待发货状态才能发货
            if ($orderModel->status != Constants::CloudStockTakeDeliveryOrderStatus_NoDeliver) {
                throw new \Exception('订单已发货');
            }
            if ($itemIds && is_numeric($itemIds)) {
                $itemIds = [$itemIds];
            }
            $orderItemsQuery = CloudStockTakeDeliveryOrderItemModel::query()
                ->where('site_id', $this->_siteId)
                ->where('order_id', $orderId)
                ->where('delivery_status', Constants::CloudStockTakeDeliveryOrderItemDeliveryStatus_No);
            if ($itemIds) {
                $orderItemsQuery->whereIn('id', $itemIds);
            }
            $orderItems = $orderItemsQuery->get();
            $noDeliver = $orderItems->count();
            if ($noDeliver) {
                // 生成物流数据
                $delivery['site_id'] = $this->_siteId;
                $delivery['member_id'] = $orderModel->member_id;
                $delivery['order_id'] = $orderModel->id;
                $delivery['type'] = Constants::LogisticsType_CloudStockTake;
                $delivery['logistics_company'] = trim($delivery['logistics_company']);
                $delivery['logistics_no'] = trim($delivery['logistics_no']);
                if ($delivery['logistics_company']) {
                    $delivery['logistics_name'] = Constants::getExpressCompanyText(intval($delivery['logistics_company']));
                } else {
                    $delivery['logistics_name'] = trim($delivery['logistics_name']);
                }
                // 是否是虚拟商品发货,因为现在虚拟商品是单独发货，所以只要检测其中一个就可以
                $is_virtual = 0;
                foreach ($orderItems as $item) {
                    if ($item->is_virtual == 1) {
                        $is_virtual = 1;
                        break;
                    }
                }
                if ($is_virtual == 0 && (!is_numeric($delivery['logistics_company']) || empty($delivery['logistics_no']) || empty($delivery['logistics_name']))) {
                    throw new \Exception('请输入正确的物流信息');
                }
                $logistics = new Logistics($this->_siteId);
                $logisticsID = $logistics->add($delivery);
                if (!$logisticsID) return false;
                // 物流数据更新到item表
                // 更新item物流状态为已发货
                $orderItemsQuery->update([
                    'logistics_id' => $logisticsID,
                    'delivery_status' => Constants::CloudStockTakeDeliveryOrderItemDeliveryStatus_Yes
                ]);
                // 检测订单是否全部都已经发货
                $items = CloudStockTakeDeliveryOrderItemModel::query()
                    ->where('site_id', $this->_siteId)
                    ->where('order_id', $orderId)
                    ->select(['delivery_status'])
                    ->get();

                $deliveryNoCount = $items
                    ->where('delivery_status', Constants::CloudStockTakeDeliveryOrderItemDeliveryStatus_No)
                    ->count();
                // 如果还有未发货的 说明是部分发货 否则是全部发货
                if ($deliveryNoCount) {
                    $orderModel->delivery_status = Constants::CloudStockTakeDeliveryOrderDeliverStatus_PartYes;
                    $orderModel->status = Constants::CloudStockTakeDeliveryOrderStatus_NoDeliver;
                } else {
                    $orderModel->delivery_status = Constants::CloudStockTakeDeliveryOrderDeliverStatus_AllYes;
                    $orderModel->status = Constants::CloudStockTakeDeliveryOrderStatus_Delivered;
                    $orderModel->send_at = Carbon::now();
                    // 如果本次是发了所有的货 则把物流id更新到order表
                    if ($noDeliver == $items->count() && !$deliveryNoCount) {
                        $orderModel->logistics_id = $logisticsID;
                    }
                }
                $save = $orderModel->save();
//                $this->deliverAfter($orderId, $itemIds); // 暂时不需要了
                DB::commit();
                // 发货通知
                $logistics = LogisticsModel::find($logisticsID);
                if ($logistics) {
                    $this->dispatch(new MessageNotice(CoreConstants::MessageType_Order_Send, $logistics));
                }
                return $save;
            } else {
                throw new \Exception('没有可发货的商品');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 发货后的逻辑 暂时不需要
     * @param string $orderId
     * @param array $itemIds
     * @return int
     */
    public function deliverAfter($orderId, $itemIds = [])
    {
        // 把log状态改为生效
        $query = CloudStockSkuLogModel::query()
            ->where('site_id', $this->_siteId)
            ->where('order_id', $orderId)
            ->where('status', 0);
        if ($itemIds) {
            $query->whereIn('order_item_id', $itemIds);
        }
        $update = $query->update(['status' => 1]);
        return $update;
    }

    /**
     * 获取
     * @param string $orderId
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getOrderModel($orderId)
    {
        if (!$orderId) {
            throw new \Exception('订单ID错误');
        }
        $orderModel = CloudStockTakeDeliveryOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('id', $orderId)
            ->first();
        if ($orderModel) return $orderModel;
        else throw new \Exception('订单不存在');
    }

    /**
     * 编辑内部备注
     * @param string $orderId
     * @param string $text
     * @return bool
     * @throws \Exception
     */
    public function editRemarkInside($orderId, $text = '')
    {
        $order = $this->getOrderModel($orderId);
        $order->remark_inside = $text;
        return $order->save();
    }

    /**
     * 修改物流信息
     * @param $orderId
     * @param $logisticsId
     * @param $params
     * @param $is_virtual
     * @return array|bool
     * @throws \Exception
     */
    public function editLogistics($orderId, $logisticsId, $params,$is_virtual)
    {
        // 检查数据
        $id = intval($logisticsId);
        if ($id <= 0) {
            throw new \Exception(trans('shop-admin.common.data_error'));
        }
        $logistics = LogisticsModel::query()->where('site_id', $this->_siteId)
            ->where('id', $logisticsId)
            ->where('order_id', $orderId)
            ->first();
        if (!$logistics) {
            throw new \Exception('找不到物流信息');
        }
        $old_logistics_company = $logistics->logistics_company;
        $old_logistics_no = $logistics->logistics_no;
        $old_logistics_name = $logistics->logistics_name;
        $logistics->logistics_company = trim($params['logistics_company']);
        $logistics->logistics_no = trim($params['logistics_no']);
        if ($logistics->logistics_company == Constants::ExpressCompanyCode_Other) {
            $logistics->logistics_name = trim($params['logistics_name']);
        } else {
            $logistics->logistics_name = Constants::getExpressCompanyText(intval($logistics->logistics_company));
        }

        if ((empty($logistics->logistics_no) || empty($logistics->logistics_name)) && $is_virtual == 0) {
            throw new \Exception('请输入正确的物流信息');
        }
        if ($logistics->logistics_company != $old_logistics_company || $logistics->logistics_name != $old_logistics_name || $logistics->logistics_no != $old_logistics_no) {
            $this->dispatch(new MessageNotice(CoreConstants::MessageType_Order_Send, $logistics));
        }

        $logistics->updated_at = Carbon::now();
        return $logistics->save();
    }

    /**
     * 格式化订单商品列表数据
     * @param $list
     * @param $orderStatus
     * @return array
     */
    public function formatOrderProductList($list, $orderStatus)
    {
        if (!$list) return [];
        // 根据发货状态 排序
        $list = $list->sortBy('logistics_id')->values()->all();
        foreach ($list as &$item) {
            $item['delivery_status_text'] = in_array($orderStatus, [Constants::CloudStockTakeDeliveryOrderStatus_Nopay]) ? '--' : self::getOrderItemStatusText(intval($item['delivery_status']), $orderStatus);
            $item['sku_names'] = $item['sku_names'] ? json_decode($item['sku_names'], true) : [];
        }
        return $list;
    }

    /**
     * 获取订单商品状态
     * @param int $itemStatus 商品状态
     * @param int|null $orderStatus 订单状态
     * @return string
     */
    public static function getOrderItemStatusText(int $itemStatus, int $orderStatus = null)
    {
        if ($orderStatus == Constants::CloudStockTakeDeliveryOrderStatus_Finished) {
            return '已完成';
        } else if ($orderStatus == Constants::CloudStockTakeDeliveryOrderStatus_Cancel) {
            return '订单取消';
        }
        switch ($itemStatus) {
            case Constants::CloudStockTakeDeliveryOrderItemDeliveryStatus_No:
                return '待发货';
            case Constants::CloudStockTakeDeliveryOrderItemDeliveryStatus_Yes:
                return '待收货';
            default:
                return '未知';
        }
    }

    /**
     * 格式化列表数据
     * @param $list
     * @return array
     */
    public function formatOrderList($list)
    {
        if (!$list) return [];
        foreach ($list as &$item) {
            $item['status_text'] = self::getOrderStatusText(intval($item['status']));
        }
        return $list;
    }

    /**
     * 获取提货订单状态文案
     * @param int $status
     * @return string
     */
    public static function getOrderStatusText(int $status)
    {
        switch ($status) {
            case Constants::CloudStockTakeDeliveryOrderStatus_NoDeliver:
                return '待发货';
            case Constants::CloudStockTakeDeliveryOrderStatus_Delivered:
                return '待收货';
            case Constants::CloudStockTakeDeliveryOrderStatus_Finished:
                return '已完成';
            case Constants::CloudStockTakeDeliveryOrderStatus_Nopay:
                return '待付款';
            case Constants::CloudStockTakeDeliveryOrderStatus_Cancel:
                return '订单取消';
            default:
                return '未知';
        }
    }
}