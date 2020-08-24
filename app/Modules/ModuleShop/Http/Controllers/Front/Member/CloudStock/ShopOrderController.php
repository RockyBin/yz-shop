<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\CloudStock\ShopOrder;
use App\Modules\ModuleShop\Libs\CloudStock\ShopProduct;
use App\Modules\ModuleShop\Libs\Model\ProductModel;
use App\Modules\ModuleShop\Libs\Model\ProductSkusModel;
use Illuminate\Http\Request;
use Nwidart\Modules\Collection;
use YZ\Core\Site\Site;

/**
 * 云仓在进货过程中用到的订单类，会员中心的云仓订单管理另起其它类，不写在这里
 * Class ShopOrderController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock
 */
class ShopOrderController extends BaseMemberController
{
    /**
     * 在确认下单前，获取订单的商品列表
     * @param Request $request
     * @return array
     */
    public function getGoodsList(Request $request)
    {
        try {
            $site = Site::getCurrentSite();
            $siteId = $site->getSiteId();
            $products = $request->get('products');
            $coll = new Collection($products);
            $productIds = $coll->pluck('product_id')->values()->all();
            $skuIds = $coll->pluck('sku_id')->values()->all();
            $productModels = ProductModel::query()->where('site_id', $siteId)->whereIn('id', $productIds)->get();
            $skuModels = ProductSkusModel::query()->where('site_id', $siteId)->whereIn('id', $skuIds)->get();
            $order = new ShopOrder($this->memberId);
            $productMoney = 0;
            $totalNum = 0;
            foreach ($products as $item) {
                $pm = $productModels->where('id', $item['product_id'])->first();
                $sm = $skuModels->where('id', $item['sku_id'])->first();
                if (!$pm || !$sm) {
                    // 查找不到数据 放到下架列表去
                    $notActiveList[] = ['product_id' => $item['product_id'], 'sku_id' => $item['sku_id']];
                    continue;
                }
                $pro = new ShopProduct($this->memberId, $pm, $sm, $item['num']);
                $productMoney += $pro->calMoney();
                $totalNum += $item['num'];
                $order->addProduct($pro);
            }
            $list = $order->getProductListInfo();
            $productMoney = moneyCent2Yuan($productMoney);
            $totalMoney = $productMoney;
            return makeApiResponseSuccess('ok', ['productList' => $list, 'totalMoney' => $totalMoney, 'productMoney' => $productMoney, 'totalNum' => $totalNum, 'not_active_list' => $notActiveList]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存订单
     * @param Request $request
     * @return array
     */
    public function create(Request $request)
    {
        try {
            $site = Site::getCurrentSite();
            $siteId = $site->getSiteId();
            $products = $request->get('products');
            $coll = new Collection($products);
            $productIds = $coll->pluck('product_id')->values()->all();
            $skuIds = $coll->pluck('sku_id')->values()->all();
            $productModels = ProductModel::query()->where('site_id', $siteId)->whereIn('id', $productIds)->get();
            $skuModels = ProductSkusModel::query()->where('site_id', $siteId)->whereIn('id', $skuIds)->get();
            $order = new ShopOrder($this->memberId);
            // 备注
            $order->setRemark($request->input('remark', ''));
            $notActiveList = []; // 查找不到数据的列表
            foreach ($products as $item) {
                $pm = $productModels->where('id', $item['product_id'])->first();
                $sm = $skuModels->where('id', $item['sku_id'])->first();
                if (!$pm || !$sm || ($pm && !$pm->cloud_stock_status)) {
                    // 查找不到数据 放到下架列表去
                    $notActiveList[] = ['product_id' => $item['product_id'], 'sku_id' => $item['sku_id']];
                    continue;
                }
                $pro = new ShopProduct($this->memberId, $pm, $sm, $item['num']);
                $order->addProduct($pro);
            }
            $params = ['goBuy' => $request->get('goBuy'), 'originMoneyData' => $request->get('originMoneyData', null)];
            $params['site_id'] = $siteId;
            $res = $order->save($params);
            // 如有查询不到的数据
            if ($notActiveList) {
                // 和其他的数据合并
                if ($res['code'] == 400) {
                    $res['data']['not_active_list'] = array_merge($res['data']['not_active_list'], $notActiveList);
                } else {
                    // 没有其他不能购买的商品 直接返回查找不到的商品列表
                    return makeApiResponse(400, '', ['not_active_list' => $notActiveList, 'not_hasperm_list' => []]);
                }
            }
            return $res;
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}