<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Member;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;

/**
 * 会员配置项Controller
 * Class MemberLevelController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Member
 */
class MemberConfigController extends BaseAdminController
{
    private $memberConfig;

    /**
     * 初始化
     * MemberConfigController constructor.
     */
    public function __construct()
    {
        $this->memberConfig = new \App\Modules\ModuleShop\Libs\Member\MemberConfig();
    }

    /**
     * 返回配置，如果没有数据，返回默认值
     * @return array
     */
    public function getInfo()
    {
        try {
            $data = $this->memberConfig->getConfig();
            $data['aftersale_isopen']=(new OrderConfig())->getInfo()['aftersale_isopen'];
            return makeApiResponseSuccess(trans("shop-admin.common.action_ok"), $data);

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存配置
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $param = $request->all();
            $config = $this->memberConfig->save($param);
            if ($config) {
                return makeApiResponseSuccess(trans("shop-admin.common.action_ok"));
            } else {
                return makeApiResponseFail(trans("shop-admin.common.action_fail"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}