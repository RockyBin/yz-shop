<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformance;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformanceReward;
use App\Modules\ModuleShop\Libs\Dealer\DealerReward;
use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\Site\Site;

class DealerPerformanceRewardController extends BaseSiteAdminController
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
            $data = DealerPerformanceReward::getList($param);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $id = $request->get('id');
            if (!$id) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $dealerPerformanceReward = new DealerReward($id);

            $model = $dealerPerformanceReward->getModel();
            // 用户信息
            $member = new Member($model->member_id);
            if ($member->checkExist()) {
                $model->member_nickname = $member->getModel()->nickname;
                $model->member_mobile = $member->getModel()->mobile;
                $model->member_headurl = $member->getModel()->headurl;
                if($model->member_headurl && !preg_match('/^(http)/i',$model->member_headurl)) $model->member_headurl = Site::getSiteComdataDir().$member->getModel()->headurl;
            }
            DealerPerformance::convertData($model);
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $model);
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
            $data = DealerPerformanceReward::getList($param);
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $exportData[] = [
                        $item->member_id,
                        $item->member_nickname,
                        $item->member_name,
                        "\t" . $item->member_mobile . "\t",
                        $item->dealer_level_name.($item->dealer_hide_level_name ? ' - '.$item->dealer_hide_level_name : ''),
                        $item->total_performance_money,
                        $item->performance_money,
                        $item->reward_money,
                        str_ireplace('-', '.', $item->period_start) . '-' . str_ireplace('-', '.', $item->period_end),
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
                '经销商等级',
                '累计业绩',
                '贡献业绩',
                '业绩奖金',
                '统计业绩周期',
                '支付奖金人昵称',
                '支付奖金人手机号',
                '状态',
            ];
            // 导出
            $exportObj = new Export(new Collection($exportData), 'YeJi-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function check(Request $request)
    {
        try {
            $id = $request->get('id');
            $status = intval($request->get('status'));
            if (!$id || $status == Constants::DealerRewardStatus_Freeze) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $dealerPerformanceReward = new DealerPerformanceReward($id);
            // 数据必须存在且未审核
            if (!$dealerPerformanceReward->checkExist() || intval($dealerPerformanceReward->getModel()->status) !== Constants::DealerRewardStatus_Freeze) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            // 拒绝就必须填写理由
            $reason = trim($request->get('reason'));
            if ($status < 0 && !$reason) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            if ($status > 0) {
                $dealerPerformanceReward->pass();
            } else {
                $dealerPerformanceReward->reject($reason);
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}