<?php
namespace App\Http\Controllers\SysManage;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cache;
use YZ\Core\SysManage\SysAdmin;

class HomeController extends BaseSysManageController
{
	public function index(){
		return "system manage home";
	}
}