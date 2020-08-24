<?php

namespace YZ\Core\Weixin;

use EasyWeChat\Factory;
use YZ\Core\Events\Eventable;
use YZ\Core\Model\WxAppModel;

/**
 * 微信微信小程序业务类
 * Class WxApp
 * @package YZ\Core\Weixin
 */
class WxApp
{
    use Eventable;

    private $_appInfo = null;
    private $_app = null;
    private $_config = null;

    public function __construct($siteId = 0)
    {
        if(!$siteId){
            $siteId = getCurrentSiteId();
        }
        $where = ['site_id' => $siteId];
        $this->_appInfo = WxAppModel::query()->where($where)->first();
        if (!$this->_appInfo) {
            throw new \Exception("读取微信小程序配置错误");
        }
        $this->_config = [
            'app_id' => $this->_appInfo->appid,
            'secret' => $this->_appInfo->appsecret,

            // 下面为可选项
            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',
            'log' => [
                'default' => 'prod', // 默认使用的 channel，生产环境可以改为下面的 prod
                'channels' => [
                    // 生产环境
                    'prod' => [
                        'driver' => 'daily',
                        'path' => storage_path() . '/logs/weixin/wxapp.log',
                        'level' => 'info',
                        'days' => 30
                    ],
                ],
            ],
        ];

        $this->_app = Factory::miniProgram($this->_config);
    }

    /**
     * 获取配置信息
     * @return array|null
     */
    public function getConfig(){
        return $this->_config;
    }

    /**
     * 获取小程序实例
     * @return \EasyWeChat\MiniProgram\Application|null
     */
    public function getApp(){
        return $this->_app;
    }

    /**
     * 获取登录信息，主要是 openid 和 session_key
     * @param $code
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function login($code){
        $session = $this->_app->auth->session($code);
        return $session;
    }

    /**
     * 获取用户绑定的手机号
     * @param $sessionKey
     * @param $iv
     * @param $encryptedData
     * @return array 返回数组，其中 purePhoneNumber 为用户的明文手机号，encPurePhoneNumber 为经过我们自定义加密的手机号，用于在小程序端用户登录
     * @throws \EasyWeChat\Kernel\Exceptions\DecryptException
     */
    public function getMobile($sessionKey, $iv, $encryptedData){
        $decryptedData = $this->_app->encryptor->decryptData($sessionKey, $iv, $encryptedData);
        if(is_array($decryptedData)) {
            $des = new \Ipower\Common\CryptDes();
            $encPurePhoneNumber = $des->encrypt($decryptedData['purePhoneNumber']);
            $decryptedData['encPurePhoneNumber'] = $encPurePhoneNumber;
        }
        return $decryptedData;
    }

    /**
     * 生成小程序码
     * @param string $path 小程序页面路径
     * @param array $optional 小程序码选项， 为以下可选参数
     *  width Int - 默认 430 二维码的宽度
        auto_color 默认 false 自动配置线条颜色，如果颜色依然是黑色，则说明不建议配置主色调
        line_color 数组，auto_color 为 false 时生效，使用 rgb 设置颜色 例如 ，示例：["r" => 0,"g" => 0,"b" => 0]。
     * @return \EasyWeChat\Kernel\Http\StreamResponse
     */
    public function getQrcode(string $path, array $optional = []){
        return $this->_app->app_code->get($path, $optional);
    }

    public function getUnlimitQrcode(string $path, array $optional = [],$scene = ''){
        if(!$scene) $scene = genUuid(10);
        $optional['path'] = $path;
        return $this->_app->app_code->getUnlimit($scene, $optional);
    }
}