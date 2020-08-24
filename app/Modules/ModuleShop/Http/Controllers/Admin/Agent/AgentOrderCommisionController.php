<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\Agent\AgentOrderCommision;
use YZ\Core\Common\Export;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AgentOrderCommisionController extends BaseAdminController
{
    /**
     * 列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $params = $request->all();
            $list = AgentOrderCommision::getList($params);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 导出
     * @param Request $request
     * @return \Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        // try {
        $params = $request->all();
        if ($params['is_all']) { //导出当前面还是全部
            $params['page'] = 1;
            $params['page_size'] = 99999;
        }
        if ($params['ids']) {
            $ids = $params['ids'];
            $params = [
                'ids' => $ids,
                'page' => 1,
                'page_size' => 99999
            ];
        }
        $data = AgentOrderCommision::getList($params);
        $exportHeadings = [
            '订单号',
            '下单时间',
            '订单金额',
            '订单分红',
            '一级代理ID',
            '一级代理信息',
            '一级代理分红',
            '二级代理ID',
            '二级代理信息',
            '二级代理分红',
            '三级代理ID',
            '三级代理信息',
            '三级代理分红',
            '发放状态'
        ];
        $exportData = [];
        $merge = [];
        if ($data['list']) {
            // 构造导出格式
            foreach ($data['list'] as $order) {
                $agentOrderCommissionData = [
                    '1' => [
                        'info' => '',
                        'money' => '0.00',
                    ],
                    '2' => [
                        'info' => '',
                        'money' => '0.00',
                    ],
                    '3' => [
                        'info' => '',
                        'money' => '0.00',
                    ],
                ];
                foreach ($order->agent_order_commision as $item) {
                    $agentOrderCommissionData[$item['agent_level']] = [
                        'member_id' =>$item ['member_id'],
                        'info' => $item['nickname'] . '/' . $item['name'] . '/' . $item['mobile'],
                        'money' => $item['money'],
                    ];
                }
                $statusText = '预计发放';
                if ($order->finance_status == 1) $statusText = '已发放';
                if ($order->finance_status == 2) $statusText = '失效';
                $exportData[] = [
                    "\t" . $order->id . "\t", // 如果不加\t excel表中订单号尾数会变为零，原因是excel默认的科学计数法导致
                    $order->created_at,
                    $order->order_money,
                    $order->total_commission,
                    $agentOrderCommissionData[1]['member_id'],
                    $agentOrderCommissionData[1]['info'],
                    $agentOrderCommissionData[1]['money'],
                    $agentOrderCommissionData[2]['member_id'],
                    $agentOrderCommissionData[2]['info'],
                    $agentOrderCommissionData[2]['money'],
                    $agentOrderCommissionData[3]['member_id'],
                    $agentOrderCommissionData[3]['info'],
                    $agentOrderCommissionData[3]['money'],
                    "\t" . $statusText . "\t",
                ];
            }
        }
        // 导出
        $exportObj = new Export(new Collection($exportData), 'FenHong-' . date("YmdHis") . '.xlsx', $exportHeadings);
        $exportObj->setMerge($merge);
        // 设置列宽等格式
        $exportObj->setRegisterEvents(
            function () use ($merge) {
                return [
                    AfterSheet::class => function (AfterSheet $event) use ($merge) {
                        foreach ($merge as $m) {
                            $event->sheet->getDelegate()->mergeCells($m);
                            $event->sheet->getStyle($m)->getAlignment()->applyFromArray([
                                'vertical' => Alignment::VERTICAL_CENTER,
                                'horizontal' => Alignment::HORIZONTAL_LEFT,
                            ]);
                        }
                        $widths = ['A' => 30, 'B' => 25, 'C' => 25, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15, 'H' => 15];
                        foreach ($widths as $col => $val) {
                            $event->sheet->getDelegate()->getColumnDimension($col)->setWidth($val);
                        }
                    }
                ];
            }
        );
        return $exportObj->export();
    }
}
