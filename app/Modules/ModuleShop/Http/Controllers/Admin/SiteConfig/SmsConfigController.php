<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\SiteConfig;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

class SmsConfigController extends BaseAdminController
{
    private $SmsConfigObj;

    public function __construct()
    {
        $this->SmsConfigObj = new \App\Modules\ModuleShop\Libs\SiteConfig\SmsConfig();
    }

    /**
     * 展示某一条记录
     * @return Response
     */
    public function getInfo()
    {
        try {
            $data = $this->SmsConfigObj->getInfo();
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 新增设置
     * @return Response
     */
    public function add(Request $request)
    {
        try {
            $result = $this->SmsConfigObj->add($request->all());
            return makeApiResponseSuccess('ok', $result);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 编辑设置
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            $this->SmsConfigObj->edit($request->all());
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
