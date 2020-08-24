<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Order;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Order\AfterSale;
use App\Modules\ModuleShop\Libs\Constants;
use YZ\Core\Constants as CoreConstants;

/**
 * 订单 Controller
 * Class OrderController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Order
 */
class AfterSaleController extends BaseAdminController
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
            $afterSale = new AfterSale(0);

            //1 申请退款  2 申请退货 3 等待买家退货 4 等待卖家收货 5 退款成功 6 待审核 7 审核不通过 8 退款关闭
            if ($param['after_sale_status'] == 1) {
                $param['status'] = Constants::RefundStatus_Apply;
                $param['type'] = Constants::AfterSaleType_Refund;
            } else if ($param['after_sale_status'] == 2) {
                $param['status'] = Constants::RefundStatus_Apply;
                $param['type'] = Constants::AfterSaleType_ReturnProduct;
            } else if ($param['after_sale_status'] == 3) {
                $param['status'] = Constants::RefundStatus_Agree ;
                $param['type'] = Constants::AfterSaleType_ReturnProduct;
            } else if ($param['after_sale_status'] == 4) {
                $param['status'] = Constants::RefundStatus_Shipped;
                $param['type'] = Constants::AfterSaleType_ReturnProduct;
            } else if ($param['after_sale_status'] == 5) {
                $param['status'] = Constants::RefundStatus_Over;
            } else if ($param['after_sale_status'] == 6) {
                $param['status'] = Constants::RefundStatus_Received;
                $param['type'] = Constants::AfterSaleType_ReturnProduct;
            } else if ($param['after_sale_status'] == 7) {
                $param['status'] = Constants::RefundStatus_Reject;
            } else if ($param['after_sale_status'] == 8) {
                $param['status'] = Constants::RefundStatus_Cancel;
            }

            $data = $afterSale->getList($param);

            return makeApiResponseSuccess('ok', $data);

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
            $id = $param['id'];
            if ($id) {
                $after_sale = new AfterSale(0);
                $data = $after_sale->getAfterSaleInfo($id);
                return makeApiResponseSuccess('ok', $data);
            } else {
                return makeApiResponseFail('数据异常');
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取订单详情
     * @param Request $request
     * @return array
     */
    public function editStatus(Request $request)
    {
        try {
            $param = $request->toArray();
            $id = $param['id'];
            if ($id) {
                $after_sale = new AfterSale(0);
                $data = $after_sale->editStatus($param);
                if ($data) {
                    return makeApiResponseSuccess('ok');
                } else {
                    return makeApiResponseFail('退款金额有误');
                }
            } else {
                return makeApiResponseFail('数据异常');
            }
            return makeApiResponseSuccess('ok');
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
            $afterSale = new AfterSale(0);

            //1 申请退款  2 申请退货 3 等待买家退货 4 等待卖家收货 5 退款成功 6 待审核 7 审核不通过 8 退款关闭
            if ($param['after_sale_status'] == 1) {
                $param['status'] = Constants::RefundStatus_Apply;
                $param['type'] = Constants::AfterSaleType_Refund;
            } else if ($param['after_sale_status'] == 2) {
                $param['status'] = Constants::RefundStatus_Apply;
                $param['type'] = Constants::AfterSaleType_ReturnProduct;
            } else if ($param['after_sale_status'] == 3) {
                $param['status'] = Constants::RefundStatus_Agree ;
                $param['type'] = Constants::AfterSaleType_ReturnProduct;
            } else if ($param['after_sale_status'] == 4) {
                $param['status'] = Constants::RefundStatus_Shipped;
                $param['type'] = Constants::AfterSaleType_ReturnProduct;
            } else if ($param['after_sale_status'] == 5) {
                $param['status'] = Constants::RefundStatus_Over;
            } else if ($param['after_sale_status'] == 6) {
                $param['status'] = Constants::RefundStatus_Received;
                $param['type'] = Constants::AfterSaleType_ReturnProduct;
            } else if ($param['after_sale_status'] == 7) {
                $param['status'] = Constants::RefundStatus_Reject;
            } else if ($param['after_sale_status'] == 8) {
                $param['status'] = Constants::RefundStatus_Cancel;
            }
            $data = $afterSale->getList($param);

            $exportHeadings = [
                '售后订单号', // A 需合并
                '申请售后时间',// B 需合并
                '总订单号', // C 需合并
                '订单总状态', // D 需合并
                '终端来源',// E 需合并
                '买家ID',// F 需合并
                '买家昵称',// G 需合并
                '买家姓名',// H 需合并
                '买家手机号',// I 需合并
                '供应商/自营商品',// J
                '商品名称',// K
                '商品编号', // L
                '规格', // M
                '成本价',// N
                '单价（优惠前）',// O
                '数量',// P
                '优惠',// Q 需合并
                '小计（优惠后）',// R 需合并
                '实付金额',// S 需合并
                '申请退款金额',// T 需合并
                '实际退款金额',// U 需合并
                '售后状态',// V 需合并
                '退款时间',// W 需合并
                '退款流水号',// X 需合并
                '申请原因',// Y 需合并
                '退款/退货说明',// Z 需合并
                '退款物流公司',// AA 需合并
                '退款物流单号'// AB 需合并
            ];
            $exportData = [];
            $row_num = 2;
            $merge = [];
            if ($data['list']) {
                // 构造导出格式
                foreach ($data['list'] as $order) {
                    $item_list_length = count($order['item_list']);
                    $merge_false = false;//是否需要记录合并单元格的标识
                    foreach ($order['item_list'] as $key => $item) {
                        $exportData[] = [
                            "\t" . $order->after_sale_id . "\t",//如果不加\t excel表中订单号尾数会变为零，原因是excel默认的科学计数法导致
                            "\t" . $order->after_sale_created_at . "\t",
                            "\t" . $order->order_id . "\t",
                            Constants::getOrderStatusTextForAdmin($order->order_status),
                            CoreConstants::getTerminalTypeText($order->terminal_type),
                            $order->member_id,
                            $order->nickname,
                            $order->name,
                            "\t" . $order->mobile . "\t",
                            $item->supplier_member_id ? $item->supplier_name:"自营",
                            $item->name,
                            $item->serial_number,
                            $item->sku_names,
                            $item->cost,
                            $item->price,
                            $item->after_sale_item_num,
                            $item->preferential,
                            $item->subtotal,
                            $order->is_all_after_sale == 1 ? "\t" . $order->actual_amount . "\t" . '(含运费' . "\t" . $order->total_refund_freight . "\t" . ')' : "\t" . $order->actual_amount . "\t" . '(不含运费)',
                            $order->actual_amount,
                            $order->real_money == 0 ? '--' : $order->real_money,
                            $order->status_text,
                            $order->active_at,
                            $order->tradeno,
                            Constants::getAfterSaleReasonText($order->reason),
                            $order->after_sale_status == Constants::RefundStatus_Reject ? $order->refuse_msg : $order->content,
                            $order->return_logistics_company == 0 ? $order->return_logistics_name : Constants::getExpressCompanyText($order->return_logistics_company),
                            "\t" . $order->return_logistics_no . "\t",
                        ];
                        //合并单元格
                        if ($merge_false == false) {
                            if ($item_list_length > 1) {
                                //一次循环，只需记录一次合并的参数
                                $merge_false = true;
                                $start_row_num = $row_num;
                                $end_row_num = $row_num + $item_list_length - 1;
                                $merge = array_merge($merge, [
                                    'A' . $start_row_num . ':' . 'A' . $end_row_num,
                                    'B' . $start_row_num . ':' . 'B' . $end_row_num,
                                    'C' . $start_row_num . ':' . 'C' . $end_row_num,
                                    'D' . $start_row_num . ':' . 'D' . $end_row_num,
                                    'E' . $start_row_num . ':' . 'E' . $end_row_num,
                                    'F' . $start_row_num . ':' . 'F' . $end_row_num,
                                    'G' . $start_row_num . ':' . 'G' . $end_row_num,
                                    'H' . $start_row_num . ':' . 'H' . $end_row_num,
                                    'I' . $start_row_num . ':' . 'I' . $end_row_num,
                                    'Q' . $start_row_num . ':' . 'Q' . $end_row_num,
                                    'R' . $start_row_num . ':' . 'R' . $end_row_num,
                                    'S' . $start_row_num . ':' . 'S' . $end_row_num,
                                    'T' . $start_row_num . ':' . 'T' . $end_row_num,
                                    'U' . $start_row_num . ':' . 'U' . $end_row_num,
                                    'V' . $start_row_num . ':' . 'V' . $end_row_num,
                                    'W' . $start_row_num . ':' . 'W' . $end_row_num,
                                    'X' . $start_row_num . ':' . 'X' . $end_row_num,
                                    'Y' . $start_row_num . ':' . 'Y' . $end_row_num,
                                    'Z' . $start_row_num . ':' . 'Z' . $end_row_num,
                                    'AA' . $start_row_num . ':' . 'AA' . $end_row_num,
                                    'AB' . $start_row_num . ':' . 'AB' . $end_row_num
                                ]);
                            }
                            $row_num = $row_num + $item_list_length;
                        }
                    }
                }
            }
            // 导出
            $exportObj = new Export(new Collection($exportData), 'ShouHou-' . date("YmdHis") . '.xlsx', $exportHeadings);
            $exportObj->setMerge($merge);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}