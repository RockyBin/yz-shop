<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Modules\ModuleShop\Libs\Agent\AgentOtherReward;
use  Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;

class AgentOtherRewardController extends BaseAdminController
{

    public function getList(Request $request)
    {
        try {
            $params = $request->toArray();
            $data = AgentOtherReward::getList($params);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
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
            $params = $request->all();
            $data = AgentOtherReward::getList($params);
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $exportData[] = [
                        "\t" . $item->order_id . "\t",
                        $item->order_created_at,
                        $item->money,
                        $item->reward_money,
                        $item->reward_member_id . '/' . $item->reward_member_nickname . '/' . $item->reward_member_name . '/' . $item->reward_member_mobile,
                        $item->status == 2 ? '已发放' : ($item->status == 1 ? "预计发放" : "失效")
                    ];
                }
            }
            // 表头
            $exportHeadings = [
                '订单号',
                '下单时间',
                '订单实付金额',
                '奖金',
                '得奖人信息',
                '发放状态'
            ];
            // 导出
            $exportObj = new Export(new Collection($exportData), 'JiangLi-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}
