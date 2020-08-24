<?php

namespace YZ\Core\Member;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Site\Site;
use YZ\Core\Site\SiteAdminAllocation;

/**
 * 粉丝类
 * Class Fans
 * @package YZ\Core\Member
 */
class Fans
{
    public static function syncFansInviteForLogin($memberModel){
        $canOverWrite = true;
        if($memberModel->invite1) $canOverWrite = false; // 如果会员已经有推荐人，不能覆盖粉丝的邀请人
        if($memberModel->has_bind_invite) $canOverWrite = false; // 如果会员已经尝试绑定过关系，也不能覆盖粉丝的邀请人
        if(Site::getCurrentSite()->getConfig()->getModel()->bind_invite_time == 0) $canOverWrite = false; // 如果设置是注册时就绑定关系，不需要刷新粉丝邀请人
        $inputParams = Request::all();
        $inviteCode = intval($inputParams['invite_code']);
        if (!$inviteCode) $inviteCode = intval(Session::get('invite')); //其次从Session里取
        if (!$inviteCode) $inviteCode = intval(Request::cookie('invite')); //再次从Cookie里取
        if(getCurrentTerminal() == Constants::TerminalType_WxOfficialAccount && Session::get('WxOficialAccountOpenId')){
            $fans = WxUserModel::where('openid','=',Session::get('WxOficialAccountOpenId'))->where('site_id',getCurrentSiteId())->first();
            if($fans){
                WxUserModel::query()->where('member_id', $memberModel->id)->where('openid','<>',$fans->openid)->delete();
                if($inviteCode && $canOverWrite) $fans->invite = $inviteCode;
                if($fans->member_id != $memberModel->id) $fans->member_id = $memberModel->id;
                $fans->save();
            }
        }
        // 如果通过公众号openid找不到相应粉丝记录，尝试采用会员ID查找
        if(!$fans && $canOverWrite){
            $fans = WxUserModel::where('member_id',$memberModel->id)->where('site_id',getCurrentSiteId())->first();
            if($fans){
                if($inviteCode) {
                    $fans->invite = $inviteCode;
                    $fans->save();
                }
            }
        }
        if($canOverWrite && $inviteCode) Log::writeLog('member-fans','会员[id:'.$memberModel->id.',name:'.$memberModel->nickname.']更新邀请人ID为：'.$inviteCode);
    }

    public static function createFansForRegister($memberModel){
        if(getCurrentTerminal() == Constants::TerminalType_WxOfficialAccount && Session::get('WxOficialAccountOpenId')){
            // 公众号获取登录，粉丝记录的生成是在授权相关的地方，不需要另外生成粉丝记录
        } else {
            $inputParams = Request::all();
            $invite = intval($inputParams['invite_code']);;
            if (!$invite) $invite = intval(Session::get('invite')); //其次从Session里取
            if (!$invite) $invite = intval(Request::cookie('invite')); //再次从Cookie里取
            $fromAdmin = 0;
            if (!$fromAdmin) $fromAdmin = intval(Session::get('fromadmin')); //其次从Session里取
            if (!$fromAdmin) $fromAdmin = intval(Request::cookie('fromadmin')); //再次从Cookie里取
            if (!$fromAdmin) $fromAdmin = (new SiteAdminAllocation())->allocate();
            $model = new WxUserModel();
            $model->member_id = $memberModel->id;
            $model->official_account = '';
            $model->site_id = Site::getCurrentSite()->getSiteId();
            $model->platform = Constants::Fans_PlatformType_H5;
            $model->nickname = $memberModel->nickname;
            $model->sex = $memberModel->sex;
            $model->city = '';
            $model->province = '';
            $model->country = '';
            $model->headimgurl = $memberModel->headurl;
            $model->invite = $invite;
            $model->admin_id = $fromAdmin;
            $model->save();
        }
    }
}
