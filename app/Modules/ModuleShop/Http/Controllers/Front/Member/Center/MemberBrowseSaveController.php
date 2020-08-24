<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Center;

use Illuminate\Http\Request;
use YZ\Core\Member\Auth;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController as BaseController;
use App\Modules\ModuleShop\Libs\Browse\Browse;

/**
 * 浏览记录添加（无需登录）
 * Class MemberBrowseSaveController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\Center
 */
class MemberBrowseSaveController extends BaseController
{
    protected $siteId = 0;
    private $Borwse = null;

    public function __construct()
    {
        parent::__construct();
        $this->siteId = Site::getCurrentSite()->getSiteId();
        $this->Borwse = new Browse();
    }

    /**
     * 添加浏览记录
     * @param Request $request
     * @return array
     */
    public function addBrowse(Request $request)
    {
        try {
            if (!$request->product_id) {
                return makeApiResponseFail('数据异常：ID不能不空');
            }
            $memberId = Auth::hasLogin();
            $data = [];
            if ($memberId) {
                $params = $request->toArray();
                $params['member_id'] = $memberId;
                $data = $this->Borwse->save($params);
            }
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}