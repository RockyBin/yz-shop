<?php

namespace App\Http\Controllers\WxWork;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Request;
use YZ\Core\Weixin\WxWork;

class ServeController extends BaseController
{
    /**
     * 企业微信接收消息入口
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {
        $wxwork = new WxWork();
        $response = $wxwork->serve();
        return $response;
    }
}