<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Distribution;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

class DistributionSettingController extends BaseAdminController
{
    private $settingObj;

    public function __construct()
    {
        $this->settingObj = new \App\Modules\ModuleShop\Libs\Distribution\DistributionSetting();
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

    /**
     * 获取基础设置
     * @return array
     */
    public function getBaseInfo()
    {
        try {
            $info = $this->settingObj->getBaseInfo();
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function edit(Request $request)
    {
        try {
            $data = $request->all();
            unset($data['baseinfo']['site_id']);
            unset($data['forminfo']['site_id']);
            $info = $this->settingObj->save($data);
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 编辑基础设置
     * @param Request $request
     * @return array
     */
    public function editBase(Request $request)
    {
        try {
            $data = $request->all();
            $save = $this->settingObj->saveBase($data);
            if ($save) {
                return makeApiResponseSuccess('ok', $save);
            } else {
                return makeApiResponse(400, '保存失败');
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
