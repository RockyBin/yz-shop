<?php

namespace App\Http\Controllers\WxWork\Open;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Request;
use YZ\Core\Weixin\WxWorkOpen;

class DataCallbackController extends BaseController
{
    /**
     * 企业微信数据回调URL入口
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {

        $suiteId = Request::route('suite_id');
        if(!$suiteId){
            return "缺少参数 suite_id";
        }
        $wxwork = new WxWorkOpen('',$suiteId);
        $response = $wxwork->serve();
        return $response;
    }
}