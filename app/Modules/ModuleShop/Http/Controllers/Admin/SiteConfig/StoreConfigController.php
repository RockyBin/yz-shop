<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\SiteConfig;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

class StoreConfigController extends BaseAdminController
{
    private $StoreConfigObj;

    public function __construct()
    {
        $this->StoreConfigObj = new \App\Modules\ModuleShop\Libs\SiteConfig\StoreConfig();
    }


    /**
     * 展示某一条记录
     * @return Response
     */
    public function getInfo()
    {
        try {
            $data= $this->StoreConfigObj->getInfo();
            return makeApiResponse(200, 'ok', $data['data']);
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    /**
     * 新增设置
     * @return Response
     */
    public function add(Request $request)
    {
        try {
            $result = $this->StoreConfigObj->add($request->all());
            return makeApiResponse(200,$result, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    /**
     * 编辑设置
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            $this->StoreConfigObj->edit($request->all());
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    /**
     * 上传二维码图片
     * @param Request $request
     * @return array
     */
    public function uploadQrcodeImg(Request $request){
        try {
            $qrcodeUrl = $this->StoreConfigObj->uploadQrcodeImg($request->file('qrcode_img'));
            return makeApiResponseSuccess('ok', ['qrcode' => $qrcodeUrl]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}
