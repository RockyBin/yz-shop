<?php

namespace App\Modules\ModuleShop\Libs\SharePaper\Mobi;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\SharePaperModel;
use Predis\Command\ServerInfo;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Auth;
use YZ\Core\Model\MemberAuthModel;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\MessageHelper;

class WeixinMessageHelper
{
	private $site = null;
	private $wx = null;
	
    public function __construct(){
        $this->site = Site::getCurrentSite();
        $this->wx = $this->site->getOfficialAccount();
    }

    /**
     * 当会员输入关键词或点击菜单时，返回会员的推广二维码
     * @param  $paper_id 海报的id
     * @param $wxmessage 收到的来自微信的消息
     */
    public function sendWeixinPaperImage($wxmessage,$paper_id){
        try {
            if($paper_id){
                $paperId=$paper_id['paper_id'];
            }
            $openid = $wxmessage['FromUserName'];
            $auth = MemberAuthModel::where('type', \YZ\Core\Constants::MemberAuthType_WxOficialAccount)
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('openid', $openid)->first();
            $bindUrl = getHttpProtocol().'://'.\YZ\Core\Common\ServerInfo::get('HTTP_HOST').'/shop/front/#/member/member-center';
            if (!$auth) {
                $this->wx->sendMessage($openid,'您还没有绑定公众号，不能获取海报，点击<a href="'.$bindUrl.'">这里绑定</a>');
                return;
            }
            //$this->wx->sendMessage($openid,'您还没有绑定公众号，不能获取海报，点击<a href="'.$bindUrl.'">这里绑定</a>');
            if($paperId){
                $paper=new Paper($paperId);
            }else{
                $paper = Paper::getDefaultPaper();
            }
            $image = $paper->renderImage($auth->member_id,1);
            if($image) {
                $imgMsg = MessageHelper::makeImageMessage($image);
                $this->wx->sendMessage($openid,$imgMsg);
            }else{
                $this->wx->sendMessage($openid,'抱歉，未能获取分享海报');
            }
        } catch (\Exception $e) {
            Log::writeLog('paperError',$e->getMessage());
            throw $e;
        }

    }
}