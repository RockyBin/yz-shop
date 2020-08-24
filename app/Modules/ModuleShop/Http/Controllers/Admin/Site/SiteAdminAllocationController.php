<?php
namespace App\Modules\ModuleShop\Http\Controllers\Admin\Site;

use App\Http\Controllers\SiteAdmin\BaseSiteAdminController;
use Illuminate\Http\Request;
use YZ\Core\Site\SiteAdminAllocation;

class SiteAdminAllocationController extends BaseSiteAdminController
{
    /**
     * 保存
     * @param Request $request
     * @return array
     */
    public function save(Request $request){
        try {
            $allocation = new SiteAdminAllocation($request->get('site_id', getCurrentSiteId()));
            $save = $allocation->save($request->all());
            if ($save !== false) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponseFail('保存失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request) {
        try {
            $allocation = new SiteAdminAllocation($request->get('site_id', getCurrentSiteId()));
            $info = $allocation->getInfo();
            return makeApiResponseSuccess('ok',$info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}