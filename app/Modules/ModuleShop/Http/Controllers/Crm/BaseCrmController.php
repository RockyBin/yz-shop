<?php

namespace App\Modules\ModuleShop\Http\Controllers\Crm;

use Illuminate\Routing\Controller as BaseController;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdmin;

class BaseCrmController extends BaseController
{
    protected $siteId = 0; // 站点id
    protected $adminId = 0; // 员工id

    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
    }

    public function beforeAction($action = '')
    {
        //检测登录状态
        $admin = SiteAdmin::getLoginedAdmin();
        if (!$admin || !SiteAdmin::hasLogined()) {
			return makeApiResponse(401, '请先登录', ['needlogin' => 1]);
        }
        $this->siteId = $admin['site_id'];
        $this->adminId = SiteAdmin::getLoginedAdminId();
        return true;
    }

    public function callAction($method, $parameters)
    {
        if (method_exists($this, 'beforeAction')) {
            $befor = $this->beforeAction();
            if ($befor !== true) {
                return $befor;
            }
        };
        $return = parent::callAction($method, $parameters);
        if (method_exists($this, 'afterAction')) call_user_func_array([$this, 'afterAction'], ['action' => $method]);
        return $return;
    }
}