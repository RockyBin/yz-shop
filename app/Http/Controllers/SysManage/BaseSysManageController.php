<?php
namespace App\Http\Controllers\SysManage;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use YZ\Core\SysManage\SysAdmin;

class BaseSysManageController extends BaseController
{
	public function beforeAction($action = ''){
		\YZ\Core\SysManage\PermCheck::check();
	}

    public function callAction($method, $parameters){
		if(method_exists($this,'beforeAction')) call_user_func_array([$this, 'beforeAction'], ['action' => $method]);
		$return = parent::callAction($method, $parameters);
		if(method_exists($this,'afterAction')) call_user_func_array([$this, 'afterAction'], ['action' => $method]);
		return $return;
	}
}