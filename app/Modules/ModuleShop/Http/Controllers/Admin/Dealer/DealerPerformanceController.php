<?php
namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformance;
use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;

class DealerPerformanceController extends BaseSiteAdminController
{
    /**
     * 业绩列表
     * @param Request $request
     * @return array
     */
    public function getPerformanceList(Request $request)
    {
        try {
            $params = $request->all();
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $params['status'] = Constants::DealerStatus_Active;
            $data = DealerPerformance::getPerformanceList($params, $page, $pageSize);
            $levels = DealerLevel::getCachedLevels();
            if ($data && $data['list']) {
                foreach ($data['list'] as $item) {
                    $item->member_dealer_level_text = $levels[$item->member_dealer_level]['name'];
                    $item->member_dealer_hide_level_text = $levels[$item->member_dealer_hide_level]['name'];
                    $item->dealer_parent_dealer_level_text = $levels[$item->parent_dealer_level]['name'];
                    $item->performance = moneyCent2Yuan($item->performance);
                    if (!$item->dealer_parent_id) {
                        $item->dealer_parent_nickname = '总店';
                    }
                    $item->member_mobile = Member::memberMobileReplace($item->member_mobile);
                }
            }
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 业绩导出
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportPerformanceList(Request $request)
    {
        try {
            $params = $request->all();
            $params['status'] = Constants::DealerLevelStatus_Active;
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $data = DealerPerformance::getPerformanceList($params, $page, $pageSize);
            $levels = DealerLevel::getCachedLevels();
            $exportData = [];
            $exportFileName = 'YeJi-' . date("YmdHis");
            if ($data && $data['list']) {
                foreach ($data['list'] as $item) {
                    $item->member_dealer_level_text = $levels[$item->member_dealer_level]['name'];
                    $item->member_dealer_hide_level_text = $levels[$item->member_dealer_hide_level]['name'];
                    $item->dealer_parent_dealer_level_text = $levels[$item->parent_dealer_level]['name'];
                    $item->performance = moneyCent2Yuan($item->performance);
                    if (!$item->dealer_parent_id) {
                        $item->dealer_parent_nickname = '总店';
                    }
                    $exportData[] = [
                        $item->member_id,
                        $item->member_nickname,
                        $item->member_name,
                        $item->member_mobile,
                        $item->member_dealer_level_text.($item->member_dealer_hide_level_text ? ' - '.$item->member_dealer_hide_level_text : ''),
                        $item->performance,
                        $item->dealer_parent_nickname,
                        str_ireplace('-', '.', $data['time_start']) . '-' . str_ireplace('-', '.', $data['time_end']),
                    ];
                }
                // 处理导出的文件名
                if ($data['time_sign']) {
                    $timeSignParam = explode('-', $data['time_sign']);
                    if (count($timeSignParam) >= 2) {
                        if ($timeSignParam[0] == '2') {
                            $exportFileName = 'NianDu' . $timeSignParam[1];
                        } else if ($timeSignParam[0] == '1') {
                            $exportFileName = 'JiDu' . $timeSignParam[1] . '-' . $timeSignParam[2];
                        } else {
                            $exportFileName = 'YueDu' . date('Ym', strtotime($timeSignParam[1] . '-' . $timeSignParam[2]));
                        }
                        $exportFileName .= '-' . date("YmdHis");
                    }
                }
            }
            // 表头
            $exportHeadings = [
                'ID',
                '昵称',
                '姓名',
                '手机号',
                '经销商等级',
                '个人业绩统计',
                '上级领导',
                '统计业绩周期',
            ];
            // 导出
            $exportObj = new Export(new Collection($exportData), $exportFileName . '.xlsx', $exportHeadings);
            return $exportObj->export();
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}