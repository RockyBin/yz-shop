<?php

namespace YZ\Core\Weixin;

use App\Modules\ModuleShop\Libs\Constants as AppConstants;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Messages\Text;
use EasyWeChat\Kernel\Messages\Image;
use EasyWeChat\Kernel\Messages\Video;
use EasyWeChat\Kernel\Messages\Voice;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;
use EasyWeChat\Kernel\Messages\Article;
use EasyWeChat\Kernel\Messages\Media;
use EasyWeChat\Kernel\Messages\Raw;
use YZ\Core\Common\ServerInfo;
use YZ\Core\Constants;
use YZ\Core\Site\Site;
use YZ\Core\Events\Eventable;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Member;

/**
 * 微信公众号业务类
 * Class OfficialAccount
 * @package YZ\Core\Weixin
 */
class OfficialAccount
{
    use Eventable;

    private $_wxconfig = null;
    private $_app = null;

    public function __construct($siteId = '')
    {
        $this->_wxconfig = new WxConfig($siteId);
        $options = [
            'app_id' => $this->_wxconfig->getModel()->appid,
            'secret' => $this->_wxconfig->getModel()->appsecret,
            'token' => $this->_wxconfig->getModel()->token,
            'response_type' => 'array',
            'log' => [
                'default' => 'prod', // 默认使用的 channel，生产环境可以改为下面的 prod
                'channels' => [
                    // 生产环境
                    'prod' => [
                        'driver' => 'daily',
                        'path' => storage_path() . '/logs/weixin/easywechat.log',
                        'level' => 'info',
                        'days' => 30
                    ],
                ],
            ],
        ];
        $this->_app = Factory::officialAccount($options);
    }

    public function getSiteId()
    {
        return $this->_wxconfig->getModel()->site_id;
    }

    /**
     * 获取微信配置对象实例
     * @return null|WxConfig
     */
    public function getConfig()
    {
        return $this->_wxconfig;
    }

    public function serve()
    {
        $this->_app->server->push(function ($message) {
            return $this->processMessage($message);
        });
        $response = $this->_app->server->serve();
        return $response;
    }

    /**
     * 接收并处理来自微信的消息
     * @param $message
     * @return Image|News|mixed|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \Exception
     */
    private function processMessage($message)
    {
        Log::writeLog("wechat", 'message_receive:' . var_export($message, true));
        switch ($message['MsgType']) {
            case 'event':
                $result = $this->processEvent($message);
                break;
            case 'text':
                $result = $this->processText($message);
                break;
            case 'image':
                $result = '收到图片消息';
                break;
            case 'voice':
                $result = '收到语音消息';
                break;
            case 'video':
                $result = '收到视频消息';
                break;
            case 'location':
                $result = '收到坐标消息';
                break;
            case 'link':
                $result = '收到链接消息';
                break;
            case 'file':
                $result = '收到文件消息';
                break;
            default:
                $result = '收到其它消息';
                break;
        }
        if ($result) {
            Log::writeLog("wechat", 'message_send:' . var_export($result, true));
        }
        return $result;
    }

    /**
     * 接收并处理来自微信的事件消息
     * @param $message
     * @return Image|News|mixed|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \Exception
     */
    private function processEvent($message)
    {
        switch (strtolower($message['Event'])) {
            case 'subscribe': //关注
                return $this->processSubscribe($message);
                break;
            case 'unsubscribe': //取消关注
                return $this->processUnSubscribe($message);
                break;
            case 'click': //点击菜单
                return $this->processMenuClick($message);
                break;
            case 'location': //请求定位
                return '';
                break;
            case 'scan': //扫码事件
                return $this->processScan($message);
                break;
            case 'view': //菜单链接跳转事件
                return '';
                break;
            default:
                return '';
                break;
        }
    }

    /**
     * 处理关注事件
     * @param $message
     * @return Image|News|mixed|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     * @throws \Exception
     */
    private function processSubscribe($message)
    {
        //获取用户信息再注册
        $user = $this->_app->user->get($message['FromUserName']);
        $user['official_account'] = $message['ToUserName'];
        $user['subscribe'] = 1;
        $user['unsubscribe_time'] = 0;
        WxUserHelper::saveUserInfo($user);
        $this->fireEvent('OnSubscribe', true, [$this, $user]);

        //如果是扫描邀请代理的带参二维码
        if ($user['qr_scene_str'] && strpos($user['qr_scene_str'], 'action=dealerInvite') !== false){
            $message = $this->processDealerInvite($user);
            if($message) return $message;
        }

        $reply = WxAutoReply::findByType($this->getSiteId(), Constants::Weixin_AutoReply_Subscribe);
        if ($reply) return $reply->getReply($message);
        else return '';
    }

    private function processScan($message){
        //获取粉丝信息
        $user = $this->_app->user->get($message['FromUserName']);
        //如果是扫描邀请代理的带参二维码
        //从 $message 中获取带参信息，注意：$user 中的 qr_scene_str 为此粉丝关注公众号时的带参信息，是不适用关注后再扫码的情况的，这时应该从
        //消息中获取当前正确的带参信息
        if($message['EventKey']) $user['qr_scene_str'] = $message['EventKey'];
        WxUserHelper::saveUserInfo($user);
        if ($user['qr_scene_str'] && strpos($user['qr_scene_str'], 'action=dealerInvite') !== false){
            $message = $this->processDealerInvite($user);
            if($message) return $message;
        }
    }

