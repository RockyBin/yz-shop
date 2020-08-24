<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceRewardRule;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceRewardSetting;
use App\Modules\ModuleShop\Libs\Model\AgentPerformanceRewardRuleModel;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use Illuminate\Http\Request;
use YZ\Core\Site\Site;

class AgentPerformanceRewardSettingController extends BaseAdminController
{
    /**
     * 获取配置
     * @return array
     */
    public function getInfo()
    {
        try {
            $setting = AgentPerformanceRewardSetting::getCurrentSiteSetting();
            $ruleData = AgentPerformanceRewardRule::getList([]);
            if ($ruleData) {
                foreach ($ruleData['list'] as $ruleItem){
                    $ruleItem->target = moneyCent2Yuan($ruleItem->target);
                    $ruleItem->reward = moneyCent2Yuan($ruleItem->reward);
                }
                $setting->rules = $ruleData['list'];
            } else {
                $setting->rules = [];
            }
            // 订单设置里面的是否打开了售后
            $orderConfig= new OrderConfig();
            $setting->aftersale_isopen=$orderConfig->getInfo()->aftersale_isopen;

            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $setting);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存配置
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $rules = $request->get('rules');
            $param = $request->toArray();
            $setting = new AgentPerformanceRewardSetting();
            unset($param['rules']);
            $setting->save($param);

            // 清理所有规则
            AgentPerformanceRewardRuleModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->delete();
            if ($rules && is_array($rules) && count($rules) > 0) {
                // 重新插入数据
                foreach ($rules as $rule) {
                    $rule['target'] = moneyYuan2Cent($rule['target']);
                    $rule['reward'] = moneyYuan2Cent($rule['reward']);
                    $agentPerformanceRewardRule = new AgentPerformanceRewardRule();
                    $agentPerformanceRewardRule->add($rule);
                }
            }

            return makeApiResponseSuccess(trans('shop-admin.common.save_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}