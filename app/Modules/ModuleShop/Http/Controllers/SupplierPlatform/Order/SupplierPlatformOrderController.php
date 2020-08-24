<?php

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Order;

use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController as BaseController;
use App\Modules\ModuleShop\Libs\Order\AfterSale;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Order\Order;
use App\Modules\ModuleShop\Libs\Constants;


/**
 * 订单 Controller
 * Class OrderController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Order
 */
class SupplierPlatformOrderController extends BaseController
{
    /**
     * 订单列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $order = new Order(Site::getCurrentSite()->getSiteId());
            $param['after_sale_detail'] = true;
            $param['show_dress'] = true;
            // 待发货的状态，只能出现拼团成功的  供应商只出现拼团成功的订单
//            if ($param['status'] == Constants::OrderStatus_OrderPay && !isset($param['type_status']) && !isset($param['activity_id'])) {
//                $param['type_status'] = [0,Constants::OrderType_GroupBuyingStatus_Yes];
//            }
            $param['type_status'] = [0,Constants::OrderType_GroupBuyingStatus_Yes];
            $param['supplier_member_id'] = $this->memberId;
            if(!isset($param['status'])){
                $param['status'] = Constants::getPaymentOrderStatus();
            }
            $data = $order->getList($param);
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $this->convertOutputOrder($item, true);
                }
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取订单详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $param = $request->all();
            $orderId = $param['id'];
            if ($orderId) {
                $order = new Order(Site::getCurrentSite()->getSiteId());
                $model = $order->getInfo($orderId);
                // 要验证订单是否属于此供应商
                if ($model && $model->supplier_member_id == $this->memberId) {
                    $this->convertOutputOrder($model, true);
                    // 获取订单取消原因
                    if ($model['status'] == Constants::OrderStatus_Cancel) {
                        $model['cancel_reason_text'] = Order::getOrderCancelReasonText($model['cancel_message']);
                    } else {
                        $model['cancel_reason_text'] = '';
                    }
                    return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $model->toArray());
                } else {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
            } else {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改订单信息
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            $orderId = $request->get('id');
            if ($orderId) {
                $order = Order::find($orderId);
                if ($order && $order->checkExist()) {
                    // 验证订单是否属于此供应商
                    if($order->getModel()->supplier_member_id != $this->memberId){
                        return makeApiResponseFail(trans('shop-admin.common.action_fail'));
                    }
                    $params = [];
                    if ($request->has('remark_inside')) {
                        $params['remark_inside'] = $request->get('remark_inside');
                    }
                    if (count($params) > 0) {
                        $order->edit($params);
                    }
                    return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
                } else {
                    return makeApiResponseFail(trans('shop-admin.common.data_error'));
                }
            } else {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 导出
     * @param Request $request
     * @return array
     */
    public function export(Request $request)
    {
        try {
            $param = $request->all();
            $param['output_text'] = true;
            $param['after_sale_detail'] = true;
            $param['show_dress'] = true;
            $param['supplier_member_id'] = $this->memberId;
            if(!isset($param['status'])){
                $param['status'] = Constants::getPaymentOrderStatus();
            }
            $order = new Order(Site::getCurrentSite()->getSiteId());
            $data = $order->getList($param);
            $exportHeadings = [
                '总订单号', // A
                '下单时间', // B
                '终端来源', // C
                '买家ID',// D 需合并
                '买家昵称',// E 需合并
                '买家姓名',// F 需合并
                '买家手机号',// G 需合并
                '总订单状态',// H 需合并
                '实付订单金额',// I 需合并
                '总运费',// J 需合并
                '商品总价（优惠后）',// K 需合并
                '商品名称',// L
                '商品编号', // M
                '规格',// N
                '供货单价',// O
                '单价（优惠后）',// P
                '数量',// Q
                '商品状态',// R
                '售后状态',// S
                '退款数量',// T
                '订单类型',// U 需合并
                '买家订单备注',// V 需合并
                '收货人姓名',// W 需合并
                '收货人电话',// X 需合并
                '收货地址',// Y 需合并
                '物流公司',// Z
                '物流单号'// AA
            ];
            $exportData = [];
            $row_num = 2;
            $merge = [];
            if ($data['list']) {
                // 先处理一些数据
                foreach ($data['list'] as $item) {
                    $this->convertOutputOrder($item, true);
                }
                // 构造导出格式
                foreach ($data['list'] as $order) {
                    $item_list_length = count($order['item_list']);
                    $merge_false = false;//是否需要记录合并单元格的标识
                    foreach ($order->item_list as $item) {
                        $exportData[] = [
                            "\t" . $order->id . "\t",//如果不加\t excel表中订单号尾数会变为零，原因是excel默认的科学计数法导致
                            $order->created_at,
                            $order->terminal_type_text,
                            $order->member_id,
                            $order->member_nickname,
                            $order->member_name,
                            "\t" . $order->member_mobile . "\t",
                            Constants::getOrderStatusTextForAdmin($order->status),
                            $order->money,
                            $order->freight,
                            $order->after_preferential_money,
                            $item->name,
                            $item->serial_number,
                            implode(',', $item->sku_names),
                            $item->supplier_price,
                            $item->price,
                            $item->num,
                            $item->status_text,
                            $item->after_sale_status_text, //售后状态
                            $item->after_sale_over_num, //实际退款
                            $order->type_text, //订单类型
                            $order->remark,
                            $order->receiver_name,
                            "\t" . $order->receiver_tel . "\t",
                            $order->prov . $order->city . $order->area . $order->receiver_address,
                            $item->logistics_name,
                            "\t" . $item->logistics_no . "\t",
                        ];
                        //合并单元格
                        if ($merge_false == false) {
                            if ($item_list_length > 1) {
                                //一次循环，只需记录一次合并的参数
                                $merge_false = true;
                                $start_row_num = $row_num;
                                $end_row_num = $row_num + $item_list_length - 1;
                                $merge = array_merge($merge, ['A' . $start_row_num . ':' . 'A' . $end_row_num, 'B' . $start_row_num . ':' . 'B' . $end_row_num, 'C' . $start_row_num . ':' . 'C' . $end_row_num, 'D' . $start_row_num . ':' . 'D' . $end_row_num, 'E' . $start_row_num . ':' . 'E' . $end_row_num, 'F' . $start_row_num . ':' . 'F' . $end_row_num, 'G' . $start_row_num . ':' . 'G' . $end_row_num, 'H' . $start_row_num . ':' . 'H' . $end_row_num, 'I' . $start_row_num . ':' . 'I' . $end_row_num, 'J' . $start_row_num . ':' . 'J' . $end_row_num,'K' . $start_row_num . ':' . 'K' . $end_row_num, 'U' . $start_row_num . ':' . 'U' . $end_row_num, 'V' . $start_row_num . ':' . 'V' . $end_row_num, 'W' . $start_row_num . ':' . 'W' . $end_row_num,'X' . $start_row_num . ':' . 'X' . $end_row_num,'Y' . $start_row_num . ':' . 'Y' . $end_row_num]);
                            }
                            $row_num = $row_num + $item_list_length;
                        }
                    }
                }
            }
            // 导出
            $exportObj = new Export(new Collection($exportData), 'DingDan-' . date("YmdHis") . '.xlsx', $exportHeadings);
            $exportObj->setMerge($merge);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 发货
     * @param Request $request
     * @return array
     */
    public function deliver(Request $request)
    {
        try {
            $param = $request->all();
            $orderId = $param['id']; // 订单id
            $items = $param['items'];
            $logistics_company = trim($param['logistics_company']);
            $logistics_no = trim($param['logistics_no']);
            $logistics_name = Constants::getExpressCompanyText(intval($logistics_company));
            if ($logistics_company == Constants::ExpressCompanyCode_Other) {
                $logistics_name = trim($param['logistics_name']);
            }

            if (empty($orderId) || !is_numeric($logistics_company) || empty($logistics_name)) {
                return makeApiResponseFail('数据异常');
            }

            if (empty($items)) {
                $items = [];
            } else if (!is_array($items)) {
                $items = explode(',', $items);
            }

            $order = Order::find($orderId, Site::getCurrentSite()->getSiteId());
            // 验证订单是否属于此供应商
            if($order->getModel()->supplier_member_id != $this->memberId){
                return makeApiResponseFail(trans('shop-admin.common.action_fail'));
            }
            $result = $order->deliver([
                'logistics_company' => $logistics_company,
                'logistics_name' => $logistics_name,
                'logistics_no' => $logistics_no
            ], $items);

            if ($result) {
                return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
            } else {
                return makeApiResponseFail(trans('shop-admin.common.action_fail'));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 订单数据输出转换
     * @param $data
     * @param bool $convertItem 是否要处理订单产品数据
     * @return mixed
     */
    private function convertOutputOrder($data, $convertItem = false)
    {
        if ($data) {

            $columns = ['product_cost', 'product_money', 'freight', 'point_money', 'coupon_money', 'money', 'sub_total', 'after_preferential_money'];

            if ($data['money']) {
                if ($data['freight']) {
                    $data['sub_total'] = intval($data['money']) - intval($data['freight']);
                } else {
                    $data['sub_total'] = intval($data['money']);
                }
            }
            if ($data['product_money']) {
                $data['after_preferential_money'] = $data['product_money'] - $data['point_money'] - $data['coupon_money'];
            }
            foreach ($columns as $column) {
                if (is_null($data[$column])) $data[$column] = 0;
                $data[$column] = moneyCent2Yuan($data[$column]);
            }
            $data['snapshot'] = null;
            if ($convertItem && $data['item_list']) {
                foreach ($data['item_list'] as &$item) {
                    $item = $this->convertOutputOrderItem($item);
                }
                unset($item);
            }
            // 供应商结算
            if($data['supplier_settle']){
                $data['supplier_settle']['money'] = moneyCent2Yuan($data['supplier_settle']['money']);
                $data['supplier_settle']['freight'] = moneyCent2Yuan($data['supplier_settle']['freight']);
                $data['supplier_settle']['after_sale_money'] = moneyCent2Yuan($data['supplier_settle']['after_sale_money']);
                $data['supplier_settle']['after_sale_freight'] = moneyCent2Yuan($data['supplier_settle']['after_sale_freight']);
            }
            // 按物流情况排序
            foreach ($data['item_list'] as &$orderItem) {
                // 计算是否能发货
                $orderItem->can_deliver = false;
                if (in_array(intval($data['status']), [Constants::OrderStatus_OrderPay, Constants::OrderStatus_OrderSend]) && !$orderItem->delivery_status && intval($orderItem->num) > intval($orderItem->after_sale_num) + intval($orderItem->after_sale_over_num)) {
                    $orderItem->can_deliver = true;
                }
                // 物流分组
                $logisticsId = intval($orderItem->logistics_id);
                $groupKey = $logisticsId . '_' . ($orderItem->can_deliver ? '1' : '0');
                $orderItem['status'] = $data['status'];
                if (in_array(intval($data->status), [Constants::OrderStatus_OrderSend, Constants::OrderStatus_OrderPay])) {
                    if ($logisticsId > 0) {
                        $orderItem['status'] = $orderItem->can_deliver ? Constants::OrderStatus_OrderPay : Constants::OrderStatus_OrderSend;
                    }
                }
                $orderItem['group_key'] = $groupKey;
                $orderItem['logistics_name'] = $orderItem->logistics_name;
                $orderItem['logistics_no'] = $orderItem->logistics_no;
                $orderItem['status_text'] = Constants::getOrderStatusTextForAdmin($orderItem['status'], $data['type_status']);
                $orderItem['type_status'] = $data['type_status'];
                $orderItem['supplier_price'] = moneyCent2Yuan($orderItem['supplier_price']);
                $orderItem['after_sale_money'] = moneyCent2Yuan($orderItem['after_sale_money']);
                $orderItem['real_refund_money'] = moneyCent2Yuan($orderItem['real_refund_money']);
                $orderItem['total_money'] = moneyCent2Yuan($orderItem['total_money']);
                $orderItem['real_price'] = moneyCent2Yuan($orderItem['real_price']);
                $orderItem['profit'] = moneyCent2Yuan($orderItem['profit']);

                // 如果有售后，则显示售后状态
                if (is_numeric($orderItem['after_sale_status'])) {
                    $afterSaleStatus = intval($orderItem['after_sale_status']);
                    if (!in_array($afterSaleStatus, [Constants::RefundStatus_Cancel, Constants::RefundStatus_Reject])) {
                        $afterSale = new AfterSale();
                        $afterSaleStatusEx = $afterSale->getAfterSaleStatus($afterSaleStatus, intval($orderItem['after_sale_type']));
                        $orderItem['after_sale_status_text'] = Constants::getFrontAfterSaleStatusText($afterSaleStatusEx);
                    }
                }
            }
            unset($orderItem);
            $data['item_list'] = $data['item_list']->sortBy('group_key')->values();
        }
        return $data;
    }

    /**
     * 订单数据输出转换
     * @param $data
     * @return mixed
     */
    private function convertOutputOrderItem($data)
    {
        if ($data) {
            $columns = ['cost', 'price', 'freight', 'point_money', 'coupon_money', 'discount', 'sub_total'];
            foreach ($columns as $column) {
                if (is_null($data[$column])) $data[$column] = 0;
                $data[$column] = moneyCent2Yuan($data[$column]);
            }
            if ($data['snapshot']) {
                $data['snapshot'] = null;
            }
            if ($data['sku_names']) {
                $sku_names = json_decode($data['sku_names'], true);
                if (!is_array($sku_names)) {
                    $sku_names = [$sku_names . ''];
                }
                $data['sku_names'] = $sku_names;
            }
        }
        return $data;
    }
}