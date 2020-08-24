<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

class DealerBaseSettingController extends BaseAdminController
{
    private $settingObj;

    public function __construct()
    {
        $this->settingObj = new \App\Modules\ModuleShop\Libs\Dealer\DealerBaseSetting();
    }

    public function getInfo()
    {
        try {
            $info = $this->settingObj->getInfo();
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
