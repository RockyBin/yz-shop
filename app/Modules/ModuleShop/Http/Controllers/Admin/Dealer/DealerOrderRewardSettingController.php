<?php
/**
 * 订货返现奖设置-后台Api
 * Created by Sound.
 */
namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerOrderRewardSettingService;
use App\Modules\ModuleShop\Libs\Entities\DealerOrderRewardSettingEntity;
use Exception;
use Illuminate\Http\Request;
use ReflectionException;
use YZ\Core\Traits\InjectTrait;

class DealerOrderRewardSettingController extends BaseSiteAdminController
{
    use InjectTrait;

    /**
     * @var DealerOrderRewardSettingService
     */
    private $dealerOrderRewardSettingService;

    /**
     * DealerOrderRewardSettingController constructor.
     * @param DealerOrderRewardSettingService $dealerOrderRewardSettingService
     * @throws ReflectionException
     */
    public function __construct(DealerOrderRewardSettingService $dealerOrderRewardSettingService)
    {
        $this->inject(get_defined_vars());
    }

    /**
     * 获取当前站点的订货返现奖设置
     * @return array
     */
    public function getInfo()
    {
        try {
            $entity = $this->dealerOrderRewardSettingService->getSettingBySite();
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $entity);
        } catch (Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存当前站点的订货返现奖设置
     * @param Request $request
     * @param DealerOrderRewardSettingEntity $dealerOrderRewardSettingEntity
     * @return array
     */
    public function save(Request $request, DealerOrderRewardSettingEntity $dealerOrderRewardSettingEntity)
    {
        try {
            $this->dealerOrderRewardSettingService->saveSetting($dealerOrderRewardSettingEntity);
            return makeApiResponseSuccess(trans('shop-admin.common.save_ok'));
        } catch (Exception $e) {
            return makeApiResponseError($e);
        }
    }
}