<?php
/**
 * 代理加盟设置接口
 * User: liyaohui
 * Date: 2019/6/27
 * Time: 11:09
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentApplySetting;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use Illuminate\Http\Request;


class AgentApplySettingController extends BaseAdminController
{
    // 获取代理申请设置信息
    public function getInfo()
    {
        try{
            // 获取设置的代理等级
            $agentBaseSetting = AgentBaseSetting::getCurrentSiteSetting();
            $agentBaseSetting = ['level' => $agentBaseSetting->level,'baseSetting'=>$agentBaseSetting->level==0?false:true];
            // 获取代理申请设置
            $applySetting = (new AgentApplySetting())->getInfo();
            $applySetting['agent_apply_level'] = json_decode($applySetting['agent_apply_level'], true);
            $applySetting['extend_fields'] = $applySetting['extend_fields'] ? json_decode($applySetting['extend_fields'], true) : $applySetting['extend_fields'];
            return makeApiResponseSuccess('ok', ['agent_base_setting' => $agentBaseSetting, 'agent_apply_setting' => $applySetting]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
    // 保存代理设置信息
    public function edit(Request $request)
    {
        try {
            $params = $request->all();
            $save = (new AgentApplySetting())->save($params);
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