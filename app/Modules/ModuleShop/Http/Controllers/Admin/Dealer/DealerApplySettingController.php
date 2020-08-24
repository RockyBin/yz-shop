<?php
/**
 * 经销商加盟设置接口
 */
namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerApplySetting;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use Illuminate\Http\Request;
use YZ\Core\Site\Site;

class DealerApplySettingController extends BaseAdminController
{
    // 获取代理申请设置信息
    public function getInfo()
    {
        try{
            $site = Site::getCurrentSite();
            // 获取等级列表
            $levels = DealerLevelModel::query()->where('site_id',$site->getSiteId())->where('parent_id',0)->orderBy('weight','desc')->get();
            // 获取代理申请设置
            $applySetting = new DealerApplySetting();
            $setting = $applySetting->getInfo();
            $canApplyLevel = $applySetting->getCanApplyLevel();
            $setting['can_apply_level'] = [];
            foreach ($canApplyLevel as $item) {
                $setting['can_apply_level'][] = $item->id;
            }
            $canInviteLevel = $applySetting->getCanInviteLevel();
            $setting['can_invite_level'] = [];
            foreach ($canInviteLevel as $item) {
                $setting['can_invite_level'][] = $item->id;
            }
            $setting['extend_fields'] = $setting['extend_fields'] ? json_decode($setting['extend_fields'], true) : $setting['extend_fields'];
            $setting['can_invite_setting'] = $setting['can_invite_setting'] ? json_decode($setting['can_invite_setting'], true) : $setting['can_invite_setting'];
            return makeApiResponseSuccess('ok', ['levels' => $levels, 'apply_setting' => $setting]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
    // 保存代理设置信息
    public function edit(Request $request)
    {
        try {
            $params = $request->all();
            $save = (new DealerApplySetting())->save($params);
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