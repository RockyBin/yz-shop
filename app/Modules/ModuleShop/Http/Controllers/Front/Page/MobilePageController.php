<?php
namespace App\Modules\ModuleShop\Http\Controllers\Front\Page;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\UI\NavMobi;
use App\Modules\ModuleShop\Libs\UI\PageMobi;
use App\Modules\ModuleShop\Libs\UI\Popup;
use App\Modules\ModuleShop\Libs\Wx\WxSubscribeSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class MobilePageController extends BaseFrontController
{
    /**
     * 获取页面的模块，基本信息，导航等，用于前台渲染
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try{
            $id = $request->get('id');
            // 会员中心页面 根据type获取
            $type = $request->get('type', 1);
            $showNav = $request->get('showNav');
            $showPopup = $request->get('showPopup');
            if($id) $page = new PageMobi($id);
            else $page = PageMobi::getDefaultPage($type,$request->get("device_type"));
            $fromCache = $request->get('fromCache');
            $result = $page->render($fromCache);
            // 没有id的时候要强制设为0
            if (!$result['pageInfo']['id']) {
                if (!$result['pageInfo']) {
                    $result['pageInfo'] = $page->getModel();
                }
                $result['pageInfo'] = $result['pageInfo'] ? $result['pageInfo']->toArray() : $result['pageInfo'];
                $result['pageInfo']['id'] = 0;
            }
            if($showNav) $navInfo = NavMobi::getDefaultNav($request->get("device_type"))->render();
            if($showPopup && $page->getModel()->type == Constants::PageMobiType_Home) $popupInfo = Popup::getDefaultPopup($request->get("device_type"))->render();
            //没传pageid，表示加载首页
            if(!$id){
                $subscribe = (new WxSubscribeSetting())->getSubscribeInfo();
            }
            return makeApiResponseSuccess('ok',['modules' => $result['moduleInfo'],'pageInfo' => $result['pageInfo'],'navInfo' => $navInfo,'popupInfo' => $popupInfo,'subscribe' => $subscribe]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取页面的基本属性信息
     * @param Request $request
     * @return array
     */
    public function getBaseInfo(Request $request)
    {
        try{
            header("Access-Control-Allow-Origin: *");
            $id = $request->get('id');
            if($id) $page = new PageMobi($id);
            else $page = PageMobi::getDefaultPage(Constants::PageMobiType_Home,$request->get("device_type"));
            return makeApiResponseSuccess('ok',['pageInfo' => $page->getModel()]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}