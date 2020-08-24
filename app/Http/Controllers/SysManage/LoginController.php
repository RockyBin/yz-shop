<?php
namespace App\Http\Controllers\SysManage;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Cache;
use YZ\Core\Common\VerifyCode;
use YZ\Core\Constants;
use YZ\Core\SysManage\SysAdmin;

class LoginController extends BaseController
{
    public function __construct()
    {

    }

    /**
     * 系统管理员登录
     * @return mixed
     */
    public function login(){
        try {
            $username = Request::get('userName');
            $password = Request::get('password');
            $vcode = Request::get('vcode');
            if (!VerifyCode::checkImgCode($vcode)) {
                //throw new \Exception('验证码不正确');
            }
            if (!SysAdmin::login($username, $password)) {
                throw new \Exception('登录失败');
            }
            return makeApiResponse(200,'ok',['userInfo' => SysAdmin::getLoginedAdmin()]);
        }catch (\Exception $ex){
            return makeApiResponse(500,$ex->getMessage());
        }
    }

	public function logout(){
        try {
            if (!SysAdmin::logout()) {
                throw new \Exception('登录失败');
            }
            return makeApiResponse(200,'ok');
        }catch (\Exception $ex){
            return makeApiResponse(500,$ex->getMessage());
        }
    }

	public function getUserInfo(){
        try {
            if (!SysAdmin::hasLogined()) {
                //throw new \Exception('未登录');
				return makeApiResponse(403,'未登录',['needlogin' => 1]);
            }
            return makeApiResponse(200,'ok',['userInfo' => SysAdmin::getLoginedAdmin()]);
        }catch (\Exception $ex){
            return makeApiResponse(500,$ex->getMessage());
        }
    }
}