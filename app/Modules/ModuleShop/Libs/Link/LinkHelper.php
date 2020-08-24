<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/29
 * Time: 16:21
 */

namespace App\Modules\ModuleShop\Libs\Link;

use App\Modules\ModuleShop\Libs\Constants;
use Illuminate\Support\Facades\Request;
use YZ\Core\License\SNUtil;
use YZ\Core\Site\Site;

/**
 *
 * Class LinkHelper
 * @package App\Modules\ModuleShop\Libs\Common
 */
class LinkHelper
{
    /**
     * 此方式用来将链接属性转换为URL地址
     * @param int $type 链接类型
     * @param string $data 链接数据，当类型是产品分类时，数据就是分类ID，当类型是产品详情时，数据就是产品ID
     * @return string
     */
    public static function getUrl($type, $data = '')
    {
        $isMobile = in_array(getCurrentTerminal(), [\YZ\Core\Constants::TerminalType_Mobile, \YZ\Core\Constants::TerminalType_WxOfficialAccount, \YZ\Core\Constants::TerminalType_WxApp]);
        $isMobile |= Request::get('isMobile') === '1'; //变量从前端vue框架的main.js中定义
        $url = "/";
        switch ($type) {
            case LinkConstants::LinkType_Home:
                $url = '/';
                break;
            case LinkConstants::LinkType_Page:
                // 自定义页面
                $url = "/page/" . $data;
                break;
            case LinkConstants::LinkType_External:
                // 站外链接
                $url = $data;
                break;
            case LinkConstants::LinkType_ProductClass:
                // 分类页面
                $url = '/product/product-class';
                break;
            case LinkConstants::LinkType_ProductList:
                // 产品列表 有data 则是分类id
                $url = '/product/product-list' . ($data ? '?classId=' . $data : '');
                break;
            case LinkConstants::LinkType_ProductDetail:
                // 产品详情 需传入产品id
                $url = '/product/product-detail?id=' . $data;
                break;
            case LinkConstants::LinkType_LiveList:
                // 直播列表 有 data 则是分类id
                $url = '/live/live-list' . ($data ? '?classId=' . $data : '');
                break;
            case LinkConstants::LinkType_LiveDetail:
                // 产品详情 需传入产品id
                $url = '/live/live-detail?id=' . $data;
                break;
            case LinkConstants::LinkType_GroupBuyingDetail:
                // 拼团活动详情 需传入拼团活动id
                $url = '/groupbuying/special-field?id=' . $data;
                break;
            case LinkConstants::LinkType_GroupBuyingProductDetail:
                // 拼团商品详情 需传入拼团商品id
                $url = '/groupbuying/product-detail?id=' . $data;
                break;
            case LinkConstants::LinkType_UserCenter:
                // 会员中心
                $url = '/member/member-center';
                break;
            case LinkConstants::LinkType_CouponCenter:
                // 领券中心
                $url = '/member/coupon-center';
                break;

            case LinkConstants::LinkType_ShoppingCart:
                // 购物车
                $url = '/product/shopping-cart';
                break;

            case LinkConstants::LinkType_OrderList:
                // 订单列表
                $url = '/member/memberOrder';
                break;
            case LinkConstants::LinkType_AfterSaleList:
                // 售后订单
                $url = '/member/memberAfterList';
                break;
            case LinkConstants::LinkType_DistributionCenter:
                // 分销中心
                $url = '/distributor/distributor-center';
                break;
            case LinkConstants::LinkType_AgentCenter:
                // 代理中心
                $url = '/agent/agent-center';
                break;
            case LinkConstants::LinkType_AreaAgentCenter:
                // 区域代理中心
                $url = '/areaagent/areaagent-center';
                break;
            case LinkConstants::LinkType_AuthCertQuery:
                // 授权证书查询
                $url = '/dealer/dealer-authcert-query';
                break;
			case LinkConstants::LinkType_AuthCert:
                // 我的授权证书
                $url = '/dealer/dealer-authcert';
                break;
			case LinkConstants::LinkType_DealerInvite:
                // 授权邀请
                $url = '/dealer/dealer-invite';
                break;
			case LinkConstants::LinkType_DealerVerify:
                // 审核管理
                $url = '/dealer/dealer-verify';
                break;
            case LinkConstants::LinkType_CloudStockCenter:
                // 云仓工作台
                $url = '/cloudstock/cloud-center';
                break;
            case LinkConstants::LinkType_SecurityCode:
                // 防伪码查询
                $url = '/securitycheck/security-check';
                break;
            case LinkConstants::LinkType_CustomerService:
                // 客服链接
                $url = '/member/member-customer-service';
                break;
            case LinkConstants::LinkType_None:
                // 无链接
                $url = 'javascript:;';
                break;
        }
        // 排除外链
        if ($type != LinkConstants::LinkType_External && $type != LinkConstants::LinkType_None) {
            // 目前只有手机版本，先强制走手机版的链接地址
            if (true || $isMobile && $url && !preg_match('/^https?:/', $url)) $url = "#" . $url; // 前面加 '#' 是因为手机端是用vue做的，vue的根目录是 "#"
        }
        return $url;
    }

