<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\SiteConfig;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use \App\Modules\ModuleShop\Libs\SiteConfig\FreightTemplate;

class FreightTemplateController extends BaseAdminController
{
    private $FreightTemplateObj;

    public function __construct()
    {
        $this->FreightTemplateObj = new FreightTemplate();
    }

    /**
     * 展示列表
     * @return Response
     */
    public function getList(Request $request)
    {
        try {
            $data = $this->FreightTemplateObj->getList($request->all());
            return makeApiResponse(true, 'ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    /**
     * 展示某一条记录
     * @return Response
     */
    public function getInfo(Request $request)
    {
        try {
            if ($request->id) {
                return makeApiResponse(true, 'ok', ['info' => $this->FreightTemplateObj->getInfo($request->id)]);
            } else {
                return makeApiResponse(false, '缺少ID参数');
            }
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    /**
     * 创建新的运费模板
     * @return Response
     */
    public function add(Request $request)
    {
        try {
            if ($this->FreightTemplateObj->checkTemplateName($request->template_name)) {
                return makeApiResponse(false, '运费模板名称重复');
            }
            $this->FreightTemplateObj->add($request->all());
            return makeApiResponse(true, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    /**
     * 编辑一条运费模板
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(false, '缺少ID参数');
            }
            if ($this->FreightTemplateObj->checkTemplateName($request->template_name, $request->id)) {
                return makeApiResponse(false, '运费模板名称重复');
            }

            $this->FreightTemplateObj->edit($request->all());
            return makeApiResponse(true, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    /**
     * 删除运费模板
     * @param  Request id 模板ID
     * @return Response
     */
    public function delete(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(false, '缺少ID参数');
            }
            if ($this->FreightTemplateObj->checkHaveUse($request->id)) {
                return makeApiResponse(false, '此模板正在被使用，不能删除');
            }
            $this->FreightTemplateObj->delete($request->id);
            return makeApiResponse(true, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    /**
     * 生成地址JS文件
     * @return Response
     */
    public function getDistrictJs()
    {
        try {
            $aa = $this->FreightTemplateObj->getDistrictJs();
            var_dump($aa);
            // makeApiResponse(true,'ok',['list'=>$aa]);
        } catch (\Exception $ex) {
            return makeApiResponse(false, $ex->getMessage());
        }
    }

    public function getFreightTemplateList()
    {
        return FreightTemplate::getFreightTemplateList();
    }

}
