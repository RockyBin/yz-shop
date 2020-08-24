<?php
/**
 * User: liyaohui
 * Date: 2019/8/28
 * Time: 17:47
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\CloudStock;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\CloudStock\AdminTakeDeliveryOrder;
use Illuminate\Http\Request;
use YZ\Core\Common\Export;
use Illuminate\Support\Collection;

class TakeDeliveryOrderController extends BaseAdminController
{
    /**
     * 获取提货单列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $params = $request->all(['status', 'keyword', 'created_at_start', 'created_at_end']);
            $list = (new AdminTakeDeliveryOrder())->getList($params, $page, $pageSize);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取订单详情
     * @param Request $request
     * @return array
     */
    public function getOrderInfo(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            $info = (new AdminTakeDeliveryOrder())->getOrderInfo($orderId);
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 订单发货
     * @param Request $request
     * @return array
     */
    public function orderDeliver(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            $delivery = $request->all(['logistics_company', 'logistics_no', 'logistics_name']);
            $items = $request->input('items', []);
            $save = (new AdminTakeDeliveryOrder())->deliver($orderId, $items, $delivery);
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '发货失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 内部备注
     * @param Request $request
     * @return array
     */
    public function editRemarkInside(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            $text = $request->input('remark', '');
            $save = (new AdminTakeDeliveryOrder())->editRemarkInside($orderId, trim($text));
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '保存失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 内部备注
     * @param Request $request
     * @return array
     */
    public function editLogistics(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            $delivery = $request->all(['logistics_company', 'logistics_no', 'logistics_name']);
            $id = $request->input('id', 0);
            $is_virtual = $request->input('is_virtual',0);
            if ((!is_numeric($delivery['logistics_company']) || empty($delivery['logistics_no']) || empty($delivery['logistics_name'])) && $is_virtual == 0 ) {
                return makeApiResponseFail('数据异常');
            }
            $save = (new AdminTakeDeliveryOrder())->editLogistics($orderId, $id, $delivery,$is_virtual);
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '修改失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     *  导出
     * @param Request $request
     * @return array
     */
    public function export(Request $request)
    {
        try {
            $params = $request->all(['status', 'keyword', 'created_at_start', 'created_at_end','ids','show_all']);
            $data = (new AdminTakeDeliveryOrder())->getList($params);
            $exportHeadings = [
                '云仓提货单号',
                '下单时间',
                '提货人ID',
                '提货人昵称',
                '提货人姓名',
                '提货人手机号',
                '提货数量',
                '订单状态'
            ];
            $exportData = [];

            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $exportData[] = [
                        "\t" . $item->order_id . "\t",
                        $item->created_at,
                        $item->member_id,
                        $item->nickname,
                        $item->name,
                        "\t" . $item->mobile . "\t",
                        $item->product_num,
                        $item->status_text
                    ];
                }
            }

            $exportObj = new Export(new Collection($exportData), 'TIHUO-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}