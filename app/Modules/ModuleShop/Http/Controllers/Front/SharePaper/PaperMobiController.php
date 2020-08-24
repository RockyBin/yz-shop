<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\SharePaper;

use App\Modules\ModuleShop\Libs\SharePaper\Mobi\Paper;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;

class PaperMobiController extends BaseFrontController
{
    public function __construct()
    {

    }

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
            $type = $request->get('type');
            $terminal = $request->get('terminal',getCurrentTerminal());
            if($id) $page = new Paper($id);
            else $page = Paper::getDefaultPaper();
            return $page->render($memberId,$type,$terminal);
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
            $type = $request->get('type');
			$returnType = intval($request->get('returnType',2));
            $memberId = $request->get('member_id');
            $terminal = $request->get('terminal',getCurrentTerminal());
            if(is_numeric($type)) $page = Paper::getTypePaper($type);
            else $page = Paper::getDefaultPaper();
            return $page->renderImage($memberId,$returnType,false,$type,$terminal);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}