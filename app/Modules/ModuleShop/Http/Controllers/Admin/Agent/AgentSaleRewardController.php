<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\Agent\AgentSaleReward;
use YZ\Core\Common\Export;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AgentSaleRewardController extends BaseAdminController
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
            $list = AgentSaleReward::getList($params);
            return makeApiResponseSuccess('ok', $list);
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
            $data = AgentSaleReward::getList($params);
            $exportHeadings = [
                '订单号',
                '下单时间',
                '订单金额',
                '销售总返佣',
                '得奖代理商1（元）',
                '得奖代理商1',
                '得奖代理商2（元）',
                '得奖代理商2',
                '得奖代理商3（元）',
                '得奖代理商3',
                // '代理等级',
                '发放状态',
            ];
            $exportData = [];
            $merge = [];
            if ($data['list']) {
                // 构造导出格式
                foreach ($data['list'] as $order) {
                    $firstInfoMoney = "";
                    $firstInfo = "";
                    $secondInfoMoney = "";
                    $secondInfo = "";
                    $thirdInfoMoney = "";
                    $thirdInfo = "";
                    $statusText = '预计发放';
                    if ($order->finance_status == 1) $statusText = '已发放';
                    if ($order->finance_status == 2) $statusText = '失效';
                    if (is_array($order->agent_sale_reward_commision) && count($order->agent_sale_reward_commision) > 0) {
                        $china = ['', '一级', '二级', '三级'];
                        $agent_sale_reward_commision = $order->agent_sale_reward_commision;
                        $firstInfoMoney .= ($agent_sale_reward_commision[0]['is_samelevel'] == 1 ? $china[$agent_sale_reward_commision[0]['agent_level']] . '平级奖' : '越级奖') . ':';
                        $firstInfoMoney .= $agent_sale_reward_commision[0]['money'];
                        $firstInfo = 'ID:' . $agent_sale_reward_commision[0] ['member_id'] . '/' . $agent_sale_reward_commision[0]['nickname'] . '/' . $agent_sale_reward_commision[0]['name'] . '/' . $agent_sale_reward_commision[0]['mobile'];
                        if ($agent_sale_reward_commision[1]) {
                            $secondInfoMoney .= ($agent_sale_reward_commision[1]['is_samelevel'] == 1 ? $china[$agent_sale_reward_commision[1]['agent_level']] . '平级奖' : '越级奖') . ':';
                            $secondInfoMoney .= $agent_sale_reward_commision[1]['money'];
                            $secondInfo = 'ID:' . $agent_sale_reward_commision[1] ['member_id'] . '/' . $agent_sale_reward_commision[1]['nickname'] . '/' . $agent_sale_reward_commision[1]['name'] . '/' . $agent_sale_reward_commision[1]['mobile'];
                        }
                        if ($agent_sale_reward_commision[2]) {
                            $thirdInfoMoney .= ($agent_sale_reward_commision[2]['is_samelevel'] == 1 ? $china[$agent_sale_reward_commision[2]['agent_level']] . '平级奖' : '越级奖') . ':';
                            $thirdInfoMoney .= $agent_sale_reward_commision[2]['money'];
                            $thirdInfo = 'ID:' . $agent_sale_reward_commision[2] ['member_id'] . '/' . $agent_sale_reward_commision[2]['nickname'] . '/' . $agent_sale_reward_commision[2]['name'] . '/' . $agent_sale_reward_commision[2]['mobile'];
                        }
                    }
                    $exportData[] = [
                        "\t" . $order->order_id . "\t", // 如果不加\t excel表中订单号尾数会变为零，原因是excel默认的科学计数法导致
                        $order->created_at,
                        $order->order_money,
                        $order->total_commission,
                        $firstInfoMoney,
                        $firstInfo,
                        $secondInfoMoney,
                        $secondInfo,
                        $thirdInfoMoney,
                        $thirdInfo,
                        // $memberAgentLevelText,
                        "\t" . $statusText . "\t",
                    ];
                }
            }
            // 导出
            $exportObj = new Export(new Collection($exportData), 'XiaoShou-' . date("YmdHis") . '.xlsx', $exportHeadings);
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
                            $widths = ['A' => 30, 'B' => 25, 'C' => 25, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 15];
                            foreach ($widths as $col => $val) {
                                $event->sheet->getDelegate()->getColumnDimension($col)->setWidth($val);
                            }
                        }
                    ];
                }
            );
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
