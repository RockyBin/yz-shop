<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\SiteConfig;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

class OrderConfigController extends BaseAdminController
{
    private $OrderConfigObj;

    public function __construct()
    {
        $this->OrderConfigObj = new \App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig();
    }

    /**
     * 展示某一条记录
     * @return Response
     */
    public function getInfo()
    {
        try {
            $data = $this->OrderConfigObj->getInfo();
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
            $result = $this->OrderConfigObj->add($request->all());
            return makeApiResponseSuccess($result, 'ok');
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
            $this->OrderConfigObj->edit($request->all());
            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
