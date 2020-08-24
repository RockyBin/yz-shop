<?php
namespace App\Modules\ModuleShop\Http\Controllers\Crm;

use Illuminate\Http\Request;
use YZ\Core\Site\Site;

class IndexController extends BaseCrmController
{
    public function index(Request $request)
    {
        try {
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取网站信息
     * @return array
     */
    public function getSiteInfo()
    {
        try {
            return makeApiResponseSuccess('ok', [
                'siteComdataPath' => Site::getSiteComdataDir(),
            ]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}