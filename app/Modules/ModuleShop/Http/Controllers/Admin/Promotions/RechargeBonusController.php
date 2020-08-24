<?php

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Promotions;

use Illuminate\Http\Request;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Promotions\RechargeBonus;

class RechargeBonusController extends BaseAdminController
{
    private $siteId;
    private $instance;

    /**
     * 充值赠送优惠控制器
     */
    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
        $this->instance = new RechargeBonus($this->siteId);
    }

    /**
     * 获取充值赠送优惠设置
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $data = $this->instance->getInfo(2);
            $data = $this->instance->toYuan($data);
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 保存充值赠送优惠设置
     * @param Request $request
     * @return array
     */
    public function save(Request $request)
    {
        try {
            $data = $request->toArray();
            $data = $this->instance->toCent($data);
            $this->instance->update($data);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}