<?php

namespace YZ\Core\Weixin;

use EasyWeChat\Factory;
use YZ\Core\Constants;
use YZ\Core\Events\Eventable;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberAuthModel;
use YZ\Core\Model\WxWorkModel;

/**
 * 企业微信自建应用业务类
 * Class WxWorkOpen
 * @package YZ\Core\Weixin
 */
class WxWork
{
    use Eventable;

    private $_appInfo = null;
    private $_app = null;
    private $_config = null;

    public function __construct($siteId = 0,$corpId = '',$agentId = '')
    {
        if(!$siteId){
            $siteId = getCurrentSiteId();
        }
        $where = ['site_id' => $siteId,'mode' => 1,'status' => 1];
        if($corpId) $where['corp_id'] = $corpId;
        if($agentId) $where['agent_id'] = $agentId;
        $this->_appInfo = WxWorkModel::query()->where($where)->first();
        if (!$this->_appInfo) {
            throw new \Exception("读取企业微信自建应用配置错误");
        }
        $this->_config = [
            'corp_id' => $this->_appInfo->corp_id,
            'agent_id' => $this->_appInfo->agent_id,
            'secret'   => $this->_appInfo->secret,
            'token' => $this->_appInfo->token,
            'aes_key' => $this->_appInfo->aes_key,
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',
            'log' => [
                'default' => 'prod', // 默认使用的 channel，生产环境可以改为下面的 prod
                'channels' => [
                    // 生产环境
                    'prod' => [
                        'driver' => 'daily',
                        'path' => storage_path() . '/logs/weixin/wxwork.log',
                        'level' => 'info',
                        'days' => 30
                    ],
                ],
            ],
        ];
        $this->_app = Factory::work($this->_config);
    }

    public function getConfig(){
        return $this->_config;
    }

    public function getJSSDK(){
        return $this->_app->jssdk;
    }

    /**
     * 跳转授权登录地址
     * @param $redirectUrl
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function OAuth($redirectUrl){
        // 返回一个 redirect 实例
        $redirect = $this->_app->oauth->redirect($redirectUrl);
        return $redirect;
    }

    /**
     * 授权登录后获取授权用户信息
     */
    public function afterOAuth(){
        $this->_app->oauth->stateless();
        $user = $this->_app->oauth->detailed()->user();
        // 获取用户信息
        $userInfo = $user->getOriginal(); // 获取企业微信接口返回的原始信息
        if($userInfo['UserId']) { //企业成员时
            $userInfo['id'] = $userInfo['UserId'];
            $userDetail = $this->_app->user->get($user->getId());
            $userInfo['is_extenal'] = 0; //非外部联系人
            $userInfo['name'] = $userDetail['name'];
            $userInfo['nick_name'] = $userDetail['name'];
            $userInfo['mobile'] = $userDetail['mobile'];
            $userInfo['thumb_avatar'] = $userDetail['thumb_avatar'];
        } elseif ($userInfo['OpenId']){ //外部联系人
            $userInfo['id'] = $userInfo['OpenId'];
            $userInfo['is_extenal'] = 1; //是外部联系人
            $userInfo['name'] = '企业微信用户_' . substr(md5(mt_rand()),0,6);
            $userInfo['nick_name'] = $userInfo['name'];
        }
        return $userInfo;
    }

    /**
     * 接收消息处理入口
     * @return mixed
     */
    public function serve()
    {
        $this->_app->server->push(function ($message) {
            Log::writeLog('wxwork','receive message:'.var_export($message,true));
            switch ($message['InfoType']) {
                //推送suite_ticket
                case 'suite_ticket':
                    break;
                default:
                    return '';
                    break;
            }
        });
        $response = $this->_app->server->serve();
        return $response;
    }

    /**
     * 获取指定用户的openid
     * @param $authId tbl_member_auth 表的openid字段
     */
    public function getUserOpenId($authId){
        $authModel = MemberAuthModel::query()->where(['openid' => $authId,'type' => Constants::MemberAuthType_WxWork,'open_app_id' => $this->_config['corp_id']])->first();
        $extendInfo = @json_decode($authModel->extend_info,true);
        if(intval($extendInfo['is_extenal'])) return $authId;
        $res = $this->_app->user->userIdToOpenid($authId);
        return $res['openid'];
    }
}