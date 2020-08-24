<?php

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Settle;

use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Order\Order;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformCount;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformSettle;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\Site\Site;

class SupplierPlatformSettleController extends BaseSupplierPlatformController
{
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $settle = new SupplierPlatformSettle($this->siteId,$this->memberId);
            $data = $settle->getList($param);
            $data = $this->convertOutput($data);
            return makeApiResponseSuccess('ok',$data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getCountInfo(Request $request){
        try {
            $settle = new SupplierPlatformCount($this->siteId,$this->memberId);
            $data = $settle->getCountInfo(['count_settle' => 1]);
            return makeApiResponseSuccess('ok',$data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    private function convertOutput($data){
        foreach ($data['list'] as &$item) {
            $item->terminal_type_text = \YZ\Core\Constants::getTerminalTypeText($item->terminal_type);
            $item->money = moneyCent2Yuan($item->money);
            $item->freight = moneyCent2Yuan($item->freight);
            $item->settle_money = moneyCent2Yuan($item->settle_money);
            $item->settle_freight = moneyCent2Yuan($item->settle_freight);
            $item->settle_after_sale_money = moneyCent2Yuan($item->settle_after_sale_money);
            $item->settle_after_sale_freight = moneyCent2Yuan($item->settle_after_sale_freight);
            $item->real_settle = moneyCent2Yuan($item->real_settle);
            $item->all_after_sale = moneyCent2Yuan($item->all_after_sale);
            foreach ($item->item_list as &$subItem) {
                $subItem->price = moneyCent2Yuan($subItem->price);
                $subItem->supplier_price = moneyCent2Yuan($subItem->supplier_price);
                $subItem->real_price = moneyCent2Yuan($subItem->real_price);
                $subItem->coupon_money = moneyCent2Yuan($subItem->coupon_money);
                $subItem->point_money = moneyCent2Yuan($subItem->point_money);
                $subItem->discount_price = moneyCent2Yuan($subItem->discount_price);
                $subItem->all_discount = moneyCent2Yuan($subItem->all_discount);
            }
            unset($subItem);
        }
        unset($item);
        return $data;
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
            $settle = new SupplierPlatformSettle($this->siteId,$this->memberId);
            $data = $settle->getList($param);
            $data = $this->convertOutput($data);
            $exportHeadings = [
                '总订单号', // A 需合并
                '下单时间', // B 需合并
                '终端来源', // C 需合并
                '买家ID',// D 需合并
                '买家昵称',// E 需合并
                '买家姓名',// F 需合并
                '买家手机号',// G 需合并
                '总订单状态',// H 需合并
                '商品名称',// I
                '规格',// J
                '单价（优惠后）',// K
                '供货单价',// L
                '数量',// M
                '订单金额',// N 需合并
                '结算明细-实际结算',// O 需合并
                '结算明细-商品结算',// P 需合并
                '结算明细-运费结算',// Q 需合并
                '结算明细-售后扣除',// R 需合并
                '结算状态',// S 需合并
            ];
            $exportData = [];
            $row_num = 2;
            $merge = [];
            if ($data['list']) {
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
                            $item->name,
                            implode(',', $item->sku_names),
                            $item->price,
                            $item->supplier_price,
                            $item->num,
                            $order->money,
                            $order->real_settle,
                            $order->settle_money,
                            $order->settle_freight,
                            abs($order->all_after_sale) ? abs($order->all_after_sale) : '0',
                            $order->settle_status_text
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
                                    'N' . $start_row_num . ':' . 'N' . $end_row_num,
                                    'O' . $start_row_num . ':' . 'O' . $end_row_num,
                                    'P' . $start_row_num . ':' . 'P' . $end_row_num,
                                    'Q' . $start_row_num . ':' . 'Q' . $end_row_num,
                                    'R' . $start_row_num . ':' . 'R' . $end_row_num,
                                    'S' . $start_row_num . ':' . 'S' . $end_row_num
                                ]);
                            }
                            $row_num = $row_num + $item_list_length;
                        }
                    }
                }
            }
            // 导出
            $exportObj = new Export(new Collection($exportData), 'DingDanJieSuan-' . date("YmdHis") . '.xlsx', $exportHeadings);
            $exportObj->setMerge($merge);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
