<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\AuthCert;

use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Dealer\AuthCert\AuthCert;
use YZ\Core\Site\Site;

class AuthCertController extends BaseFrontController
{
    /**
     * 渲染海报
     * @param Request $request
     * @return html页面
     */
    public function render(Request $request)
    {
        try{
            $id = $request->get('id');
            $memberId = $request->get('member_id');
			$terminal = $request->get('terminal',getCurrentTerminal());
            $page = new AuthCert($id);
            return $page->render($memberId, $terminal);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
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
			$terminal = $request->get('terminal',getCurrentTerminal());
            $page = new AuthCert($id);
            return $page->renderImage($memberId, $returnType, false, $terminal);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 查询会员的授权证书
     *
     * @param Request $request
     * @return void
     */
    public function query(Request $request){
        try{
            $query = $request->get('query');
			$terminal = getCurrentTerminal();
            $cert = AuthCert::searchMemberCert($query);
            if(!$cert) {
                return makeApiResponse(404,'cert not found');
            }
			if(!$cert->image_wxapp && $terminal == \YZ\Core\Constants::TerminalType_WxApp){
				return makeApiResponse(404,'cert not exists');
			}
            if(intval($cert->agent_status) != 1) {
                return makeApiResponse(404,'代理未生效，不能查询证书');
            }
			$cert->image = Site::getSiteComdataDir().$cert->image;
			//如果是小程序，就输出小程序码
			if($terminal == \YZ\Core\Constants::TerminalType_WxApp){
				$cert->image = Site::getSiteComdataDir().$cert->image_wxapp;
			}
            return makeApiResponseSuccess('ok',['id' => $cert->id,'image' =>  $cert->image]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}