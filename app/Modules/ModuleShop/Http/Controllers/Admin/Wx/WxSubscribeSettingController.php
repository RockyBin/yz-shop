<?php
namespace App\Modules\ModuleShop\Http\Controllers\Admin\Wx;

use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Wx\WxSubscribeSetting;
use Illuminate\Http\Request;

class WxSubscribeSettingController extends BaseAdminController
{
    // 获取代理申请设置信息
    public function getInfo()
    {
        try{
            $obj = new WxSubscribeSetting();
            $model = $obj->getModel();
            return makeApiResponseSuccess('ok', $model);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
    // 保存代理设置信息
    public function edit(Request $request)
    {
        try {
            $obj = new WxSubscribeSetting();
            $params = $request->all();
            $obj->edit($params);
            return makeServiceResultSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}