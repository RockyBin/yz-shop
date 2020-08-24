<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\AreaAgent;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\AreaAgent\AdminAreaAgentCommission;
use YZ\Core\Common\Export;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AreaAgentCommissionController extends BaseAdminController
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
            $list = AdminAreaAgentCommission::getList($params);
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
        $data = AdminAreaAgentCommission::getList($params);
        $exportHeadings = [
            '订单号',
            '下单时间',
            '订单实付金额',
            '订单返佣',
            '省代信息',
            '省代返佣',
            '市代信息',
            '市代返佣',
            '区代信息',
            '区代返佣',
            '发放状态'
        ];
        $exportData = [];
        $merge = [];
        if ($data['list']) {
            // 构造导出格式
            foreach ($data['list'] as $order) {
                $agentOrderCommissionData = [
                    'province' => [
                        'info' => '--',
                        'money' => '--',
                    ],
                    'city' => [
                        'info' => '--',
                        'money' => '--',
                    ],
                    'district' => [
                        'info' => '--',
                        'money' => '--',
                    ],
                ];
                foreach ($order->area_agent_commission as $item) {
                    $agentOrderCommissionData[$item['area_type']] = [
                        'info' => 'ID:' . $item ['member_id'] . '/' . $item['nickname'] . '/' . $item['name'] . '/' . $item['mobile'],
                        'money' => $item['money'],
                    ];
                }
                $statusText = '预计发放';
                if ($order->area_agent_commission_status == 2) $statusText = '已发放';
                if ($order->area_agent_commission_status == 3) $statusText = '失效';
                $exportData[] = [
                    "\t" . $order->order_id . "\t", // 如果不加\t excel表中订单号尾数会变为零，原因是excel默认的科学计数法导致
                    $order->created_at,
                    $order->order_money,
                    $order->total_commission,
                    $agentOrderCommissionData['province']['info'],
                    $agentOrderCommissionData['province']['money'],
                    $agentOrderCommissionData['city']['info'],
                    $agentOrderCommissionData['city']['money'],
                    $agentOrderCommissionData['district']['info'],
                    $agentOrderCommissionData['district']['money'],
                    "\t" . $statusText . "\t",
                ];
            }
        }
        // 导出
        $exportObj = new Export(new Collection($exportData), 'FanYong-' . date("YmdHis") . '.xlsx', $exportHeadings);
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
