<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Live;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Live\Live;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\LiveViewerModel;
use App\Modules\ModuleShop\Libs\SiteConfig\ShopConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use YZ\Core\Member\Auth;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Site\Site;
use YZ\Core\Weixin\WxUser;

class LiveController extends BaseFrontController
{
    /**
     * 获取某直播的信息
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->id);
            //直播基本信息
            $liveInfo = $live->getInfo(true);
            $liveInfo['real_online_num'] = $live->getOnlineNum();
            //更新点击数
            if (intval($liveInfo['status']) === 1) $live->changeHits(1);

            //商品列表
            $productList = $live->getProductList();

            //优惠券列表
            $couponList = $live->getCouponList(['status' => 1, 'count_member_canuse' => true]);

            //菜单列表
            $menuList = $live->getMenuList(true, ['status' => 1]);

            //当前上屏的优惠券信息
            $onScreenCoupon = $live->getOnScreenCoupon();

            //商城信息
            $shopInfo = (new ShopConfig())->getInfo();

            //当前上屏的商品信息
            $onScreenProduct = $live->getOnScreenProduct();
            if ($onScreenProduct) {
                $onScreenProduct->price = moneyCent2Yuan($onScreenProduct->price);
                $onScreenProduct->small_image = Site::getSiteComdataDir() . explode(',', $onScreenProduct->small_images)[0];
            }

            //获取当前会员信息
            $memberId = Auth::hasLogin();
            if (!$memberId) $memberId = Cookie::get('member_id');
            $memberInfo = (new Member($memberId))->getModel();
            if ($memberInfo) {
                $memberInfo['headurl'] = Member::getHeadUrl($memberInfo['headurl']);
            } elseif (Cookie::get('auth_name')) { //这个cookie是微信授权登录的时候记录下来的
                $memberInfo['nickname'] = Cookie::get("auth_name");
                $memberInfo['headurl'] = Cookie::get("auth_headurl");
            }

            //正在观看此直播的前10个人的列表
            $sql = "select v.*, m.nickname, m.headurl from tbl_live_viewer as v left join tbl_member as m on m.id = v.member_id";
            $sql .= " where live_id = :live_id and v.site_id = :site_id and v.status = 1 order by v.updated_at desc limit 20";
            $viewerList = LiveViewerModel::runSql($sql, ['live_id' => $request->id, 'site_id' => getCurrentSiteId()]);
            foreach ($viewerList as &$item) {
                $item->headurl = Member::getHeadUrl($item->headurl);
            }
            unset($item);
            //添加几个假的观众列表
            if (count($viewerList) < 20) {
                $virtualList = WxUserModel::runSql("select * from tbl_wx_user where platform = 0 order by id desc limit 20");
                foreach ($virtualList as $item) {
                    $viewerList[] = ['nickname' => $item->nickname, 'headurl' => $item->headimgurl];
                }
            }

            return makeApiResponseSuccess('成功', [
                'liveInfo' => $liveInfo,
                'memberInfo' => $memberInfo,
                'viewerList' => $viewerList,
                'productList' => $productList,
                'couponList' => $couponList,
                'menuList' => $menuList,
                'onScreenCoupon' => $onScreenCoupon,
                'onScreenProduct' => $onScreenProduct,
                'shopInfo' => $shopInfo
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getCoupon(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->id);

            //优惠券列表
            $couponList = $live->getCouponList(['status' => 1, 'count_member_canuse' => true]);

            //当前上屏的优惠券信息
            $onScreenCoupon = $live->getOnScreenCoupon();

            return makeApiResponseSuccess('成功', [
                'couponList' => $couponList,
                'onScreenCoupon' => $onScreenCoupon,
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function addLike(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $live = new Live($request->id);
            $live->changeLike($request->num);
            return makeApiResponseSuccess('成功');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getList(Request $request)
    {
        try {
            $params = $request->toArray();
            $params['show_live_list'] = 1;
            $list = live::getList($params, $params['page'], $params['page_size']);
            if ($list['list']) {
                foreach ($list['list'] as &$item) {
                    $expected_live_time = strtotime($item['expected_live_time']);
                    $item['expected_live_time'] = date("n", $expected_live_time) . "月" . date("j", $expected_live_time) . "日" . date("H", $expected_live_time) . ":" . date("i", $expected_live_time);
                }
            }

            return makeApiResponseSuccess('成功', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}