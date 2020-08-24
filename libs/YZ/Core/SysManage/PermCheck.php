<?
namespace YZ\Core\SysManage;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Response;

class PermCheck
{
    public static function check()
    {
		if(!SysAdmin::hasLogined()){
			echoApiResponse(401,'请先登录',['needlogin' => 1]);
			myexit();
		}
        $action = Route::currentRouteAction();
        $action = strtolower($action);
        if(substr($action,0,1) == '\\') $action = substr($action,1);
        $permconfig = include(__DIR__.'/PermConfig.php');
        $needPerm = '';
        foreach ($permconfig as $key => $val){
            $key = strtolower($key);
            if(substr($key,0,1) == '\\') $key = substr($key,1);
            if($key == $action){
                $needPerm = $val;
            }
        }
        if($needPerm){
            if(!SysAdmin::hasPerm($needPerm)){
				echoApiResponse(403,'您没有执行此操作的权限('.$needPerm.')');
				myexit();
            }
        }
    }
}
?>