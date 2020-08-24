<?php

namespace App\Http\Controllers\WxWork\Open;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Request;
use YZ\Core\Weixin\WxWorkOpen;

class SystemEventCallbackController extends BaseController
{
    /**
     * 企业微信服务商系统事件接收URL入口
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {
        $corpId = Request::route('corp_id');
        if(!$corpId){
            return "缺少参数 corp_id";
        }
        $wxwork = new WxWorkOpen($corpId);
        $response = $wxwork->serve();
        return $response;
    }
}