<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/10
 * Time: 15:29
 */

namespace Ipower\Common;

use Jenssegers\Agent\Agent;

/**
 * 判断浏览器类型的工具类
 * Class UserAgent
 * @package Ipower\Common
 */
class UserAgent
{
    /**
     * 判断客户端是否为移动设备
     * @param bool $includeTablet 是否包含平板，如果是，那平板和手机都认为是移动设备，否则只认手机
     * @return bool
     */
    public static function isMobile($includeTablet = true){
        $agent = new Agent();
        $iswap = $agent->isMobile();
        if($includeTablet) $iswap |= self::isTablet();
        return $iswap;
    }

    /**
     * 判断客户端是否为平板
     * @return bool
     */
    public static function isTablet(){
        $agent = new Agent();
        return $agent->isTablet();
    }

    /**
     * 判断客户端是否为公众号
     * @return bool
     */
    public static function isWxOfficialAccount(){
        $agent = new Agent();
        return $agent->match('MicroMessenger') && !$agent->match('wxwork') && !$agent->match('MiniProgram');
    }

    /**
     * 判断客户端是否为企业微信
     * @return bool
     */
    public static function isWxWork(){
        $agent = new Agent();
        return $agent->match('MicroMessenger') && $agent->match('wxwork') && !$agent->match('MiniProgram');
    }

    /**
     * 判断客户端是否为微信小程序
     * @return bool
     */
    public static function isWxApp(){
        $agent = new Agent();
        return $agent->match('MicroMessenger') && $agent->match('MiniProgram');
    }

    /**
     * 判断客户端是否为PC
     * @return bool
     */
    public static function isPC(){
        return !self::isMobile() && !self::isWxApp() && !self::isWxOfficialAccount();
    }
}