    /**
     * 处理代理邀请
     *
     * @param array $user 粉丝的信息
     * @return void
     */
    private function processDealerInvite($user){
        preg_match('/invite=([0-9a-z]+)/i',$user['qr_scene_str'],$match);
        if($match[1]) $invite = $match[1];
        preg_match('/inviteLevel=([0-9a-z]+)/i',$user['qr_scene_str'],$match);
        if($match[1]) $inviteLevel = $match[1];
        if($invite && $inviteLevel){
            $levelModel = DealerLevelModel::find($inviteLevel);
            $member = new Member($invite);
            $mModel = $member->getModel();
            if($mModel){
                $qrurl = getHttpProtocol() . '://' . ServerInfo::get('HTTP_HOST') . '/shop/front/vuehash/cloudstock/cloud-center';
                $qrurl .= '?invite=' . $invite.'&inviteLevel='.$inviteLevel;
                return new Text("<a href=\"$qrurl\">您好！$mModel->nickname 邀请您成为他的 ".$levelModel->name."，点击填写个人信息</a>");
            }else{
                return new Text("您好！邀请您成为 ".$levelModel->name."，点击填写个人信息");
            }
        }else{
            return new Text('邀请信息不正确，请重新设置邀请二维码');
        }
    }

    /**
     * 处理取消关注事件
     * @param $message
     * @return string
     */
    private function processUnSubscribe($message)
    {
        $model = WxUserModel::where('openid', '=', $message['FromUserName'])->where('site_id', '=', $this->getSiteId())->first();
        if ($model) {
            $model->unsubscribe_time = time();
            $model->subscribe = 0;
            $model->save();
        }
        return 'unsubscribe ok';
    }

    /**
     * 处理菜单点击事件
     * @param $message
     * @return Image|News|mixed|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \Exception
     */
    private function processMenuClick($message)
    {
        $menuId = $message['EventKey'];
        $menu = new WxMenu($menuId);
        if ($menu->checkExist()) return $menu->getReply($message);
        else return "此菜单不存在";
    }

    /**
     * 处理收到文本消息的自动回复
     * @param $message
     * @return Image|News|mixed|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \Exception
     */
    public function processText($message)
    {
        $text = $message['Content'];
        $reply = WxAutoReply::findByType($this->getSiteId(), Constants::Weixin_AutoReply_Keyword, $text);
        if (!$reply) $reply = WxAutoReply::findByType($this->getSiteId(), Constants::Weixin_AutoReply_Notmatch, $text);
        if ($reply) return $reply->getReply($message);
        return '';
    }

    /**
     * 发送客服消息到单个粉丝
     * @param $openid
     * @param string $message
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     * @throws \Exception
     */
    public function sendMessage($openid, $message = '')
    {
        if (!$message) $message = new Text('您好');
        $res = $this->_app->customer_service->message($message)->to($openid)->send();
        if (intval($res['errcode']) > 0) {
            throw new \Exception('send message to wxuser error: ' . $res['errmsg']);
        }
    }

    /**
     * 生成带参二维码
     * @param string|array $key
     * @param string $val
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     */
    public function qrcode($key, $val = null)
    {
        if(is_array($key)){
            $strings = [];
            foreach($key as $k => $v){
                $strings[] = "$k=$v";
            }
            $result = $this->_app->qrcode->forever(implode('&',$strings));
        }else{
            $result = $this->_app->qrcode->forever("$key=$val");
        }
        $url = $this->_app->qrcode->url($result['ticket']);
        $result['qrurl'] = $url;
        return $result;
    }

    /**
     * 推送菜单到公众号
     * @param $menus
     * @throws \Exception
     */
    public function pushMenu($menus)
    {
        $res = $this->_app->menu->create($menus);
        if ($res['errcode'] != 0) throw new \Exception('push menu:' . $res['errmsg']);
    }

    /**
     * @param int $menuId    删除个性化菜单时用，ID 从查询接口获取 不传则删除所有菜单
     * @throws \Exception
     */
    public function deleteMenu($menuId = 0)
    {
        if ($menuId) {
            $res = $this->_app->menu->delete($menuId);
        } else {
            $res = $this->_app->menu->delete();
        }
        if ($res['errcode'] != 0) throw new \Exception('delete menu:' . $res['errmsg']);
    }

    /**
     * 获取公众号的临时媒体接口对象
     * @return \EasyWeChat\BasicService\Media\Client
     */
    public function getMediaObj()
    {
        return $this->_app->media;
    }

    /**
     * 获取公众号的各素材管理接口对象
     * @return \EasyWeChat\OfficialAccount\Material\Client
     */
    public function getMaterialObj()
    {
        return $this->_app->material;
    }

    /**
     * 获取粉丝管理接口对象
     * @return \EasyWeChat\OfficialAccount\User\UserClient
     */
    public function getUserObj()
    {
        return $this->_app->user;
    }

    /**
     * 获取群发接口对象
     * @return \EasyWeChat\OfficialAccount\Broadcasting\Client
     */
    public function getBroadcastObj()
    {
        return $this->_app->broadcasting;
    }

    /**
     * 获取用户标签接口对象
     * @return \EasyWeChat\OfficialAccount\User\TagClient
     */
    public function getUserTagObj()
    {
        return $this->_app->user_tag;
    }

    /**
     * 返回JSSDK
     * @return \EasyWeChat\BasicService\Jssdk\Client
     */
    public function getJSSDK()
    {
        return $this->_app->jssdk;
    }
}