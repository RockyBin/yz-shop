<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Agent\AgentRecommendRewardSetting;
use Illuminate\Http\Request;

class AgentRecommendRewardSettingController extends BaseAdminController
{
    /**
     * 获取配置
     * @return array
     */
    public function getInfo()
    {
        try {
            $data = AgentRecommendRewardSetting::getCurrentSiteSetting()->ToArray();
            if (is_array($data['commision'])) {
                if (count($data['commision']) > 0) {
                    foreach ($data['commision'] as &$commisionItem) {
                        if (array_key_exists('reward', $commisionItem)) {
                            $commisionItem['reward'] = moneyCent2Yuan($commisionItem['reward']);
                        }
                    }
                } else {
                    $data['commision'] = null;
                }
            }
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $data);
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
            $setting = new AgentRecommendRewardSetting();
            $param = $request->toArray();
            if (array_key_exists('commision', $param)) {
                $commision = $param['commision'];
                if (!$commision || !is_array($commision)) {
                    $commision = [];
                }
                foreach ($commision as &$commisionItem) {
                    if (array_key_exists('reward', $commisionItem)) {
                        $commisionItem['reward'] = moneyYuan2Cent($commisionItem['reward']);
                    }
                }
                $param['commision'] = json_encode($commision);
            }
            $setting->save($param);
            return makeApiResponseSuccess(trans('shop-admin.common.save_ok'));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}