    /**
     * 获取链接选择器中的固定页面
     * @return array
     */
    public static function getAllStaticUrl()
    {
        $arr = [
            [
                'name' => '店铺主页',
                'url' => self::getUrl(LinkConstants::LinkType_Home),
                'type' => LinkConstants::LinkType_Home
            ],
            [
                'name' => '个人中心',
                'url' => self::getUrl(LinkConstants::LinkType_UserCenter),
                'type' => LinkConstants::LinkType_UserCenter
            ],
            [
                'name' => '领券中心',
                'url' => self::getUrl(LinkConstants::LinkType_CouponCenter),
                'type' => LinkConstants::LinkType_CouponCenter
            ],
            [
                'name' => '购物车',
                'url' => self::getUrl(LinkConstants::LinkType_ShoppingCart),
                'type' => LinkConstants::LinkType_ShoppingCart
            ],
            [
                'name' => '全部商品',
                'url' => self::getUrl(LinkConstants::LinkType_ProductList),
                'type' => LinkConstants::LinkType_ProductList
            ],
            [
                'name' => '商品分类',
                'url' => self::getUrl(LinkConstants::LinkType_ProductClass),
                'type' => LinkConstants::LinkType_ProductClass
            ],
            [
                'name' => '订单列表',
                'url' => self::getUrl(LinkConstants::LinkType_OrderList),
                'type' => LinkConstants::LinkType_OrderList
            ],
            [
                'name' => '售后列表',
                'url' => self::getUrl(LinkConstants::LinkType_AfterSaleList),
                'type' => LinkConstants::LinkType_AfterSaleList
            ]
        ];
        $sn = SNUtil::getSNInstanceBySite(Site::getCurrentSite()->getModel());
        if($sn->hasPermission(Constants::FunctionPermission_ENABLE_LIVE)){
            $newItem = [[
                'name' => '直播广场',
                'url' => self::getUrl(LinkConstants::LinkType_LiveList),
                'type' => LinkConstants::LinkType_LiveList
            ]];
            array_splice($arr,3,0, $newItem);
        }
        if($sn->hasPermission(Constants::FunctionPermission_ENABLE_DISTRIBUTION)){
            $arr[] = [
                'name' => '分销中心',
                'url' => self::getUrl(LinkConstants::LinkType_DistributionCenter),
                'type' => LinkConstants::LinkType_DistributionCenter
            ];
        }
        if($sn->hasPermission(Constants::FunctionPermission_ENABLE_AGENT)){
            $arr[] = [
                'name' => '代理中心',
                'url' => self::getUrl(LinkConstants::LinkType_AgentCenter),
                'type' => LinkConstants::LinkType_AgentCenter
            ];
        }
        if($sn->hasPermission(Constants::FunctionPermission_ENABLE_AREA_AGENT)){
            $arr[] = [
                'name' => '区域代理中心',
                'url' => self::getUrl(LinkConstants::LinkType_AreaAgentCenter),
                'type' => LinkConstants::LinkType_AreaAgentCenter
            ];
        }
        if($sn->hasPermission(Constants::FunctionPermission_ENABLE_CLOUDSTOCK)){
            $arr[] = [
                'name' => '经销商中心',
                'url' => self::getUrl(LinkConstants::LinkType_CloudStockCenter),
                'type' => LinkConstants::LinkType_CloudStockCenter
            ];
        }
        if($sn->hasPermission(Constants::FunctionPermission_ENABLE_AUTHCERT)){
            $arr[] = [
                'name' => '授权查询',
                'url' => self::getUrl(LinkConstants::LinkType_AuthCertQuery),
                'type' => LinkConstants::LinkType_AuthCertQuery
            ];
			$arr[] = [
                'name' => '授权证书',
                'url' => self::getUrl(LinkConstants::LinkType_AuthCert),
                'type' => LinkConstants::LinkType_AuthCert
            ];
        }
		 if($sn->hasPermission(Constants::FunctionPermission_ENABLE_DEALER_INVITE)){
            $arr[] = [
                'name' => '授权邀请',
                'url' => self::getUrl(LinkConstants::LinkType_DealerInvite),
                'type' => LinkConstants::LinkType_DealerInvite
            ];
        }
		if($sn->hasPermission(Constants::FunctionPermission_ENABLE_CLOUDSTOCK)){
            $arr[] = [
                'name' => '审核管理',
                'url' => self::getUrl(LinkConstants::LinkType_DealerVerify),
                'type' => LinkConstants::LinkType_DealerVerify
            ];
        }
        if($sn->hasPermission(Constants::FunctionPermission_ENABLE_SECURITY_CODE)){
            $arr[] = [
                'name' => '防伪查询',
                'url' => self::getUrl(LinkConstants::LinkType_SecurityCode),
                'type' => LinkConstants::LinkType_SecurityCode
            ];
        }
        $arr[] = [
            'name' => '联系客服',
            'url' => self::getUrl(LinkConstants::LinkType_CustomerService),
            'type' => LinkConstants::LinkType_CustomerService
        ];
        return $arr;
    }

