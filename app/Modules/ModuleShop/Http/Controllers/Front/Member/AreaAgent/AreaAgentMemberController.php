<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\AreaAgent;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformance;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceRewardSetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentBaseSetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentHelper;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentor;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceModel;
use Illuminate\Http\Request;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;

/**
 * 区代成员相关控制器
 * Class AgentMemberController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\AreaAgent
 */
class AreaAgentMemberController extends BaseController
{
    /**
     * 获取成员统计信息
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        try {
            // 会员信息
            $memberId = $this->memberId;
            $member = new Member($memberId);
            $areaId = $request->area_id;
            if (!$areaId) {
                return makeServiceResultFail("area_id不能为空");
            }
            if (!$member->checkExist()) {
                return makeServiceResultFail("不是会员");
            }
            // 检查是否代理
            $agent = new  AreaAgentor($areaId);
            if (!$member->isAreaAgent()) {
                return makeServiceResultFail("此区域代理不生效或不存在");
            }
            // 获取下级成员统计信息
            $data = $agent->getTeamMemberCount();
            // 自身信息
            $data['baseinfo'] = $agent->getModel()->toArray();
            $data['baseinfo']['area_path'] = AreaAgentHelper::getAreaTypePath($agent->getModel()->area_type, [$agent->getModel()->prov, $agent->getModel()->city, $agent->getModel()->district]);
            // 返回数据
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


    /**
     * 获取会员代理的列表
     * @param Request $request
     * @return array
     */
    public function getAreaAgentList()
    {
        try {
            // 会员信息
            $memberId = $this->memberId;
            $member = new Member($memberId);
            if (!$member->checkExist()) {
                return makeServiceResultFail("不是会员");
            }
            // 检查是否代理
            if (!$member->isAreaAgent()) {
                return makeServiceResult(402,"此区域代理已被取消资格或不存在");
            }
            $data = AreaAgentHelper::getMemberAreaAgentListAndCount($this->memberId);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 分红列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            // 检查是否代理
            $memberId = $this->memberId;
            $member = new Member($memberId);
            $areaId = $request->area_id;
            if (!$areaId) {
                return makeServiceResultFail("area_id不能为空");
            }
            $agent = new AreaAgentor($areaId);
            // 检查是否代理
            if (!$member->isAreaAgent()) {
                return makeServiceResultFail("此区域代理不生效或不存在");
            }
            $data = $agent->getSubAgentList($request->all());
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }


    public function center()
    {
        try {
            $agentBaseSetting = AreaAgentBaseSetting::getCurrentSiteSetting();
            if ($agentBaseSetting->status != 1) {
                return makeApiResponseFail('无开设区域代理功能，请联系客服');
            }

            $data['member_info'] = AreaAgentor::getAreaAgentMemberInfo($this->memberId);
            $data['count_data'] = AreaAgentor::getCountData([
                'member_id' => $this->memberId,
                'commission' => true,
                'history_area_agent_commission_order_count' => true,
                'performance_now' => true
            ], true);
            $data['count_data']['area_agent_total'] = AreaAgentHelper::getMemberAreaAgentListAndCount($this->memberId, true);
            if ($data['member_info']['is_area_agent'] == -2) {
                return makeApiResponse(402, '您已被取消区域代理资格，如有疑问，请联系客服', $data);
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}