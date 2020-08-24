<?php
/**
 * 销售奖设置接口
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerSaleRewardSetting;
use Illuminate\Http\Request;

class DealerSaleRewardSettingController extends BaseAdminController
{
    /**
     * 获取配置
     * @return array
     */
    public function getInfo()
    {
        try {
            $setting = DealerSaleRewardSetting::getCurrentSiteSetting();
            if ($setting['reward_rule'] && $rewardRule = json_decode($setting['reward_rule'], true)) {
                $setting['reward_rule'] = $rewardRule;
            } else {
                $setting['reward_rule'] = [];
            }

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
            $data = [
                'enable' => $request->input('enable', 0),
                'auto_check' => $request->input('auto_check', 0),
                'payer' => $request->input('payer', 0),
                'reward_rule' => $request->input('reward_rule', ''),
            ];
            (new DealerSaleRewardSetting())->save($data);
            return makeApiResponseSuccess(trans('shop-admin.common.save_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}