<?php
namespace App\Http\Controllers\Common;

use Illuminate\Http\Request;
use Ipower\Common\Util;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\WxApp;

/**
 * 二维码相关工具
 * Class QrcodeController
 * @package App\Http\Controllers\Common
 */
class QrcodeController
{
	/**
	 * 获取小程序二维码
	 */
    public function wxAppQrcode(Request $request){
		try {
            $qrurl = $request->get('qrurl');
            $qrurl = str_replace('/#/','/vuehash/',$qrurl);
            $scene = $request->get('scene');
            $returnType = $request->get('returnType');
            $permanent = $request->get('permanent');
            $savePath = '/tmpdata/' . Site::getCurrentSite()->getSiteId();
            Util::mkdirex(public_path().$savePath);
            $filename = md5($qrurl.$scene) . '.jpg';
            $wxApp = new WxApp();
            if($permanent) $response = $wxApp->getQrcode($qrurl, ['auto_color' => true, 'width' => 700]);
            else $response = $wxApp->getUnlimitQrcode($qrurl, ['auto_color' => true, 'width' => 700], $scene); //大部分情况下，获取到的码是临时用，不用永久保存
            if($returnType) return $response;
            if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
                $filename = $response->saveAs(public_path().$savePath, $filename);
            }
			return makeApiResponseSuccess('ok',['qrcode' => $savePath.'/'.$filename]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}