    /**
     * 将 html 里的链接标签进行替换，主要是用在产品详情等地方
     * 因为pc和手机版的链接可能是不一样的，但是内容可能是统一添加修改的，这会导致链接选择器里的地址无法分清是手机还是PC，这里根据A标签里的linkinfo属性统一处理一下
     * @param $html
     * @param string $domain 域名
     * @return mixed
     */
    public static function replaceHtmlLink($html, $domain = '')
    {
        preg_match_all("/<a[^>]+>/", $html, $links, PREG_SET_ORDER);
        foreach ($links as $link) {
            $oldLink = $link[0];
            $link = $link[0];
            preg_match("/linkinfo=('|\")?([^'\"\>]+)('|\")?/", $link, $linkInfo);
            $linkInfo = $linkInfo[2];
            if ($linkInfo) {
                $linkInfo = json_decode(base64_decode($linkInfo), true);
                if ($linkInfo['type']) {
                    $url = static::getUrl($linkInfo['type'], $linkInfo['data']);
                    if ($url) {
                        $url = trim($url);
                        if ($linkInfo['type'] == LinkConstants::LinkType_External) {
                            // 检查外链是否有http，微信里必须带http
                            if (stripos($url, 'http') !== 0) {
                                $url = 'http://' . $url;
                            }
                        } else if ($domain) {
                            // 内链转外链
                            $url = getHttpProtocol() . '://' . $domain . '/' . $url;
                        }
                        preg_match("/href=('|\")?([^'\"]+)/", $link, $href);
                        if ($href[2]) {
                            $link = str_replace($href[0], "href=" . $href[1] . $url, $link);
                        }
                    }
                }
            }
            if ($oldLink != $link) $html = str_replace($oldLink, $link, $html);
        }
        return $html;
    }

    private static function replace($match)
    {
        if (strpos($match[3], "http://") === false && strpos($match[3], "https://") === false && strpos($match[3], "?") === false
            && strpos($match[3], "javascript") === false && strpos($match[3], "#") === false) {
            $hrefstr = "";
            if (strpos($match[3], "/") === false) {/* a.html */
                $hrefstr = self::$domainstr . self::$urlPath;
            } else {/* /ss/a.html */
                $hrefstr = self::$domainstr;
            }
            return "<a {$match[1]}={$match[2]}{$hrefstr}{$match[3]}";
        } else {
            return "<a {$match[1]}={$match[2]}{$match[3]}";
        }
    }
}