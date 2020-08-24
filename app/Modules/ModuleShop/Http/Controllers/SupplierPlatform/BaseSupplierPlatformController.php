<?php

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform;

use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformAdmin;
use Illuminate\Routing\Controller as BaseController;
use YZ\Core\Site\Site;

class BaseSupplierPlatformController extends BaseController
{
    protected $siteId = 0; // 站点id
    protected $memberId = 0; // 会员id
    protected $supplierAdminId = 0; // 员工id

    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
    }

    public function beforeAction($action = '')
    {
        $check = PermCheck::check();
        if ($check !== true) {
            return $check;
        }
        $admin = SupplierPlatformAdmin::getLoginedSupplierPlatformAdmin();
        $this->siteId = $admin['site_id'];
        $this->memberId = SupplierPlatformAdmin::getLoginedSupplierPlatformAdminMemberId();
        $this->supplierAdminId = SupplierPlatformAdmin::getLoginedSupplierPlatformAdminId();
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