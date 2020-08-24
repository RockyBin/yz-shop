<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformanceRewardRule;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformanceRewardSetting;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerPerformanceRewardRuleModel;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use Illuminate\Http\Request;
use YZ\Core\Site\Site;
use Illuminate\Support\Facades\DB;

class DealerPerformanceRewardSettingController extends BaseAdminController
{
    /**
     * 获取配置
     * @return array
     */
    public function getInfo()
    {
        try {
            $setting = DealerPerformanceRewardSetting::getCurrentSiteSetting();
            $ruleData = DealerPerformanceRewardRule::getList([]);
            if ($ruleData) {
                foreach ($ruleData['list'] as $ruleItem) {
                    $ruleItem->target = moneyCent2Yuan($ruleItem->target);
                    $ruleItem->reward = moneyCent2Yuan($ruleItem->reward);
                }
                $setting->rules = $ruleData['list'];
            } else {
                $setting->rules = [];
            }
            // 订单设置里面的是否打开了售后
            $orderConfig = new OrderConfig();
            $setting->aftersale_isopen = $orderConfig->getInfo()->aftersale_isopen;

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
            DB::beginTransaction();
            $rules = $request->get('rules');
            $param = $request->toArray();
            $setting = new DealerPerformanceRewardSetting();
            unset($param['rules']);
            $setting->save($param);

            // 清理所有规则
            DealerPerformanceRewardRuleModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->delete();
            if ($rules && is_array($rules) && count($rules) > 0) {
                // 重新插入数据
                foreach ($rules as $rule) {
                    $rule['target'] = moneyYuan2Cent($rule['target']);
                    $rule['reward'] = moneyYuan2Cent($rule['reward']);
                    $levelCheck=DealerLevelModel::query()->where('id',$rule['dealer_level'])->where('site_id',Site::getCurrentSite()->getSiteId())->count();
                    if($levelCheck<=0) throw  new \Exception(trans('无此等级'));;
                    $agentPerformanceRewardRule = new DealerPerformanceRewardRule();
                    $agentPerformanceRewardRule->add($rule);
                }
            }
            DB::commit();
            return makeApiResponseSuccess(trans('shop-admin.common.save_ok'));
        } catch (\Exception $ex) {
            DB::rollBack();
            return makeApiResponseError($ex);
        }
    }
}