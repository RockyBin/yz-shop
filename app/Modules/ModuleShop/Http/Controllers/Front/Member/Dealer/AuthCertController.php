<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Dealer;

use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\Dealer\AuthCert\AuthCert;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\DealerAuthCertItemModel;
use App\Modules\ModuleShop\Libs\Model\DealerAuthCertModel;
use YZ\Core\Member\Member;
use YZ\Core\Site\Site;

class AuthCertController extends BaseController
{
    public function getInfo()
    {
        try {
            $checkRes = $this->check();
            if($checkRes['code'] != 200) return $checkRes;
            //读取当前会员是否已经生成过证书
            $cert = DealerAuthCertItemModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('member_id',$this->memberId)->first();
            if(!$cert) return makeApiResponse(404,'cert not exists');
			$terminal = getCurrentTerminal();
			if(!$cert->image_wxapp && $terminal == \YZ\Core\Constants::TerminalType_WxApp){
				return makeApiResponse(404,'cert not exists');
			}
            $cert->image = Site::getSiteComdataDir().$cert->image;
			//如果是小程序，就输出小程序码
			if($terminal == \YZ\Core\Constants::TerminalType_WxApp){
				$cert->image = Site::getSiteComdataDir().$cert->image_wxapp;
			}
            return makeApiResponseSuccess('ok', $cert->toArray());
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    private function check(){
        //检测当前网站是否有授权证书功能
        $site = Site::getCurrentSite();
        if (!$site->getSn()->hasPermission(Constants::FunctionPermission_ENABLE_AUTHCERT)) {
            return makeServiceResult(413, "网站未开通授权证书功能");
        }
        //检测后台是否有配置好证书
        $member = new Member($this->memberId);
        $dealerLevel = $member->getModel()->dealer_level;
        $config = AuthCert::getApplySettingInfo();
        if (!$config[$dealerLevel]) {
            return makeServiceResult(405, "商家还设置授权证书，如有疑问，请联系客服~");
        }
        $certId = $config[$dealerLevel]->id;
        $cert = DealerAuthCertModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('id',$certId)->first();
        if(!$cert){
            return makeServiceResult(406, "证书配置信息缺失，如有疑问，请联系客服~");
        }
        return makeServiceResult(200, "ok");
    }

    /**
     * 保存证书信息
     * @param Request $request
     * @return array
     */
    public function create(Request $request)
    {
        try {
            $checkRes = $this->check();
            if($checkRes['code'] != 200) return $checkRes;
			$terminal = $request->get('terminal',getCurrentTerminal());
            $data = AuthCert::createMemberCert($this->memberId,$terminal);
            $data = $data->toArray();
            if ($data['image_wxapp']) $data['image'] = Site::getSiteComdataDir().$data['image_wxapp'];
            else $data['image'] = Site::getSiteComdataDir().$data['image'];
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}
