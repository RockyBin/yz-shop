<?php

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Setting;

use App\Modules\ModuleShop\Libs\Supplier\SupplierAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController as BaseController;

class SupplierPlatformBaseSettingController extends BaseController
{


    public function getInfo()
    {
        try {
            $info = SupplierAdmin::getSupplierInfo($this->memberId);
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function edit(Request $request)
    {
        try {
            $data = $request->all();
            $info = (new SupplierAdmin($this->memberId))->saveBaseSetting($data);
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $ex) {
            dd($ex);
            return makeApiResponseError($ex);
        }
    }
}
