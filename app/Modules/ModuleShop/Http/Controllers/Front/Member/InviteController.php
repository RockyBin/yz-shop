<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use YZ\Core\Model\MemberModel;
use YZ\Core\Member\Auth;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;

class InviteController extends BaseFrontController
{
    /**
     * 用于给前台通过ajax设置推荐者的ID
     * @param Request $request
     * @return void
     */
    public function setInvite(Request $request)
    {
        $invite = Session::get('invite');
        $data = [];
        if ($invite) {
            $data['invite'] = intval($invite);
        }
        // 方法为空，因为在 BaseFrontController 里已经实现了相应方法
        return makeApiResponseSuccess("ok", $data);
    }

    /**
     * 用于给前台通过ajax设置推荐者的ID
     * @param Request $request
     * @return void
     */
    public function getInvite(Request $request)
    {
        $invite = Session::get('invite');
        return "invite=$invite";
    }

    /**
     * 用于给广告屏搜索推荐者信息
     * @param Request $request
     * @return void
     */
    public function searchInvite(Request $request)
    {
        try {
            $query = $request->get('query');
            $query = preg_replace('/[^\d]/','',$query);
            $member = MemberModel::query()->where('site_id',getCurrentSiteId())->where(function($where) use($query){
                $where->where('id', $query)->orWhere('mobile', $query);
            })->first();
            if($member) return makeApiResponseSuccess("ok", $member);
            else return makeApiResponse(404, "找不到相应的会员");
        }catch (\Exception $ex){
            return makeApiResponseError($ex);
        }
    }
}