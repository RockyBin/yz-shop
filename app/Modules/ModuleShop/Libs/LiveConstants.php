<?php
/**
 * 直播常量
 * User: liyaohui
 * Date: 2020/3/17
 * Time: 15:45
 */

namespace App\Modules\ModuleShop\Libs;


class LiveConstants
{
    // 直播样式
    const LiveStyle_FullScreen = 0; // 全屏
    const LiveStyle_HalfScreen = 1; // 半屏

    // 直播平台
    const LivePlatForm_DouYin = 1; // 抖音
    const LivePlatForm_KuaiShou = 2; // 快手
    const LivePlatForm_YingKe = 3; // 映客
    const LivePlatForm_HuYa = 4; // 虎牙
    const LivePlatForm_YY = 5;
    const LivePlatForm_YiZhiBo = 6; // 一直播

    // 导航类型
    const LiveNavType_Button = 0; // 按钮导航
    const LiveNavType_Menu = 1; // 菜单导航

    // 导航按钮类型
    const LiveNavLinkType_Follow = 0; // 关注
    const LiveNavLinkType_MoreLive = 1; // 更多直播
    const LiveNavLinkType_Home = 2; // 主页
    const LiveNavLinkType_Cmment = 3; // 评论
    const LiveNavLinkType_Product = 4; // 商品
    const LiveNavLinkType_Coupon = 5; // 优惠券
    const LiveNavLinkType_Like = 6; // 点赞
    const LiveNavLinkType_Share = 7; // 分享
    const LiveNavLinkType_Customize = 8; // 自定义
    const LiveNavLinkType_Interactive = 9; // 互动

    // 直播状态
    const LiveStatus_Ready = 0; // 未开始
    const LiveStatus_Living = 1; // 直播中
    const LiveStatus_End = 2; // 直播结束

    /**
     * 获取直播平台列表
     * @return array
     */
    public static function getLivePlatFormList()
    {
        return [
            self::LivePlatForm_DouYin => '抖音',
            self::LivePlatForm_KuaiShou => '快手',
            self::LivePlatForm_YingKe => '映客',
            self::LivePlatForm_HuYa => '虎牙',
            self::LivePlatForm_YY => 'YY',
            self::LivePlatForm_YiZhiBo => '一直播',
        ];
    }

    /**
     * 获取导航链接列表
     * @return array
     */
    public static function getLiveNavLinkList()
    {
        return [
            self::LiveNavLinkType_Follow => '关注',
            self::LiveNavLinkType_MoreLive => '更多直播',
            self::LiveNavLinkType_Home => '主页',
            self::LiveNavLinkType_Cmment => '评论',
            self::LiveNavLinkType_Product => '商品',
            self::LiveNavLinkType_Coupon => '优惠券',
            self::LiveNavLinkType_Like => '点赞',
            self::LiveNavLinkType_Share => '分享',
            self::LiveNavLinkType_Customize => '自定义',
            self::LiveNavLinkType_Interactive => '互动'
        ];
    }
}