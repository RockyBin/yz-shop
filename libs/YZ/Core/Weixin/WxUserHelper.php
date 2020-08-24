<?php
namespace YZ\Core\Weixin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Auth;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use YZ\Core\Events\Event;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Site\SiteAdminAllocation;

/**
 * Class WxUserHelper 微信粉丝的静态工具类
 * @package YZ\Core\Weixin
 */
class WxUserHelper
{
    /**
     * 保存粉丝信息到数据库，目前主要用在关注公众号和授权登录时，更新数据库信息
     * @param array $user 从微信接口获取到的粉丝信息
     * 有三个字段是我们加的，并不是从接口里返回，分别是 unsubscribe_time，official_account，invite
     * invite 一般是通过关注公众号时由带参二维码带过来
     */
    public static function saveUserInfo(array $user){
        //判断是否存在上下级推荐关系(是否带参数二维码)
        $invite = 0;
        //尝试获取推荐人
        if ($user['qr_scene_str']){
            if (strpos($user['qr_scene_str'], 'invite') !== false) {
                preg_match('/invite=([0-9a-z]+)/i',$user['qr_scene_str'],$match);
                if($match[1]) $invite = $match[1];
                $user['invite'] = $invite;
            }
            if (strpos($user['qr_scene_str'], 'fromadmin') !== false) {
                preg_match('/fromadmin=([0-9a-z]+)/i',$user['qr_scene_str'],$match);
                if($match[1]) $fromadmin = $match[1];
                $user['admin_id'] = $fromadmin;
            }
        } else {
            $invite = 0;
            if (!$invite) $invite = intval(Session::get('invite')); //其次从Session里取
            if (!$invite) $invite = intval(Request::cookie('invite')); //再次从Cookie里取
            $user['invite'] = $invite;

            $fromadmin = 0;
            if (!$fromadmin) $fromadmin = intval(Session::get('fromadmin')); //其次从Session里取
            if (!$fromadmin) $fromadmin = intval(Request::cookie('fromadmin')); //再次从Cookie里取
            if (!$fromadmin) $fromadmin = (new SiteAdminAllocation())->allocate();
            $user['admin_id'] = $fromadmin;
        }

        //没有手机号，不绑定上下级
        if($user['invite']) $pModel = MemberModel::find($user['invite']);
        $mobile = $pModel ? $pModel->mobile : '';
        if(!preg_match('/^\d{11}$/',$mobile)) $user['invite'] = 0;

        //自动设置公众号的原始ID
        if(!array_key_exists('official_account',$user)){
            $user['official_account'] = Site::getCurrentSite()->getOfficialAccount()->getConfig()->getModel()->wxid;
        }
        $model = WxUserModel::where('openid','=',$user['openid'])->where('site_id','=',Site::getCurrentSite()->getSiteId())->first();
        if(!$model){
            $model = new WxUserModel();
        }
        $model->openid = $user['openid'];
        if($user['official_account']) $model->official_account = $user['official_account'];
        $model->site_id = Site::getCurrentSite()->getSiteId();
        $model->platform = Constants::Fans_PlatformType_WxOfficialAccount;
        $model->nickname = $user['nickname'];
        $model->sex = $user['sex'];
        $model->city = $user['city'];
        $model->province = $user['province'];
        $model->country = $user['country'];
        $model->headimgurl = $user['headimgurl'];
        if(array_key_exists('tagid_list',$user) && is_array($user['tagid_list']) && count($user['tagid_list'])){
            $tagids = ','.implode(',',$user['tagid_list']).',';
            $model->tags = $tagids;
        }
        $canOverWriteInvite = true;
        //if(Site::getCurrentSite()->getConfig()->getModel()->bind_invite_time == 1){
            $member = Auth::getMemberWxOficialAccount($user['openid']);
            if($member && $member->getModel()->has_bind_invite == 1) $canOverWriteInvite = false;
        //}
        if(array_key_exists('subscribe_time',$user)) $model->subscribe_time = $user['subscribe_time'];
        if(array_key_exists('remark',$user)) $model->remark = $user['remark'];
        if(array_key_exists('groupid',$user)) $model->groupid = $user['groupid'];
        if(array_key_exists('unsubscribe_time',$user)) $model->unsubscribe_time = $user['unsubscribe_time'];
        if(array_key_exists('subscribe',$user)) $model->subscribe = $user['subscribe'];
        if(array_key_exists('invite',$user) && $user['invite'] && (/*!$model->invite ||*/ $canOverWriteInvite)) $model->invite = $user['invite']; //推荐人不应该被覆盖，所以此处限制为当原来没有推荐人的时候才能更新推荐人字段
        if(array_key_exists('admin_id',$user) && !$model->admin_id) $model->admin_id = $user['admin_id']; //员工推荐人不应该被覆盖，所以此处限制为当原来没有员工推荐人的时候才能更新推荐人字段
        $model->save();
    }

