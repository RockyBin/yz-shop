<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\CloudStock;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Constants;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockSkuSettle;
use YZ\Core\Common\Export;
use Illuminate\Support\Collection;


class CloudStockSkuSettleController extends BaseAdminController
{
    /**
     * 获取云仓列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $params = $request->all();
            $data = CloudStockSkuSettle::getAdminList($params);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
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
            $params = $request->all();
            $data = CloudStockSkuSettle::getAdminList($params);
            $exportHeadings = [
                '总订单号', // A
                '结算时间', // B
                '买家ID', // C
                '买家昵称',// D 需合并
                '买家姓名',// E 需合并
                '买家手机号',// F 需合并
                '订单状态',// G 需合并
                '结算状态',// H 需合并
                '商品名称',// I 需合并
                '规格',// J 需合并
                '云仓订货单价',// K 需合并
                '数量',// L
                '配仓仓库',// M
                '云仓结算金额',// N
                '结算总金额',// O
            ];
            $exportData = [];
            $row_num = 2;
            $merge = [];
            if ($data['list']) {
                // 构造导出格式
                foreach ($data['list'] as $order) {
                    $item_list_length = count($order['items']);
                    $merge_false = false;//是否需要记录合并单元格的标识
                    foreach ($order['items'] as $item) {
                        $exportData[] = [
                            "\t" . $order['order_id'] . "\t",//如果不加\t excel表中订单号尾数会变为零，原因是excel默认的科学计数法导致
                            $order['finished_at'],
                            $order['buyer_id'],
                            $order['buyer_nickname'],
                            $order['buyer_name'],
                            "\t" . $order['buyer_mobile'] . "\t",
                            $order['status_text'],
                            $order['status'] == 1 ? '已结算' :'未结算',
                            $item->product_name,
                            implode(';', $item->sku_name),
                            $item->price,
                            $item->num,
                            $item->cloudstock_nickname,
                            $item->money,
                            $order['total'],
                        ];
                        //合并单元格
                        if ($merge_false == false) {
                            if ($item_list_length > 1) {
                                //一次循环，只需记录一次合并的参数
                                $merge_false = true;
                                $start_row_num = $row_num;
                                $end_row_num = $row_num + $item_list_length - 1;
                                $merge = array_merge($merge, ['A' . $start_row_num . ':' . 'A' . $end_row_num, 'B' . $start_row_num . ':' . 'B' . $end_row_num, 'C' . $start_row_num . ':' . 'C' . $end_row_num, 'D' . $start_row_num . ':' . 'D' . $end_row_num, 'E' . $start_row_num . ':' . 'E' . $end_row_num, 'F' . $start_row_num . ':' . 'F' . $end_row_num, 'G' . $start_row_num . ':' . 'G' . $end_row_num, 'H' . $start_row_num . ':' . 'H' . $end_row_num, 'O' . $start_row_num . ':' . 'O' . $end_row_num]);
                            }
                            $row_num = $row_num + $item_list_length;
                        }
                    }
                }
            }
            // 导出
            $exportObj = new Export(new Collection($exportData), 'YUNCANGJINHUO-' . date("YmdHis") . '.xlsx', $exportHeadings);
            $exportObj->setMerge($merge);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
