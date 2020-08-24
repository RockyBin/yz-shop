<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformance;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceReward;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;

class AgentPerformanceRewardController extends BaseSiteAdminController
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
            $data = AgentPerformanceReward::getList($param);
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $this->convertData($item);
                }
            }
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
            $agentPerformanceReward = new AgentPerformanceReward($id);
            if (!$agentPerformanceReward->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $model = $agentPerformanceReward->getModel();
            // 用户信息
            $member = new Member($model->member_id);
            if ($member->checkExist()) {
                $model->member_nickname = $member->getModel()->nickname;
                $model->member_mobile = $member->getModel()->mobile;
                $model->member_headurl = $member->getModel()->headurl;
            }
            $this->convertData($model);
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
            $data = AgentPerformanceReward::getList($param);
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $this->convertData($item);
                    $exportData[] = [
                        $item->member_id,
                        $item->member_nickname,
                        $item->member_name,
                        $item->member_mobile,
                        $item->member_agent_level_text,
                        $item->performance_money,
                        $item->reward_money,
                        str_ireplace('-', '.', $item->period_start) . '-' . str_ireplace('-', '.', $item->period_end),
                        $item->status_text,
                    ];
                }
            }
            // 表头
            $exportHeadings = [
                'ID',
                '昵称',
                '姓名',
                '手机号',
                '代理等级',
                '团队业绩统计',
                '业绩奖金',
                '统计业绩周期',
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
            if (!$id || $status == Constants::AgentRewardStatus_Freeze) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $agentPerformanceReward = new AgentPerformanceReward($id);
            // 数据必须存在且未审核
            if (!$agentPerformanceReward->checkExist() || !intval($agentPerformanceReward->getModel()->status) == Constants::AgentRewardStatus_Freeze) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            // 拒绝就必须填写理由
            $reason = trim($request->get('reason'));
            if ($status < 0 && !$reason) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            if ($status > 0) {
                $agentPerformanceReward->pass();
            } else {
                $agentPerformanceReward->reject($reason);
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 输出数据转换
     * @param $item
     */
    private function convertData($item)
    {
        if ($item) {
            $item['reward_money'] = moneyCent2Yuan($item['reward_money']);
            $item['performance_money'] = moneyCent2Yuan($item['performance_money']);
            // 代理等级
            $item['member_agent_level_text'] = '';
            if ($item['member_agent_level']) {
                $item['member_agent_level_text'] = Constants::getAgentLevelTextForAdmin($item['member_agent_level']);
            }
            // 统计时间段
            $item['period_start'] = '';
            $item['period_end'] = '';
            if ($item['period']) {
                list($givePeriod, $year, $num) = explode('_', $item['period']);
                $timeParam = AgentPerformance::parseTime($givePeriod, $year, $num);
                $item['period_start'] = $timeParam['start'];
                $item['period_end'] = $timeParam['end'];
            }
            // 状态
            if ($item['status'] == 1) {
                $item['status_text'] = '已发放';
            } else if ($item['status'] == -1) {
                $item['status_text'] = '已拒绝';
            } else {
                $item['status_text'] = '待审核';
            }
        }
    }
}