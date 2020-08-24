<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\DealerInvite;

use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Dealer\DealerInvite\InvitePoster;
use YZ\Core\Member\Auth;

class PosterController extends BaseFrontController
{
    /**
     * 渲染海报
     * @param Request $request
     * @return html页面
     */
    public function render(Request $request)
    {
     //   try{
            $id = $request->get('id');
            $memberId = $request->get('member_id');
            $inviteLevel = $request->get('inviteLevel');
			$terminal = $request->get('terminal',getCurrentTerminal());
            if($id) $page = new InvitePoster($id);
            else $page = InvitePoster::getDefaultPoster();
            return $page->render($memberId,$inviteLevel,$terminal);
       // } catch (\Exception $e) {
      //      return makeApiResponseError($e);
     //   }
    }

    /**
     * 渲染海报生成图片
     * @param Request $request
     * @return 返回图片的URL地址
     */
    public function renderImage(Request $request)
    {
        try{
            $id = $request->get('id');
			$returnType = intval($request->get('returnType',2));
            $memberId = $request->get('member_id');
            if(!$memberId) $memberId = Auth::hasLogin();
            $inviteLevel = $request->get('inviteLevel');
			$terminal = $request->get('terminal',getCurrentTerminal());
            if($id) $page = new InvitePoster($id);
            else $page = InvitePoster::getDefaultPoster();
            return $page->renderImage($memberId, $inviteLevel, $returnType, $terminal);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}