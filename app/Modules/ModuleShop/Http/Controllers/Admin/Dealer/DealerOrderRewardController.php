<?php
/**
 * 订货返现奖-后台Api
 * Created by Sound.
 */
namespace App\Modules\ModuleShop\Http\Controllers\Admin\Dealer;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use App\Modules\ModuleShop\Libs\Dealer\DealerOrderRewardService;
use App\Modules\ModuleShop\Libs\Entities\QueryParameters\DealerOrderRewardQueryParameter;
use Exception;
use Illuminate\Http\Request;
use ReflectionException;
use YZ\Core\Entities\Utils\PaginationEntity;
use YZ\Core\Site\Site;
use YZ\Core\Traits\InjectTrait;

class DealerOrderRewardController extends BaseSiteAdminController
{
    use InjectTrait;

    /**
     * @var DealerOrderRewardService
     */
    private $dealerOrderRewardService;

    /**
     * DealerOrderRewardController constructor.
     * @param DealerOrderRewardService $dealerOrderRewardService
     * @throws ReflectionException
     */
    public function __construct(DealerOrderRewardService $dealerOrderRewardService)
    {
        $this->inject(get_defined_vars());
    }

    /**
     * 获取订货返现奖分页列表
     * @param Request $request
     * @param PaginationEntity $paginationEntity
     * @param DealerOrderRewardQueryParameter $dealerOrderRewardQueryParameter
     * @return array
     */
    public function getList(Request $request, PaginationEntity $paginationEntity, DealerOrderRewardQueryParameter $dealerOrderRewardQueryParameter)
    {
        try {
            // 设置site_id
            $dealerOrderRewardQueryParameter->site_id = Site::getCurrentSite()->getSiteId();
            // 设用Service的getPaginationByAdmin方法，并返回方法封装了的返回值数据给前端。
            return makeApiResponseSuccess(trans('shop-admin.common.action_ok'), $this->dealerOrderRewardService->getPaginationByAdmin($paginationEntity, $dealerOrderRewardQueryParameter));
        } catch (Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 导出
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        try {
            // 创建PaginationEntity实例并从$request->all()中抓取对应的属性的参数值压入PaginationEntity实例。
            $paginationEntity = new PaginationEntity($request);
            // 创建DealerOrderRewardQueryParameter实例并从$request->all()中抓取对应的属性的参数值压入DealerOrderRewardQueryParameter实例。
            $dealerOrderRewardQueryParameter = new DealerOrderRewardQueryParameter($request);

            $export = $this->dealerOrderRewardService->exportFileByQuery($paginationEntity, $dealerOrderRewardQueryParameter);
            return $export->export();
        } catch (Exception $e) {
            return makeApiResponseError($e);
        }
    }
}