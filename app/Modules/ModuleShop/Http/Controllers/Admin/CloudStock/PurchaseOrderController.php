<?php
/**
 * 进货单后台接口
 * User: liyaohui
 * Date: 2019/8/28
 * Time: 16:19
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\CloudStock;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\CloudStock\AdminPurchaseOrder;
use Illuminate\Http\Request;
use YZ\Core\Common\Export;
use Illuminate\Support\Collection;

class PurchaseOrderController extends BaseAdminController
{
    /**
     * 获取进货单列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $params = $request->all(['status', 'keyword', 'created_at_start', 'created_at_end']);
            $list = (new AdminPurchaseOrder())->getList($params, $page, $pageSize);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 财务审核
     * @param Request $request
     * @return array
     */
    public function financeReview(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            $review_status = $request->input('review_status', 0); // 审核状态
            $paymentStatus = $request->input('payment_status', 0); // 是否确认收到了货款
            $remark = $request->input('remark', '');
            $save = (new AdminPurchaseOrder())->financeReview($orderId, $review_status, $paymentStatus, trim($remark));
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '审核失败');
            }
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
            $info = (new AdminPurchaseOrder())->getOrderInfo($orderId);
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取财务审核需要的信息
     * @param Request $request
     * @return array
     */
    public function getFinanceReviewInfo(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            $info = (new AdminPurchaseOrder())->getFinanceReviewInfo($orderId);
            return makeApiResponseSuccess('ok', $info);
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
            $save = (new AdminPurchaseOrder())->editRemarkInside($orderId, trim($text));
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
     * 后台手动配仓
     * @param Request $request
     * @return array
     */
    public function orderManualStockDeliver(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            $save = (new AdminPurchaseOrder())->orderManualStockDeliver($orderId, true);
            if ($save === true) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '配仓失败', $save['data']);
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
            $data = (new AdminPurchaseOrder())->getList($params);
            $exportHeadings = [
                '云仓进货单号',
                '下单时间',
                '买家ID',
                '买家昵称',
                '买家姓名',
                '买家手机号',
                '订单金额',
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
                        $item->total_money,
                        $item->status_text
                    ];
                }
            }

            $exportObj = new Export(new Collection($exportData), 'JINHUO-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}