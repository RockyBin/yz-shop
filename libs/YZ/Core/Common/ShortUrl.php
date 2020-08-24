<?php
/**
 * Created by Aison.
 */

namespace YZ\Core\Common;

use EasyWeChat\Factory as EasyWeChatFactory;
use YZ\Core\Logger\Log;
use YZ\Core\Weixin\WxConfig;


class ShortUrl
{
    /**
     * 获取短地址
     * @param $longUrl
     * @return string
     */
    public static function getShortUrl($longUrl)
    {
        $longUrl = trim($longUrl);
        if (!$longUrl) return false;

        return self::easyWechatShortUrl($longUrl);
    }

    /**
     * EasyWechat
     * @param $longUrl
     * @return string
     */
    private static function easyWechatShortUrl($longUrl)
    {
        try {
            $wxConfig = new WxConfig();
            if ($wxConfig->infoIsFull()) {
                $options = [
                    'app_id' => $wxConfig->getModel()->appid,
                    'secret' => $wxConfig->getModel()->appsecret,
                ];
                $app = EasyWeChatFactory::officialAccount($options);
                $res = $app->url->shorten($longUrl);
                if (intval($res['errcode']) == 0) {
                    return trim($res['short_url']);
                } else {
                    Log::writeLog('shortUrl', $longUrl);
                    Log::writeLog('shortUrl', $res['errmsg']);
                }
            }
        } catch (\Exception $ex) {
            Log::writeLog('shortUrl', $longUrl);
            Log::writeLog('shortUrl', $ex->getMessage());
        }
    }

    /**
     * 百度
     * @param $longUrl
     * @return bool|string
     */
    private static function baiduShortUrl($longUrl)
    {
        try {
            $host = 'https://dwz.cn';
            $path = '/admin/v2/create';
            $url = $host . $path;
            $method = 'POST';
            $content_type = 'application/json';

            // 设置Token
            $token = '8128417df369e2645538df1bd197155a';

            // 设置待注册长网址
            $bodys = array('url' => $longUrl);

            // 配置headers
            $headers = array('Content-Type:' . $content_type, 'Token:' . $token);

            // 创建连接
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_FAILONERROR, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($bodys));

            // 发送请求
            $response = curl_exec($curl);
            curl_close($curl);

            if ($response) {
                $res = json_decode($response, true);
                if (intval($res['Code']) == 0) {
                    return trim($res['ShortUrl']);
                } else {
                    Log::writeLog('shortUrl', $longUrl);
                    Log::writeLog('shortUrl', $res['ErrMsg']);
                }
            }

        } catch (\Exception $ex) {
            Log::writeLog('shortUrl', $longUrl);
            Log::writeLog('shortUrl', $ex->getMessage());
        }

        return false;
    }
}