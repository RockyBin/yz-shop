<?php
/**
 * 快递设置接口
 * User: liyaohui
 * Date: 2020/7/9
 * Time: 16:05
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Express;


use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Express\ExpressSetting;
use Illuminate\Http\Request;
use YZ\Core\Site\Site;

class ExpressSettingController extends BaseAdminController
{
    protected $expressSetting;

    /**
     * ExpressSettingController constructor.
     */
    public function __construct()
    {
        $this->expressSetting = new ExpressSetting();
    }

    /**
     * 获取设置详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $info = $this->expressSetting->getInfo();
            $info['domain_list'] = Site::getCurrentSite()->getUserDomain(true);
            return makeApiResponseSuccess('ok', $info);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存设置
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            $save = $this->expressSetting->edit($request->all());
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '保存失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取授权地址
     * @return array
     */
    public function authorizeUrl()
    {
        try {
            $url = $this->expressSetting->authorize();
            return makeApiResponseSuccess('ok', ['url' => $url]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 刷新accessToken
     * @return array
     */
    public function refreshToken()
    {
        try {
            $save = $this->expressSetting->refreshToken();
            if ($save) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, '授权失败');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}