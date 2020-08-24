<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentRecommendReward;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;

class AgentRecommendRewardController extends BaseSiteAdminController
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
            $data = AgentRecommendReward::getList($param);
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
            $agentRecommendReward = new AgentRecommendReward($id);
            if (!$agentRecommendReward->checkExist()) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $model = $agentRecommendReward->getModel();
            // 推荐会员信息
            $member = new Member($model->member_id);
            if ($member->checkExist()) {
                $model->member_nickname = $member->getModel()->nickname;
                $model->member_mobile = $member->getModel()->mobile;
                $model->member_headurl = $member->getModel()->headurl;
            }
            // 被推荐会员信息
            $subMember = new Member($model->sub_member_id);
            if ($subMember->checkExist()) {
                $model->sub_member_nickname = $subMember->getModel()->nickname;
                $model->sub_member_mobile = $subMember->getModel()->mobile;
                $model->sub_member_headurl = $subMember->getModel()->headurl;
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
            $data = AgentRecommendReward::getList($param);
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
                        $item->reward_money,
                        $item->sub_member_id,
                        $item->sub_member_nickname,
                        $item->sub_member_name,
                        $item->sub_member_mobile,
                        $item->sub_member_agent_level_text,
                        $item->created_at,
                        $item->status_text,
                    ];
                }
            }
            // 表头
            $exportHeadings = [
                '推荐人ID',
                '推荐人昵称',
                '推荐人姓名',
                '推荐人手机',
                '推荐人等级',
                '推荐人奖金',
                '被推荐人ID',
                '被推荐人昵称',
                '被推荐人姓名',
                '被推荐人手机',
                '被推荐人等级',
                '推荐升级时间',
                '状态',
            ];
            // 导出
            $exportObj = new Export(new Collection($exportData), 'TuiJian-' . date("YmdHis") . '.xlsx', $exportHeadings);
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
            $agentRecommendReward = new AgentRecommendReward($id);
            // 数据必须存在且未审核
            if (!$agentRecommendReward->checkExist() || !intval($agentRecommendReward->getModel()->status) == Constants::AgentRewardStatus_Freeze) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            // 拒绝就必须填写理由
            $reason = trim($request->get('reason'));
            if ($status < 0 && !$reason) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            if ($status > 0) {
                $agentRecommendReward->pass();
            } else {
                $agentRecommendReward->reject($reason);
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
            // 推荐人代理等级
            $item['member_agent_level_text'] = '';
            if ($item['member_agent_level']) {
                $item['member_agent_level_text'] = Constants::getAgentLevelTextForAdmin($item['member_agent_level']);
            }
            // 被推荐人代理等级
            $item['sub_member_agent_level_text'] = '';
            if ($item['sub_member_agent_level']) {
                $item['sub_member_agent_level_text'] = Constants::getAgentLevelTextForAdmin($item['sub_member_agent_level']);
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