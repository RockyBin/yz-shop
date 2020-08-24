<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuying;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingConstants;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingProducts;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingProductsModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Model\PageMobiModel;
use App\Modules\ModuleShop\Libs\Point\PointGiveHelper;
use App\Modules\ModuleShop\Libs\SiteConfig\StoreConfig;
use App\Modules\ModuleShop\Libs\SmallShop\SmallShop;
use Illuminate\Http\Request;
use YZ\Core\Member\Auth;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\WxConfig;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use App\Modules\ModuleShop\Libs\UI\StyleColorMobi;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use YZ\Core\License\SNUtil;
use YZ\Core\Member\Member;
use App\Modules\ModuleShop\Libs\Model\LiveModel;
use function GuzzleHttp\Psr7\parse_query;
use YZ\Core\Weixin\WxWork;

class IndexController extends BaseFrontController
{
    /**
     * 获取网站信息
     * @return array
     */
    public function getSiteInfo()
    {
        try {
            // 获取色系
            $styleColor = new StyleColorMobi();
            $styleColor = $styleColor->getSiteColor();
            unset($styleColor['color_info']['images']);
            // 获取会员登录信息
            $hasLogin = Auth::hasLogin();
            // 分销设置
            $distributionConfig = DistributionSetting::getCurrentSiteSetting();
            $productCommentConfig = Site::getCurrentSite()->getConfig()->getProductCommentConfig();
            // 版本权限
            $site = Site::getCurrentSite();
            $sn = SNUtil::getSNInstanceBySite($site->getSiteId());
            $LicensePerm = $sn->getPermission(1);
            // 版权设置
            $copyRight = $site->getConfig()->getCopyRight();
			if ($sn->hasPermission(Constants::FunctionPermission_ENABLE_FORCE_HIDE_COPYRIGHT)) {
				$copyRight['status'] = 0;
			}
            // 商城设置
            $shopConfig = new ShopConfig();
            // 第三客服设置
            $gConfig = $site->getConfig()->getModel();
            $service3rdPages = [];
            if ($gConfig->service3rd_pages) {
                $tmp = json_decode($gConfig->service3rd_pages, true);
                if (is_array($tmp)) $service3rdPages = $tmp;
            }
            $service3rd = [
                'service3rd_status' => $gConfig->service3rd_status,
                'service3rd_code' => $gConfig->service3rd_code,
                'service3rd_pages' => $service3rdPages
            ];
            $wsConfig = [
                'ws_url' => config('app.WS_URL'),
                'ws_user' => config('app.WS_USER'),
                'ws_pwd' => config('app.WS_PWD')
            ];
            return makeApiResponseSuccess('ok', [
                'site_id' => $site->getSiteId(),
                'site_status' => $site->getModel()->status,
                'siteComdataPath' => Site::getSiteComdataDir(),
                'style_info' => $styleColor['color_info'],
                'member_login' => $hasLogin,
                'distribution_config' => $distributionConfig,
                'product_video_page' => $gConfig->product_video_page,
                'product_comment_config' => $productCommentConfig,
                'shop_config' => $shopConfig->getInfo()['info'],
                'ws_config' => $wsConfig,
                'service3rd' => $service3rd,
                'LicensePerm' => $LicensePerm,
                'CopyRight' => $copyRight,
                'config' => [
                    'product_list_style' => $gConfig->product_list_style,
                    'product_list_show_sale_num' => $gConfig->product_list_show_sale_num,
                    'retail_status' => $gConfig['retail_status']
                ]
            ]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取微信JSSDK配置信息
     * @param Request $request
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getConfigWxJSSDK(Request $request)
    {
        try {
            if (getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxOfficialAccount) {
                $wxConfig = new WxConfig();
                if (!$wxConfig->infoIsFull()) {
                    return makeApiResponseFail('config error');
                }
                $domain = $wxConfig->getDomain();
                $jsSdk = Site::getCurrentSite()->getOfficialAccount()->getJSSDK();
            } elseif (getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxWork || 1) {
                $wxWork = new WxWork();
                $jsSdk = $wxWork->getJSSDK();
            }
            // 分享数据格式
            $shareData = [
                'title' => '',
                'imgUrl' => '',
                'desc' => '',
                'link' => '',
            ];
            $url = $request->get('url'); // 原地址，用于假面（因为vue的路由关系，会显示from的地址）
            $fullPath = $request->get('fullPath'); // 当前地址path
            if (!$url) {
                $url = getHttpProtocol() . "://" . $domain;
            }
            $link = '';
            // 商城总设置
            $shopConfig = new ShopConfig();
            $shopConfigData = $shopConfig->getInfo();
            // 分析匹配
            if (str_contains($fullPath, "agent-invite-show")) {
                $shareData = $this->processAgentInviteShare($domain, $fullPath);
                $link = $shareData['link'];
            } elseif (str_contains($fullPath, 'dealer-invite-show')) {
                $shareData = $this->processDealerInviteShare($domain, $fullPath);
                $link = $shareData['link'];
            } elseif (str_contains($fullPath, 'smallshop-home')) {
                $shareData = $this->processSmallShopShare($domain, $fullPath);
                $link = $shareData['link'];
            } elseif (str_contains($fullPath, "/product/product-detail") || str_contains($fullPath, "/product/product-video")) {
                $shareData = $this->processProductShare($domain, $fullPath);
                $link = $shareData['link'];
            } elseif (str_contains($fullPath, "/groupbuying/product-detail")) {
                $shareData = $this->processGroupProductShare($domain, $fullPath);
                $link = $shareData['link'];
            } elseif (str_contains($fullPath, "/live/live-detail")) {
                $shareData = $this->processLiveShare($domain, $fullPath);
                $link = $shareData['link'];
            } elseif (str_contains($fullPath, "/groupbuying/group-buying-detail") || str_contains($fullPath, "/product/payment-success") || str_contains($fullPath, "/groupbuying/group-share-purchase")) {
                $shareData = $this->processGroupBuyingDetailShare($domain, $fullPath);
                $link = $shareData['link'];
            } elseif (preg_match('/^\/page\/([0-9]+)/', $fullPath, $matches)) {
                // 自定义页面
                $pageId = intval($matches[1]);
                if ($pageId) {
                    $pageModel = PageMobiModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('id', $pageId)->first();
                    if ($pageModel) {
                        $shareData['title'] = $pageModel->title;
                        $shareData['desc'] = $pageModel->description;
                        if ($shopConfigData && $shopConfigData['info']) {
                            $shareData['imgUrl'] = getHttpProtocol() . "://" . $domain . $shopConfigData['info']['logo'];
                        }
                        $link = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash/page/" . $pageId;
                    }
                }
            }
            if (empty($link)) {
                // 首页
                $link = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash";
                if ($shopConfigData && $shopConfigData['info']) {
                    $shareData['imgUrl'] = getHttpProtocol() . "://" . $domain . $shopConfigData['info']['logo'];
                    $shareData['title'] = $shopConfigData['info']['name'];
                    $shareData['desc'] = $shopConfigData['info']['describe'];
                }
            }

            // 去掉#号后面的
            $url = explode('#', $url)[0];
            // 推荐人
            $memberId = Auth::hasLogin();
            if (!$memberId && $request->cookie('member_id')) $memberId = intval($request->cookie('member_id'));
            if ($memberId && !str_contains($link, 'invite=')) {
                if (str_contains($link, '?')) {
                    $link .= '&invite=' . $memberId;
                } else {
                    $link .= '?invite=' . $memberId;
                }
            }
            $shareData['link'] = $link;
            // 获取配置信息
            $jsSdk->setUrl($url);
            $apis = myToArray($request->get('apis'));
            if (count($apis) == 0) {
                // 默认开启的权限
                $apis = [
                    "getLocation",
                    "showMenuItems",
                    "hideMenuItems",
                    "hideAllNonBaseMenuItem",
                    "showAllNonBaseMenuItem",
                    "closeWindow", // 关闭窗口
                    "scanQRCode", // 扫描二维码
                    "chooseWXPay", // 微信支付
                    "updateAppMessageShareData", // 分享给朋友 和 分享到qq
                    "updateTimelineShareData", // 分享到朋友圈 和 分享到qq空间
                    "onMenuShareWeibo", // 分享到微博
                    "onMenuShareTimeline",
                    "onMenuShareAppMessage",
                    "onMenuShareQQ",
                    "onMenuShareQZone",
                ];
            }
            $config = $jsSdk->getConfigArray($apis, false);
            return makeApiResponseSuccess('ok', [
                'config' => $config,
                'share_data' => $shareData,
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    private function processProductShare($domain, $url)
    {
        // 产品详情
        $productId = 0;
        $urlData = explode('#', $url, 2);
        $urlHash = count($urlData) > 1 ? $urlData[1] : $urlData[0];
        $search = explode('?', $urlHash, 2);
        // 因为微信分享的时候，会过滤掉#号和后面的内容，所以分享时，将URL里的#替换为vuehash
        // 然后在index.php中，当进入页面时，重新将vuehash替换回来
        $productLink = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash" . $search[0];
        $params = count($search) > 1 ? $search[1] : $search[0];
        $params = explode('&', $params);
        $paramsStr = [];
        foreach ($params as $param) {
            $keyValue = explode('=', $param, 2);
            if (count($keyValue) == 2) {
                // 排除原来的 invite
                if (strtolower($keyValue[0]) != 'invite') {
                    $paramsStr[] = $keyValue[0] . '=' . $keyValue[1];
                }
                if ($keyValue[0] == 'id') {
                    $productId = intval(trim($keyValue[1]));
                    break;
                }
            }
        }
        if ($paramsStr) {
            $productLink .= '?' . implode('&', $paramsStr);
        }
        if ($productId) {
            $productModel = ProductModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('id', $productId)->first();
            if ($productModel) {
                $price = intval($productModel->price);
                $defaultDesc = '仅售' . moneyCent2Yuan($price) . '元！' . $productModel->name;
                $shareData['title'] = $productModel->name;
                $shareData['desc'] = $productModel->describe ? $productModel->describe : $defaultDesc;
                $shareData['imgUrl'] = getHttpProtocol() . "://" . $domain . Site::getSiteComdataDir() . explode(',', $productModel->small_images)[0];
                $shareData['link'] = $productLink;
            }
        }
        return $shareData;
    }

    private function processGroupProductShare($domain, $url)
    {
        // 产品详情
        $groupBuyingProductId = 0;
        $urlData = explode('#', $url, 2);
        $urlHash = count($urlData) > 1 ? $urlData[1] : $urlData[0];
        $search = explode('?', $urlHash, 2);
        // 因为微信分享的时候，会过滤掉#号和后面的内容，所以分享时，将URL里的#替换为vuehash
        // 然后在index.php中，当进入页面时，重新将vuehash替换回来
        $productLink = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash" . $search[0];
        $params = count($search) > 1 ? $search[1] : $search[0];
        $params = explode('&', $params);
        $paramsStr = [];
        foreach ($params as $param) {
            $keyValue = explode('=', $param, 2);
            if (count($keyValue) == 2) {
                // 排除原来的 invite
                if (strtolower($keyValue[0]) != 'invite') {
                    $paramsStr[] = $keyValue[0] . '=' . $keyValue[1];
                }
                if ($keyValue[0] == 'id') {
                    $groupBuyingProductId = intval(trim($keyValue[1]));
                    break;
                }
            }
        }
        if ($paramsStr) {
            $productLink .= '?' . implode('&', $paramsStr);
        }
        if ($groupBuyingProductId) {
            $productModel = GroupBuyingProductsModel::query()
                ->leftJoin('tbl_product as p', 'p.id', 'master_product_id')
                ->where('tbl_group_buying_products.site_id', Site::getCurrentSite()->getSiteId())
                ->where('tbl_group_buying_products.id', $groupBuyingProductId)
                ->select(['tbl_group_buying_products.min_price as price', 'p.name', 'p.describe', 'p.small_images'])
                ->first();
            if ($productModel) {
                $price = intval($productModel->price);
                $defaultDesc = '仅售' . moneyCent2Yuan($price) . '元！' . $productModel->name;
                $shareData['title'] = $productModel->name;
                $shareData['desc'] = $productModel->describe ? $productModel->describe : $defaultDesc;
                $shareData['imgUrl'] = getHttpProtocol() . "://" . $domain . Site::getSiteComdataDir() . explode(',', $productModel->small_images)[0];
                $shareData['link'] = $productLink;
            }
        }
        return $shareData;
    }

    private function processGroupBuyingDetailShare($domain, $url)
    {
        // 产品详情
        $groupbuyingId = 0;
        $urlData = explode('#', $url, 2);
        $urlHash = count($urlData) > 1 ? $urlData[1] : $urlData[0];
        $search = explode('?', $urlHash, 2);
        // 因为微信分享的时候，会过滤掉#号和后面的内容，所以分享时，将URL里的#替换为vuehash
        // 然后在index.php中，当进入页面时，重新将vuehash替换回来
        $groupbuyingLink = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash" . $search[0];
        $params = count($search) > 1 ? $search[1] : $search[0];
        $params = explode('&', $params);
        $paramsStr = [];
        foreach ($params as $param) {
            $keyValue = explode('=', $param, 2);
            if (count($keyValue) == 2) {
                if ($keyValue[0] == 'group_buying_id') {
                    $groupbuyingId = intval(trim($keyValue[1]));
                    break;
                } elseif ($keyValue[0] == 'order_id') {
                    $order = OrderModel::query()->find(trim($keyValue[1]));
                    $groupbuyingId = $order->activity_id;
                    break;
                }
            }
        }
        if ($paramsStr) {
            $groupbuyingLink .= '?' . implode('&', $paramsStr);
        }
        if ($groupbuyingId) {
            $groupbuying = (new GroupBuying($groupbuyingId))->getModel();
            $productModel = GroupBuyingProductsModel::query()
                ->from('tbl_group_buying_products as gbp')
                ->leftJoin('tbl_product as p', 'gbp.master_product_id', 'p.id')
                ->where('gbp.site_id', Site::getCurrentSite()->getSiteId())
                ->where('gbp.id', $groupbuying->group_product_id)
                ->first();
            if ($productModel && $groupbuying->status != GroupBuyingConstants::GroupBuyingTearmStatus_Yes) {
                $price = intval($productModel->min_price);
                $title = '【仅剩下' . ($groupbuying->need_people_num - $groupbuying->current_people_num) . '个名额】 我' . moneyCent2Yuan($price) . '元！拼了【' . $productModel->name . '】';
                $shareData['title'] = $title;
                $shareData['desc'] = '我买了' . $productModel->name;
                $shareData['imgUrl'] = getHttpProtocol() . "://" . $domain . Site::getSiteComdataDir() . (explode(',', $productModel->small_images)[0]);
                $shareData['link'] = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash" . '/groupbuying/group-share-purchase?group_buying_id=' . $groupbuyingId;
            }

        }
        return $shareData;
    }


    private function processLiveShare($domain, $url)
    {
        // 直播详情
        $liveId = 0;
        $urlData = explode('#', $url, 2);
        $urlHash = count($urlData) > 1 ? $urlData[1] : $urlData[0];
        $search = explode('?', $urlHash, 2);
        // 因为微信分享的时候，会过滤掉#号和后面的内容，所以分享时，将URL里的#替换为vuehash
        // 然后在index.php中，当进入页面时，重新将vuehash替换回来
        $liveLink = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash" . $search[0];
        $params = count($search) > 1 ? $search[1] : $search[0];
        $params = explode('&', $params);
        $paramsStr = [];
        foreach ($params as $param) {
            $keyValue = explode('=', $param, 2);
            if (count($keyValue) == 2) {
                // 排除原来的 invite
                if (strtolower($keyValue[0]) != 'invite') {
                    $paramsStr[] = $keyValue[0] . '=' . $keyValue[1];
                }
                if ($keyValue[0] == 'id') {
                    $liveId = intval(trim($keyValue[1]));
                    break;
                }
            }
        }
        if ($paramsStr) {
            $liveLink .= '?' . implode('&', $paramsStr);
        }
        if ($liveId) {
            $liveModel = LiveModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('id', $liveId)->first();
            if ($liveModel) {
                $shareData['title'] = $liveModel->title;
                $shareData['desc'] = $liveModel->intro;
                $shareData['imgUrl'] = getHttpProtocol() . "://" . $domain . Site::getSiteComdataDir() . $liveModel->list_poster;
                $shareData['link'] = $liveLink;
            }
        }
        return $shareData;
    }

    /**
     * 处理代理授权邀请的微信分享
     *
     * @return array
     */
    private function processAgentInviteShare($domain, $url)
    {
        $urlInfo = parse_url($url);
        $params = parse_query($urlInfo['query']);
        $member = new  Member($params['invite']);
        $shareData = [];
        $shopConfig = new ShopConfig();
        $shopConfigData = $shopConfig->getInfo();
        $levelName = Constants::getAgentLevelTextForFront($params['inviteLevel'], $params['inviteLevel'] . "级代理");
        $shareData['title'] = $member->getModel()->nickname . " 邀请您成为 " . $shopConfigData['info']['name'] . " 的 " . $levelName;
        $shareData['desc'] = "点击填写信息即可成为 " . $levelName;
        $shareData['imgUrl'] = $member->getMemberHeadUrl($domain);
        $shareData['link'] = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash/agent/agent-invite-show?invite=" . $params['invite'] . "&inviteLevel=" . $params['inviteLevel'] . '&qrcode=' . urlencode($params['qrcode']);
        $shareData['link'] .= "&title=" . urlencode($shareData['title']);
        return $shareData;
    }

    /**
     * 处理代理授权邀请的微信分享
     *
     * @return array
     */
    private function processDealerInviteShare($domain, $url)
    {
        $urlInfo = parse_url($url);
        $params = parse_query($urlInfo['query']);
        $member = new  Member($params['invite']);
        $shareData = [];
        $shopConfig = new ShopConfig();
        $shopConfigData = $shopConfig->getInfo();
        $levelName = DealerLevelModel::query()->where('site_id', getCurrentSiteId())
            ->where('id', $params['inviteLevel'])
            ->value('name');
        $shareData['title'] = $member->getModel()->nickname . " 邀请您成为 " . $shopConfigData['info']['name'] . " 的 " . $levelName;
        $shareData['desc'] = "点击填写信息即可成为 " . $levelName;
        $shareData['imgUrl'] = $member->getMemberHeadUrl($domain);
        $shareData['link'] = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash/dealer/dealer-invite-show?invite=" . $params['invite'] . "&inviteLevel=" . $params['inviteLevel'] . '&qrcode=' . urlencode($params['qrcode']);
        $shareData['link'] .= "&title=" . urlencode($shareData['title']);
        return $shareData;
    }

    /**
     * 处理小店微信分享
     *
     * @return array
     */
    private function processSmallShopShare($domain, $url)
    {
        $urlInfo = parse_url($url);
        $params = parse_query($urlInfo['query']);
        $memberId = $params['member_id'];
        if (!$memberId) $memberId = Auth::hasLogin();
        if (!$memberId && \Illuminate\Support\Facades\Request::cookie('member_id')) $memberId = intval(\Illuminate\Support\Facades\Request::cookie('member_id'));
        $shareData = [];
        $smallShop = SmallShop::getInfo(['member_id' => $memberId, 'noqrcode' => 1]);
        $shareData['title'] = $smallShop['name'];
        $shareData['desc'] = $smallShop['description'] ? $smallShop['description'] : '';
        if ($smallShop['logo']) $shareData['imgUrl'] = preg_match('@^(http:|https:)@i', $smallShop['logo']) ? $smallShop['logo'] : getHttpProtocol() . "://" . $domain . Site::getSiteComdataDir() . $smallShop['logo'];
        else $shareData['imgUrl'] = '';
        $shareData['link'] = getHttpProtocol() . "://" . $domain . "/shop/front/vuehash/smallshop/smallshop-home?member_id=" . $memberId . "&invite=" . $memberId;
        return $shareData;
    }

    /**
     * 分享后事件
     * @return array
     */
    public function afterShare()
    {
        try {
            // 检查是否登录
            $memberId = Auth::hasLogin();
            if ($memberId) {
                PointGiveHelper::GiveForShare($memberId);
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取站点的客服配置
     * @return array
     */
    public function getSiteServiceInfo()
    {
        try {
            $storeConfig = new StoreConfig();
            $storeConfigData = $storeConfig->getInfo();
            return makeApiResponseSuccess('ok', [
                'custom_mobile' => $storeConfigData['data']['custom_mobile'],
                'qrcode' => $storeConfigData['data']['qrcode']
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}