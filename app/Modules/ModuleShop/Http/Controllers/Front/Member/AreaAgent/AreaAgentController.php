<?php


namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\AreaAgent;

use App\Modules\ModuleShop\Http\Controllers\Admin\AreaAgent\AreaAgentApplySettingController;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Agent\AgentApplySetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentApplyFront;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentApplySetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentBaseSetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentConstants;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentor;
use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


class AreaAgentController extends BaseController
{
    /**
     * 获取可申请的区域等级
     * @param Request $request
     * @return array
     */
    public function check()
    {
        try {
            $agentBaseSetting = AreaAgentBaseSetting::getCurrentSiteSetting();
            if ($agentBaseSetting->status != 1) {
                return makeApiResponse(401, '无开设区域代理功能，请联系客服');
            }
            // 检测是否已经申请过(此入口只做第一次申请)，第二次申请入口不在这里
            // 此会员的区域代理已经通过审核并已经生效则跳转到区域代理中心
            $member = (new Member($this->memberId))->getModel();
            if ($member->is_area_agent != 0) {
                return makeApiResponse(200, '区域代理已通过审核');
            } else {
                $agentApplySetting = (new AreaAgentApplySetting())->getInfo();
                if ($agentApplySetting['status'] == 0) {
                    return makeApiResponseFail('无开设加盟功能，请联系客服');
                }
                return makeApiResponse(501, '跳转申请页面');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function apply(Request $request)
    {
        try {
            $agentBaseSetting = AreaAgentBaseSetting::getCurrentSiteSetting();
            if ($agentBaseSetting->status != 1) {
                return makeApiResponseFail('无开设区域代理功能，请联系客服');
            }
            $agentApplySetting = (new AreaAgentApplySetting())->getInfo();
            if ($agentApplySetting['status'] == 0) {
                return makeApiResponseFail('无开设加盟功能，请联系客服');
            }
            if (!$agentApplySetting['apply_level']) {
                return makeApiResponseFail('无可申请的等级，请联系客服');
            }
            $member = (new Member($this->memberId))->getModel();
            if ($member->is_area_agent != 0) {
                return makeApiResponse(200, '区域代理已通过审核');
            }
            // 检测是否已经申请过(此入口只做第一次申请)，第二次申请入口不在这里
            // 此会员是否申请过(重新申请不用检测)
            if ($request->apply_id) {
                $applyLevel = (new AreaAgentApplySetting())->getMemberApplyLevel($this->memberId);
                return makeApiResponse(505, 'ok', $applyLevel);
            }
            if (AreaAgentApplyFront::getAreaAgentApplyByMemberId($this->memberId, [0, -1])) {
                // 此会会员申请过则显示审核进度以及详细信息
                // 暂时没有多个区域申请
                return makeApiResponse(504, 'ok', AreaAgentApplyFront::getApplyInfo($this->memberId));
            } else {
                //无申请过 则要显示可申请等级，并且标记是否可申请
                $applyLevel = (new AreaAgentApplySetting())->getMemberApplyLevel($this->memberId);
                return makeApiResponse(505, 'ok', $applyLevel);
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 申请时上传的文件
     * @param Request $request
     * @return array
     */
    public function applyAreaAgentFile(Request $request)
    {
        try {
            $memberId = $this->memberId;
            // 检测会员是否存在 是否申请过代理
            $areaAgent = new AreaAgentApplyFront();
            $agentBaseSetting = AreaAgentBaseSetting::getCurrentSiteSetting();
            if ($agentBaseSetting->status != 1) {
                return makeApiResponseFail('无开设区域代理功能，请联系客服');
            }
            $file = [];
            if ($request->hasFile('idcard_file_data')) {
                $file['idcard_file_data'] = $areaAgent->uploadFile($request->file('idcard_file_data'), $memberId, 'idcard');
            }
            if ($request->hasFile('business_license_file_data')) {
                $file['business_license_file_data'] = $areaAgent->uploadFile($request->file('business_license_file_data'), $memberId, 'business_license');
            }
            return makeApiResponseSuccess('ok', ['file_url' => $file]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存表单数据
     * @param Request $request
     * @return array|bool
     */
    public function applyAreaAgentSaveForm(Request $request)
    {
        try {
            DB::beginTransaction();
            $memberId = $this->memberId;
            $params = $request->toArray();
            $params['member_id'] = $memberId;
            $agentBaseSetting = AreaAgentBaseSetting::getCurrentSiteSetting();
            if ($agentBaseSetting->status != 1) {
                return makeApiResponseFail('无开设区域代理功能，请联系客服');
            }
            // 已使用或已申请的地区
            $usedDistrict = AreaAgentApplyFront::getUsedDistrict();
            $applyAreaType = $request->apply_area_type;
            if ($usedDistrict) {
                switch (true) {
                    case $applyAreaType == AreaAgentConstants::AreaAgentLevel_Province :
                        if ($request->apply_prov) {
                            if (in_array($request->apply_prov, $usedDistrict['prov'])) {
                                return makeApiResponse(501, '此省代区域已被代理');
                            }
                        } else {
                            throw new \Exception('请传入正确的省代区域');
                        }
                        break;
                    case $applyAreaType == AreaAgentConstants::AreaAgentLevel_City :
                        if ($request->apply_city) {
                            if (in_array($request->apply_city, $usedDistrict['city'])) {
                                return makeApiResponse(501, '此市代区域已被代理');
                            }
                        } else {
                            throw new \Exception('请传入正确的市代区域');
                        }
                        break;
                    case $applyAreaType == AreaAgentConstants::AreaAgentLevel_District :
                        if ($request->apply_district) {
                            if (in_array($request->apply_district, $usedDistrict['district'])) {
                                return makeApiResponse(501, '此区代区域已被代理');
                            }
                        } else {
                            throw new \Exception('请传入正确的区代区域');
                        }
                        break;
                }
            }
            $applyLevel = (new AreaAgentApplySetting())->getMemberApplyLevel($this->memberId);
            foreach ($applyLevel as $key => $item) {
                if ($key == $applyAreaType) {
                    if ($applyLevel[$key]['status'] == 0) return makeApiResponse(502, '不符合条件,不允许成为区域代理');
                }
            }
            (new AreaAgentApplyFront())->saveFrom($params);
            DB::commit();
            return makeServiceResultSuccess('ok');
        } catch (\Exception $e) {
            DB::rollBack();
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取申请表单
     * @param Request $request
     * @return array
     */
    public function getAreaAgentApplyForm()
    {
        try {
            $agentBaseSetting = AreaAgentBaseSetting::getCurrentSiteSetting();
            if ($agentBaseSetting->status != 1) {
                return makeApiResponseFail('无开设区域代理功能，请联系客服');
            }
            $data = (new  AreaAgentApplySetting())->getApplyForm();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getUsedDistrict()
    {
        try {
            $agentBaseSetting = AreaAgentBaseSetting::getCurrentSiteSetting();
            if ($agentBaseSetting->status != 1) {
                return makeApiResponseFail('无开设区域代理功能，请联系客服');
            }
            $data = AreaAgentApplyFront::getUsedDistrict();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


    /**
     * 代理代理概况
     * @param Request $request
     * @return array
     */
    public function getAgentTeamInfo(Request $request)
    {
        try {
            $memberId = $this->memberId;
            $member = new Member($memberId);
            if (!$member->checkExist()) {
                return makeServiceResultFail("不是会员");
            }
            $memberModel = $member->getModel();
            $agentor = new Agentor($memberId);
            if (!$agentor->checkExist()) {
                return makeServiceResultFail("未申请代理");
            }
            $data = $agentor->getCountData([
                'team' => true,
                'team_contain_self' => true,
                'order_reward' => true,
                'sale_reward' => true,
                'recommend_reward' => true,
                'performance_reward' => true,
            ], true);
            $data['base_setting'] = AgentBaseSetting::getCurrentSiteSettingFormat();
            $data['sale_reward_setting'] = AgentSaleRewardSetting::getCurrentSiteSetting();
            unset($data['sale_reward_setting']['commision']);
            $data['recommend_reward_setting'] = AgentRecommendRewardSetting::getCurrentSiteSetting();
            unset($data['recommend_reward_setting']['commision']);
            $data['performance_reward_setting'] = AgentPerformanceRewardSetting::getCurrentSiteSetting();
            $data['member'] = [
                'id' => $memberModel->id,
                'name' => $memberModel->name,
                'nickname' => $memberModel->nickname,
                'headurl' => $memberModel->headurl,
                'mobile' => $memberModel->mobile,
                'agent_level' => $memberModel->agent_level,
                'agent_level_text' => Constants::getAgentLevelTextForFront(intval($memberModel->agent_level)),
            ];
            $agent_parent = (new Member($memberModel->agent_parent_id))->getModel();
            if ($agent_parent) {
                $parent_info = [
                    'id' => $agent_parent->id,
                    'name' => $agent_parent->name,
                    'nickname' => $agent_parent->nickname,
                    'headurl' => $agent_parent->headurl,
                    'mobile' => $agent_parent->mobile,
                ];
            } else {
                $store = (new StoreConfig())->getInfo()['data'];
                $parent_info = [
                    'name' => '公司',
                    'nickname' => '公司',
                    'mobile' => $store->custom_mobile,
                ];
            }
            $data['parent_info'] = $parent_info;
            return makeApiResponseSuccess('ok', $data);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}



