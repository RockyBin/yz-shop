<?php
namespace App\Http\Controllers\SiteAdmin;

use YZ\Core\Controllers\BaseController;
use YZ\Core\Site\SiteAdminPermCheck;

class BaseSiteAdminController extends BaseController
{
	public function beforeAction($action = ''){
		return SiteAdminPermCheck::check();
	}

    public function callAction($method, $parameters){
		if(method_exists($this,'beforeAction')) {
            $before = call_user_func_array([$this, 'beforeAction'], ['action' => $method]);
            if ($before !== true) {
                return $before;
            }
        }
		$return = parent::callAction($method, $parameters);
		if(method_exists($this,'afterAction')) call_user_func_array([$this, 'afterAction'], ['action' => $method]);
		return $return;
	}
}