<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use YZ\Core\Model\MemberModel;
use YZ\Core\Member\Auth;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;

class FromadminController extends BaseFrontController
{
    /**
     * 用于给前台通过ajax设置推荐者的ID
     * @param Request $request
     * @return void
     */
    public function setFromadmin(Request $request)
    {
        $fromadmin = Session::get('fromadmin');
        $data = [];
        if ($fromadmin) {
            $data['fromadmin'] = intval($fromadmin);
        }
        // 方法为空，因为在 BaseFrontController 里已经实现了相应方法
        return makeApiResponseSuccess("ok", $data);
    }

    /**
     * 用于给前台通过ajax设置推荐者的ID
     * @param Request $request
     * @return void
     */
    public function getFromadmin(Request $request)
    {
        $fromadmin = Session::get('fromadmin');
        return "fromadmin=$fromadmin";
    }

}