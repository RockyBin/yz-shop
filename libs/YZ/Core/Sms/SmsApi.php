<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/5
 * Time: 9:15
 */

namespace YZ\Core\Sms;

use YZ\Core\Common\ServerInfo;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

/**
 * 云指的短信接口API
 * Class SmsApi
 * @package YZ\Core\Sms
 */
class SmsApi
{
    private $_smsUser;  //ID
    private $_smsPwd;  //密码
    private $_smsSign;  //签名
    private $_url;  //发送网关

    public function __construct($smsuser, $smspassword, $smssign)
    {
        $this->_smsUser = $smsuser;
        $this->_smsPwd = $smspassword;
        $this->_smsSign = $smssign;
        $this->_url = 'http://www.72dns.com/smsadmin/Sms_Api.aspx';
    }

    /**
     * 发送短信
     * @param $content 短信内宅
     * @param $mobile 接收手机号
     * @return bool
     * @throws \Exception
     */
    public function sendSms($content, $mobile)
    {
        try {
            if (!$content) {
                throw new \Exception('no content');
            }
            if (!$mobile) {
                throw new \Exception('no mobile');
            }

            if (!$this->_smsSign) {
                throw new \Exception('no SMSSign');
            }

            $content = trim($content);
            $content .= '【' . $this->_smsSign . '】';
            $datas = array();
            $datas['UserID'] = $this->_smsUser;
            $datas['PassWord'] = md5($this->_smsPwd);
            $datas['action'] = 'SendSms';
            $datas['message'] = $content;
            $datas['mobile'] = $mobile;
            $result = $this->http($datas);
            $result = mb_convert_encoding($result, 'utf-8', 'gbk');

            // 写日志
            $logStr = "\r\n请求地址: " . 'http://' . (explode(':', ServerInfo::get("HTTP_HOST"))[0]) . ':' . ServerInfo::get("SERVER_PORT") . ServerInfo::get("REQUEST_URI");
            $logStr .= "\r\n手机号: " . $mobile . "\r\n短信内容: " . $content . "\r\n接口返回: " . $result;
            Log::writelog('sms_send', $logStr);
            $isok = strpos($result, 'code=200') === 0;
            if (!$isok) throw new \Exception($result);
            return $isok;
        } catch (Exception $e) {
            // 写日志
            $logStr = 'request url: ' . 'http://' . (explode(':', ServerInfo::get("HTTP_HOST"))[0]) . ':' . ServerInfo::get("SERVER_PORT") . ServerInfo::get("REQUEST_URI");
            $logStr .= "\r\n" . $mobile . ': ' . $e->getMessage();
            Log::writelog('sms_send', $logStr);
            return false;
        }
    }

    private function http($datas)
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('POST', $this->_url, ['form_params' => $datas]);
        $data = $res->getBody()->getContents();
        return $data;
    }

    /**
     * 根据站点的短信配置发送短信
     * @param $content
     * @param $mobile
     * @param $siteId
     * @return bool
     * @throws \Exception
     */
    public static function sendSmsBySite($content, $mobile, $siteId = 0)
    {
        $site = Site::getCurrentSite();
        if($siteId && $site->getSiteId() != $siteId) $site = new Site($siteId);
        $config = $site->getConfig()->getSmsConfig();
        $smsApi = new SmsApi($config->appid, $config->appkey, $config->sign);
        return $smsApi->sendSms($content, $mobile);
    }
}