<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\SiteConfig;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;

class PayConfigController extends BaseAdminController
{
    private $PayConfigObj;

    /**
     * 根据不同的type初始化PayConfig
     * @return Response
     */
    function initPayConfigObj($pay_config_type)
    {
        $this->PayConfigObj = new PayConfig($pay_config_type);
    }

    /**
     * 展示某一条记录
     * @return Response
     */
    public function getInfo(Request $request)
    {
        try {
            $this->initPayConfigObj($request->pay_config_type);
            $data = $this->PayConfigObj->getInfo();
            return makeApiResponseSuccess('ok', $data);
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
            $this->initPayConfigObj($request->pay_config_type);
            $this->PayConfigObj->edit($request->all());
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
