<?php
/**
 * 推荐奖设置接口
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerRecommendRewardSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealerRecommendRewardSettingController extends BaseAdminController
{
    /**
     * 获取配置
     * @return array
     */
    public function getInfo()
    {
        try {
            $setting = DealerRecommendRewardSetting::getCurrentSiteSetting();
            if ($setting['reward_rule'] && $rewardRule = json_decode($setting['reward_rule'], true)) {
                foreach ($rewardRule as &$item) {
                    $item['money'] = moneyCent2Yuan($item['money']);
                }
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
                'same_reward_payer' => $request->input('same_reward_payer', 0),
                'under_reward_payer' => $request->input('under_reward_payer', 0),
                'reward_rule' => $request->input('reward_rule', ''),
            ];
            (new DealerRecommendRewardSetting())->save($data);
            return makeApiResponseSuccess(trans('shop-admin.common.save_ok'));
        } catch (\Exception $ex) {
            DB::rollBack();
            return makeApiResponseError($ex);
        }
    }
}