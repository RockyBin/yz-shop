<?php
/**
 * 销售奖后台api
 * User: liyaohui
 * Date: 2020/1/10
 * Time: 20:10
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;


use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerSaleReward;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;

class DealerSaleRewardController extends BaseSiteAdminController
{
    /**
     * 列表数据
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $data = DealerSaleReward::getList($param);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 导出
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        try {
            $param = $request->all();
            $data = DealerSaleReward::getList($param);
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $exportData[] = [
                        $item->member_id,
                        $item->member_nickname,
                        $item->member_name,
                        "\t" . $item->member_mobile . "\t",
                        "\t" . $item->order_id . "\t",
                        $item->order_created_at,
                        $item->order_money,
                        $item->reward_money,
                        $item->pay_member_nickname,
                        $item->pay_member_mobile ? "\t" . $item->pay_member_mobile . "\t" : '--',
                        $item->status_text,
                    ];
                }
            }
            // 表头
            $exportHeadings = [
                '得奖经销商ID',
                '得奖经销商昵称',
                '得奖经销商姓名',
                '得奖经销商手机号',
                '关联订单号',
                '下单时间',
                '订单金额',
                '销售奖金',
                '支付奖金人昵称',
                '支付奖金人手机号',
                '状态',
            ];
            // 导出
            $exportObj = new Export(new Collection($exportData), 'Xiaoshou-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}