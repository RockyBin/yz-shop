<?php

namespace App\Modules\ModuleShop\Libs\Wx;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\WxSubscribeSettingModel;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use YZ\Core\Member\Auth;
use YZ\Core\Member\Fans;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\WxConfig;

class WxSubscribeSetting
{
    private $_siteId = 0;
    private $_model = null;

    public function __construct($siteId = 0)
    {
        if (!$siteId) $siteId = getCurrentSiteId();
        $this->_siteId = $siteId;
        $this->_model = WxSubscribeSettingModel::query()->where('site_id', $this->_siteId)->first();
    }

    /**
     * 返回原始 model
     * @return WxSubscribeSettingModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * 修改
     * @param array $info
     */
    public function edit(array $info)
    {
        if (!$this->_model) {
            $this->_model = new WxSubscribeSettingModel();
            $this->_model->site_id = $this->_siteId;
        }
        $info['site_id'] = $this->_siteId;
        $this->_model->fill($info);
        $this->_model->save();
    }

    /**
     * 返回关注信息，用于前端判断是否需要显示相关界面
     * @return WxSubscribeSettingModel
     */
    public function getSubscribeInfo()
    {
        $memberId = Auth::hasLogin();
        $member = new Member($memberId);
        if($member->checkExist()) {
            $openId = $member->getWxOpenId();
            $wxUser = WxUserModel::query()->where('openid', $openId)->first();
            if($member->getModel()->invite1) {
                $inviteId = $member->getModel()->invite1;
            }else {
                $fans = WxUserModel::query()->where('member_id',$memberId)->first();
                $inviteId = $fans ? $fans->invite : 0;
            }
        }
        if (!$inviteId) $inviteId = intval(Session::get('invite')); //其次从Session里取
        if (!$inviteId) $inviteId = intval(Request::cookie('invite')); //再次从Cookie里取
        if($inviteId) {
            $invite = MemberModel::find($inviteId);
            $invite = [
                'nickname' => $invite->nickname,
                'headurl' => Member::getHeadUrl($invite->headurl)
            ];
        }
        $wxConfig = new WxConfig();
        $subscribeInfo = [
            'member_id' => $memberId,
            'openid' => $openId,
            'subscribe' => $wxUser ? $wxUser->subscribe : 0,
            'qrcode' => Site::getSiteComdataDir().$wxConfig->getModel()->qrcode,
            'invite' => $invite ?? new \stdClass()
        ];
        return ['setting' => $this->_model ?? new \stdClass(), 'member_subscribe_info' => $subscribeInfo];
    }
}
