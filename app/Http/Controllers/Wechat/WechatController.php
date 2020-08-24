<?php

namespace App\Http\Controllers\Wechat;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Request;
use YZ\Core\Weixin\OfficialAccount;

class WechatController extends BaseController
{
    /**
     * 公众号通信的入口
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index()
    {
        $wechat = new OfficialAccount();
//        $wechat->registerEvent('OnSubscribe', function ($wxobj, $user) {
//            $wxobj->sendMessage($user['openid']);
//        });
        $response = $wechat->serve();
        return $response;
    }

    public function qrcode()
    {
        $wechat = new OfficialAccount();
        $res = $wechat->qrcode(Request::get('key'), Request::get('value'));
        return $res;
    }

    public function testmsg()
    {
        $wechat = new OfficialAccount();
        $res = $wechat->sendMessage("o5aX6jrdwVREJonu2UcXuxD3zRmY");
        return $res;
    }
}