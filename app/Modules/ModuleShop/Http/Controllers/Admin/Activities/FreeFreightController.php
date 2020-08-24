<?php
namespace App\Modules\ModuleShop\Http\Controllers\Admin\Activities;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Activities\FreeFreight;
use App\Modules\ModuleShop\Libs\Agent\AgentApplySetting;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use Illuminate\Http\Request;

class FreeFreightController extends BaseAdminController
{
    // 获取代理申请设置信息
    public function getInfo()
    {
        try{
            $obj = new FreeFreight();
            $model = $obj->getModel();
            if($model) $model->money = moneyCent2Yuan($model->money);
            return makeApiResponseSuccess('ok', $model);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
    // 保存代理设置信息
    public function edit(Request $request)
    {
        try {
            $obj = new FreeFreight();
            $params = $request->all();
            $params['money'] = moneyYuan2Cent($params['money']);
            $obj->edit($params);
            return makeServiceResultSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}