<?php
/**
 * 区域代理加盟设置接口
 * User: liyaohui
 * Date: 2020/5/19
 * Time: 16:26
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\AreaAgent;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentApplySetting;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentBaseSetting;
use Illuminate\Http\Request;

class AreaAgentApplySettingController extends BaseAdminController
{
    /**
     * 获取代理申请设置信息
     * @return array
     */
    public function getInfo()
    {
        try{
            // 获取区域代理设置
            $areaAgentBaseSetting = AreaAgentBaseSetting::getCurrentSiteSetting();
            $areaAgentBaseSetting = !!$areaAgentBaseSetting->status; // 是否开启
            // 获取代理设置
            $agentBaseSetting = AgentBaseSetting::getCurrentSiteSetting();
            // 获取区域代理申请设置
            $applySetting = (new AreaAgentApplySetting())->getInfo();
            $applySetting['apply_level'] = json_decode($applySetting['apply_level'], true);
            $applySetting['extend_fields'] = $applySetting['extend_fields'] ? json_decode($applySetting['extend_fields'], true) : $applySetting['extend_fields'];
            $applySetting['self_level'] = $applySetting['self_level'] ? json_decode($applySetting['self_level'], true) : $applySetting['self_level'];
            return makeApiResponseSuccess('ok', ['base_setting' => $areaAgentBaseSetting, 'agent_level' => $agentBaseSetting->level, 'apply_setting' => $applySetting]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存代理设置信息
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            $params = $request->all();
            $save = (new AreaAgentApplySetting())->save($params);
            if ($save) {
                return makeServiceResultSuccess('ok');
            } else {
                return makeServiceResultFail('保存失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}