<?php

namespace YZ\Core\Weixin;

use EasyWeChat\Factory;
use Illuminate\Support\Facades\Response;
use YZ\Core\Events\Eventable;
use YZ\Core\Logger\Log;
use YZ\Core\Model\WxWorkAppModel;
use YZ\Core\Model\WxWorkProviderModel;
use YZ\Core\Site\Site;

/**
 * 企业微信开放平台业务类
 * Class WxWorkOpen
 * @package YZ\Core\Weixin
 */
class WxWorkOpen
{
    use Eventable;

    private $_appInfo = null;
    private $_providerInfo = null;
    private $_app = null;
    private $_messageIsToCorp = false;
    private $_rawMessage = "";

    public function __construct($corpId = '',$suiteId = '')
    {
        $this->_rawMessage = file_get_contents('php://input');
        if($suiteId) {
            $this->_appInfo = WxWorkAppModel::find($suiteId);
            if (!$this->_appInfo) {
                throw new \Exception("读取企业微信应用配置错误");
            }
            $this->_providerInfo = WxWorkProviderModel::find($this->_appInfo->corp_id);
            if (!$this->_providerInfo) {
                throw new \Exception("读取企业微信服务商配置错误");
            }
        } elseif($corpId){
            $this->_providerInfo = WxWorkProviderModel::find($corpId);
            if (!$this->_providerInfo) {
                throw new \Exception("读取企业微信服务商配置错误");
            }
        }
        // 第三方应用的数据回调URL有时会返回一些数据，这个数据是给开放平台的，而不是给应用的
        // 当数据是给应用时，微信传过来的XML的 ToUserName 为应用的ID ，如：<xml><ToUserName><![CDATA[$this->_appInfo->corp_id]]></ToUserName>...
        // 否则为开放平台的ID 如：<xml><ToUserName><![CDATA[$this->_providerInfo->corp_id]]></ToUserName>...
        // 这时如果用应用的 token 和 aes_key 解密就会出错，这里要做特殊处理
        if($this->_appInfo && stripos($this->_rawMessage,'<ToUserName><![CDATA['.$this->_providerInfo->corp_id) !== false && stripos($this->_rawMessage,'<ToUserName><![CDATA['.$this->_appInfo->suite_id) === false){
            //$forceProvider = true;
            $this->_messageIsToCorp = true;
        }
        if($this->_appInfo && $this->_providerInfo/* &&!$forceProvider*/) {
            $config = [
                'corp_id' => $this->_providerInfo->corp_id,
                'secret' => $this->_providerInfo->secret,
                'suite_id' => $suiteId,
                'suite_secret' => $this->_appInfo->secret,
                'token' => $this->_appInfo->token,
                'aes_key' => $this->_appInfo->aes_key,
                //'reg_template_id'      => '注册定制化模板ID',
                //'redirect_uri_install' => '安装应用的回调url（可选）',
                //'redirect_uri_single'  => '单点登录回调url （可选）',
                //'redirect_uri_oauth'   => '网页授权第三方回调url （可选）',
            ];
        } elseif($this->_providerInfo) {
            $config = [
                'corp_id' => $this->_providerInfo->corp_id,
                'secret' => $this->_providerInfo->secret,
                'token' => $this->_providerInfo->token,
                'aes_key' => $this->_providerInfo->aes_key,
                //'reg_template_id'      => '注册定制化模板ID',
                //'redirect_uri_install' => '安装应用的回调url（可选）',
                //'redirect_uri_single'  => '单点登录回调url （可选）',
                //'redirect_uri_oauth'   => '网页授权第三方回调url （可选）',
            ];
        }
        $this->_app = Factory::openWork($config);
    }

    public function installSuite(){
        $site = Site::getCurrentSite();
        $code = $this->_app->corp->getPreAuthCode();
        $this->_app->corp->setSession( $code['pre_auth_code'], ['auth_type' => $this->_appInfo->is_online ? 0 : 1]);
        $state = $site->getSiteId();
        $redirectUrl = $this->_appInfo->auth_callback_domain."/core/wxwork/open/install/redirect"; //坑爹的回调URL不能填写协议头
        $redirectUrl = urlencode($redirectUrl);
        $url = "https://open.work.weixin.qq.com/3rdapp/install?suite_id=".$this->_appInfo->suite_id."&pre_auth_code=".$code['pre_auth_code']."&redirect_uri=".$redirectUrl."&state=".$state;
        header("Location: ".$url);
        myexit();
    }

    public function serve()
    {
        if($this->_messageIsToCorp){
            Log::writeLog('wxworkopen','应用数据回调URL不处理发给开放平台的消息:'.$this->_rawMessage);
            return "应用数据回调URL不处理发给开放平台的消息";
        }
        $this->_app->server->push(function ($message) {
            switch ($message['InfoType']) {
                //推送suite_ticket
                case 'suite_ticket':
                    break;
                //授权成功通知
                case 'create_auth':
                    Log::writeLog('wxworkopen','create_auth:'.var_export($message,true));
                    myexit("暂时不支持在应用市场授权安装，请联系我们");
                    break;
                //变更授权通知
                case 'cancel_auth':
                    Log::writeLog('wxworkopen','cancel_auth:'.var_export($message,true));
                    break;
                //通讯录事件通知
                case 'change_contact':
                    switch ($message['ChangeType']) {
                        case 'create_user':
                            return '新增成员事件';
                            break;
                        case 'update_user':
                            return '更新成员事件';
                            break;
                        case 'delete_user':
                            return '删除成员事件';
                            break;
                        case 'create_party':
                            return '新增部门事件';
                            break;
                        case 'update_party':
                            return '更新部门事件';
                            break;
                        case 'delete_party':
                            return '删除部门事件';
                            break;
                        case 'update_tag':
                            return '标签成员变更事件';
                            break;
                    }
                    break;
                default:
                    return 'fail';
                    break;
            }
        });
        $response = $this->_app->server->serve();
        return $response;
    }
}