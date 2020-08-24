<?php
/**
 * 区域代理基础设置接口
 * User: liyaohui
 * Date: 2020/5/19
 * Time: 16:07
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\AreaAgent;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentBaseSetting;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use Illuminate\Http\Request;

class AreaAgentBaseSettingController extends BaseAdminController
{
    private $settingObj;

    public function __construct()
    {
        $this->settingObj = new AreaAgentBaseSetting();
    }

    /**
     * 获取设置详情
     * @return array
     */
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

    /**
     * 编辑设置
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            $data = $request->all();
            $this->settingObj->save($data);
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}