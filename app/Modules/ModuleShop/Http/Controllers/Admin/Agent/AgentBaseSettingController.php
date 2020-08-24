<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Agent;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;

class AgentBaseSettingController extends BaseAdminController
{
    private $settingObj;

    public function __construct()
    {
        $this->settingObj = new \App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting();
    }

    public function getInfo()
    {
        try {
            $info = $this->settingObj->getInfo();
            //查询是否有开启维权
            $orderConfig = new OrderConfig();
            $orderConfigInfo = $orderConfig->getInfo();
            $info['aftersale_isopen'] = $orderConfigInfo->aftersale_isopen;
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function edit(Request $request)
    {
        try {
            $data = $request->all();
            $info = $this->settingObj->save($data);
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
