<?php
/**
 * 代理接口
 * User: liyaohui
 * Date: 2019/6/29
 * Time: 18:12
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Agent\Agent;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceRewardSetting;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockApplySetting;
use App\Modules\ModuleShop\Libs\Constants;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\Site\Site;
use Illuminate\Support\Facades\DB;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStock;

class AgentController extends BaseAdminController
{
    /**
     * 获取代理列表
     * @param Request $request
     * @return array
     */
    public function getAgentList(Request $request)
    {
        try {
            $params = $request->all();
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $params['show_agent_commission'] = true;
            $data = Agent::getAgentList($params, $page, $pageSize);
            foreach ($data['list'] as $item) {
                $item->agent_commission = moneyCent2Yuan($item->agent_commission);
                $item->agent_commission_balance = moneyCent2Yuan($item->agent_commission_balance);
                $item->agent_apply_level = $item->agent_apply_level ? $item->agent_apply_level : $item->cancel_history_agent_level;
            }
            $agentBaseSetting = AgentBaseSetting::getCurrentSiteSetting();
            $data['level'] = $agentBaseSetting->level;
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取经销商申请列表
     * @param Request $request
     * @return array
     */
    public function getApplyAgentList(Request $request)
    {
        try {
            $params = $request->all();
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $list = Agent::getApplyAgentList($params, $page, $pageSize);
            $agentBaseSetting = AgentBaseSetting::getCurrentSiteSetting();
            $list['level'] = $agentBaseSetting->level;
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 后台添加代理
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function adminAddAgent(Request $request)
    {
        try {
            $params = $request->all();
            $add = (new Agent())->adminAddAgent($params, true);
            if ($add === true) {
                return makeApiResponseSuccess('ok');
            } else if ($add) {
                return makeApiResponse(511, '数据已存在', [
                    'member_id' => $add->member_id,
                    'status' => $add->status
                ]);
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }




    /**
     * 审核代理
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function verifyAgent(Request $request)
    {
        try {
            $params = $request->all();
            $save = (new Agent())->verifyAgent($params);
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponseFail('审核失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 取消代理
     * @param Request $request
     * @return array
     */
    public function cancelAgent(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            (new Agent())->cancelAgent($memberId);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 恢复代理
     * @param Request $request
     * @return array
     */
    public function resumeAgent(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            (new Agent())->resumeAgent($memberId);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除拒绝申请代理的记录
     * @param Request $request
     * @return array
     */
    public function delAgentRejectApplyData(Request $request)
    {
        try {
            $memberId = $request->input('member_id', 0);
            $del = (new Agent())->delAgentRejectApplyData($memberId);
            if ($del) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponseFail('删除失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

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
            $params['status'] = Constants::AgentStatus_Active;
            $data = Agent::getPerformanceList($params, $page, $pageSize);
            if ($data && $data['list']) {
                foreach ($data['list'] as $item) {
                    $item->member_agent_level_text = Constants::getAgentLevelTextForAdmin($item->member_agent_level);
                    $item->agent_parent_agent_level_text = Constants::getAgentLevelTextForAdmin($item->parent_agent_level);
                    $item->performance = moneyCent2Yuan($item->performance);
                    if (!$item->agent_parent_id) {
                        $item->agent_parent_nickname = '总店';
                    }
                }
            }
            $setting = AgentPerformanceRewardSetting::getCurrentSiteSetting();
            $data['config'] = $setting;

            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 修改代理等级
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function setAgentLevel(Request $request)
    {
        try {
            $memberId = intval($request->get('member_id'));
            $agentLevel = intval($request->get('agent_level'));

            if (!$memberId || !$agentLevel) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $agentor = new Agentor($memberId);
            if (!$agentor->isActive()) {
                return makeApiResponseFail('代理未生效');
            }
            $result = $agentor->setAgentLevel($agentLevel);
            if ($result) {
                return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
            } else {
                return makeApiResponseFail(trans('shop-admin.common.action_fail'));
            }
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
            $params['status'] = Constants::AgentStatus_Active;
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $data = Agent::getPerformanceList($params, $page, $pageSize);
            $exportData = [];
            $exportFileName = 'YeJi-' . date("YmdHis");
            if ($data && $data['list']) {
                foreach ($data['list'] as $item) {
                    $item->member_agent_level_text = Constants::getAgentLevelTextForAdmin($item->member_agent_level);
                    $item->agent_parent_agent_level_text = Constants::getAgentLevelTextForAdmin($item->parent_agent_level);
                    $item->performance = moneyCent2Yuan($item->performance);
                    if (!$item->agent_parent_id) {
                        $item->agent_parent_nickname = '总店';
                    }
                    $exportData[] = [
                        $item->member_nickname,
                        $item->member_mobile,
                        $item->member_agent_level_text,
                        $item->performance,
                        $item->agent_parent_nickname,
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
                '昵称',
                '手机号',
                '经销商等级',
                '团队业绩统计',
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
