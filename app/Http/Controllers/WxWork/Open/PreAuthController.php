<?php

namespace App\Http\Controllers\WxWork\Open;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Request;
use YZ\Core\Model\WxWorkAppModel;
use YZ\Core\Weixin\WxWorkOpen;

class PreAuthController extends BaseController
{
    /**
     * 获取预授权二维码
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function install()
    {
        $suiteId = Request::route('suite_id');
        if(!$suiteId){
            $model = WxWorkAppModel::query()->limit(1)->first();
            if($model){
                $suiteId = $model->suite_id;
            }
        }
        if(!$suiteId){
            return "缺少参数 suite_id";
        }
        $wxwork = new WxWorkOpen('',$suiteId);
        $wxwork->installSuite();
    }

    public function redirect(){
        return "redirect...";
    }
}