    /**
     * 从公众号获取粉丝的 openid ，一般在同步粉丝时使用，微信限制一次最多拉取10000个openid，如果要获取全部，要用循环来搞定
     * @param string $nextOpenId 从哪个OPENID开始拉取，不填默认从头开始拉取
     * @return int|mixed|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function getUsersOpenIdFromApi($nextOpenId = ''){
        $wx = Site::getCurrentSite()->getOfficialAccount();
        $api = $wx->getUserObj();
        $list = $api->list($nextOpenId);
        if($list['errcode'] > 0) throw new \Exception('get openid error: '.$list['errmsg']);
        if($list['count'] == 0) return 0;
        $nextOpenId = $list['next_openid'];
        $openIds = $list['data']['openid'];
        //将获取到的openid写入临时文件内，给后面读取会员信息作准备
        $path = storage_path().'/wxopenid';
        \Ipower\Common\Util::mkdirex($path);
        $file = $path.'/'.Site::getCurrentSite()->getSiteId().'.txt';
        if(file_exists($file) && filemtime($file) < time() - 300) unlink($file); //如果文件修改时间是五分钟，认为不是同一个任务，将原文件清空
        $fd = fopen($file,"a+");
        foreach ($openIds as $openid){
            fwrite($fd,$openid."\r\n");
        }
        fclose($fd);
        return $nextOpenId;
    }

    /**
     * 从公众号获取全部粉丝的 openid
     */
    public static function getAllUsersOpenIdFromApi(){
        $nextOpenId = self::getUsersOpenIdFromApi();
        while($nextOpenId){
            $nextOpenId = self::getUsersOpenIdFromApi($nextOpenId);
            //echo "nextOpenId = $nextOpenId \r\n";
        }
    }

    /**
     * 读取临时文件中的 openid 列表，根据openid拉取粉丝信息并写入数据库，为避免程序超时，注意一次不要读太多，外层要自行分页读取
     * @param int $offset 从第几个开始拉取
     * @param int $limit 一次最多拉几个
     * @return bool 读到文本尾时，会返回 false,表示没有更多的openid了，此时外层应该中止循环分页拉取的过程
     */
    public static function syncUsers($offset = 0,$limit = 100){
        $path = storage_path().'/wxopenid';
        $file = $path.'/'.Site::getCurrentSite()->getSiteId().'.txt';
        $lines = \Ipower\Common\Util::getFileLines($file,$offset,$limit);
        if(count($lines) == 0) return false;
        $wx = Site::getCurrentSite()->getOfficialAccount();
        $api = $wx->getUserObj();
        $users = $api->select($lines);
        foreach ($users['user_info_list'] as $user){
            self::saveUserInfo($user);
        }
        return true;
    }

    /**
     * 列出当前公众号的用户标签
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function listTags(){
        $tags = Site::getCurrentSite()->getOfficialAccount()->getUserTagObj()->list();
        if($tags['errcode'] > 0) throw new \Exception('get user tags error: '.$tags['errmsg']);
        return $tags;
    }

    /**
     * 创建用户标签
     * @param $name 标签名称
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function createTag($name){
        $tag = Site::getCurrentSite()->getOfficialAccount()->getUserTagObj()->create($name);
        if($tag['errcode'] > 0) throw new \Exception('create user tags error: '.$tag['errmsg']);
        return $tag;
    }

    /**
     * 更改标签名称
     * @param $tagId 标签ID
     * @param $name 新标签名称
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function updateTag($tagId, $name){
        $tag = Site::getCurrentSite()->getOfficialAccount()->getUserTagObj()->update($tagId, $name);
        if($tag['errcode'] > 0) throw new \Exception('update user tags error: '.$tag['errmsg']);
        return $tag;
    }

    /**
     * 删除标签
     * @param $tagId 标签ID
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function deleteTag($tagId){
        $tag = Site::getCurrentSite()->getOfficialAccount()->getUserTagObj()->delete($tagId);
        if($tag['errcode'] > 0) throw new \Exception('delete user tags error: '.$tag['errmsg']);
    }

    /**
     * 获取粉丝的标签列表
     * @param $openId 粉丝 openid
     * @return mixed
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function getUserTag($openId){
        $tags = Site::getCurrentSite()->getOfficialAccount()->getUserTagObj()->userTags($openId);
        if($tags['errcode'] > 0) throw new \Exception('create user tags error: '.$tags['errmsg']);
        return $tags['tagid_list'];
    }

    /**
     * 将某些粉丝添加标签
     * @param array $openIds 粉丝列表
     * @param $tagId 标签ID
     * @return bool
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function addUsersTag(array $openIds, $tagId){
        $tags = Site::getCurrentSite()->getOfficialAccount()->getUserTagObj()->tagUsers($openIds, $tagId);
        if($tags['errcode'] > 0) throw new \Exception('add users tag error: '.$tags['errmsg']);
        return true;
    }

    /**
     * 删除某些粉丝的标签
     * @param array $openIds 粉丝列表
     * @param $tagId 标签ID
     * @return bool
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public static function removeUsersTag(array $openIds, $tagId){
        $tags = Site::getCurrentSite()->getOfficialAccount()->getUserTagObj()->untagUsers($openIds, $tagId);
        if($tags['errcode'] > 0) throw new \Exception('remove users tag error: '.$tags['errmsg']);
        return true;
    }
}