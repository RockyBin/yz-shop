<?php
/**
 * 产品列表
 * User: 李耀辉
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Product;

use App\Modules\ModuleShop\Libs\Activities\FreeFreight;
use App\Modules\ModuleShop\Libs\SmallShop\SmallShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use YZ\Core\Member\Auth;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Product\Product;
use App\Modules\ModuleShop\Libs\Member\MemberAddress;
use App\Modules\ModuleShop\Libs\Shop\NormalShopProduct;
use App\Modules\ModuleShop\Libs\Shop\BaseCalOrderFreight;
use YZ\Core\Member\Member;
use YZ\Core\Site\Site;

class ProductDetailController extends BaseFrontController
{
    /**
     * 产品详情
     * @param Request $request
     * @return array
     */
    public function getDetail(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponseFail('数据异常：ID不能不空');
            }
            $obj = new Product($request->id);
            $params = $request->toArray();
            $member_id = Auth::hasLogin();
            $params['member_id'] = $member_id;
            $data = $obj->getProducDetail($params, true);
            $data['member_id'] = $member_id;
            $data['checkViewPerm'] = $obj->checkViewPerm();
            $data['checkBuyPerm'] = $obj->checkBuyPerm();
            // 小店展示
            $smallShopEnable = Site::getCurrentSite()->getConfig()->getModel()->small_shop_status;
            if($smallShopEnable){
                $smallShopMid = $member_id;
                if(!$smallShopMid) $smallShopMid = $request->session()->get("invite");
                if(!$smallShopMid) $smallShopMid = $request->cookie("invite");
                $smallShop = SmallShop::getRecentlySmallShopInfo($smallShopMid);
            }
            $data['small_shop'] = $smallShop;
            //合并基础销量+真实销量
            $data['sold_count'] = $data['sold_count'] + $data['base_sold_count'];
            //更新浏览量的值
            $obj->incrementHits();
            //满额包邮
            $freeFreight = (new FreeFreight())->getWithProducts([$request->id]);
            if($freeFreight) $freeFreight->money = moneyCent2Yuan($freeFreight->money);
            $data['free_freight'] = $freeFreight;
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * SKU 数据
     * @param Request $request
     * @return array
     */
    public function getSku(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponseFail('数据异常：ID不能不空');
            }
            $obj = new Product($request->id);
            $member_id = Auth::hasLogin();
            $data = $obj->getSku($member_id, true);
            return makeApiResponseSuccess('成功', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * SKU 数据
     * @param Request $request
     * @return array
     */
    public function getAdressList()
    {
        try {
            $member_id = Auth::hasLogin();
            if (!$member_id) {
                return makeApiResponseSuccess(trans('shop-front.common.action_ok'), []);
            }
            $list = (new MemberAddress($member_id))->getAddressList();
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function getFreight(Request $request)
    {
        try {
            $cacheKey = "freight_" . md5($request->fullUrl());
            if (Cache::has($cacheKey)) {
                $this->freight = Cache::get($cacheKey);
            } else {
                $pro[] = new NormalShopProduct($request->product_id, $request->sku_id, 1);
                $freight = new BaseCalOrderFreight($request->city, $pro);
                if (!$pro[0]->canDelivery($request->city)) {
                    return makeApiResponse(501, '此区域不在配送范围内');
                }
                if ($request->sku_id == 0) {
                    return makeApiResponse(201, '此区域在配送范围内');
                }
                $this->freight = $freight->getOrderFreight();
                Cache::set($cacheKey, $this->freight, 3);
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), ['freight' => moneyCent2Yuan($this->freight)]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

}