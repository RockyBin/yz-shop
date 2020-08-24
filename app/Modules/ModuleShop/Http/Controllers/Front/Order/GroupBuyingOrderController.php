<?php
/**
 * 拼团订单接口
 * User: liyaohui
 * Date: 2020/4/9
 * Time: 14:04
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Order;


use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Activities\FreeFreight;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingConstants;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingSetting;
use App\Modules\ModuleShop\Libs\Member\MemberAddress;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Order\OrderFront;
use App\Modules\ModuleShop\Libs\Shop\Discount;
use App\Modules\ModuleShop\Libs\Shop\GroupBuyingShopProduct;
use App\Modules\ModuleShop\Libs\Supplier\SupplierBaseSetting;
use Illuminate\Http\Request;
use YZ\Core\Model\MemberModel;

class GroupBuyingOrderController extends BaseMemberController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 创建订单页面
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        try {
            $productList = $request->input('product', '');
            if (empty($productList)) {
                return makeApiResponseFail(trans('shop-front.shop.data_error'));
            }
            $activityId = $request->input('activity_id', 0); // 活动id
            $groupBuyingId = $request->input('group_buying_id', 0); // 具体团的id 为0则是新开团

            $order = $this->initOrder($groupBuyingId, $productList, $activityId);
            // 检测活动状态
            $check = $this->orderBeforeCheck($order);
            if ($check !== true) {
                return $check;
            }
            $setting = $order->getGroupBuyingSetting();
            // 获取订单金额
            $orderProductMoney = $order->calProductMoney();
            // 是否可以使用优惠券
            if ($setting->open_coupon == 1) {
                // 可用的优惠券列表
                $coupons = (new Discount())->getValidCoupons($order);
            } else {
                $coupons = [];
            }

            // 是否可以使用积分
            $pointInfo = $order->calPoint();
            $pointInfo['money'] = $pointInfo['money'] ? moneyCent2Yuan($pointInfo['money']) : 0;

            $headDiscount = 0;
            // 获取非团长的价格 如果不是团长 则不用获取
            $isHead = $groupBuyingId > 0 ? 0 : 1;
            if ($isHead) {
                $headDiscount = moneyCent2Yuan($order->calOtherDiscount());
            }
            // 是否虚拟商品
            $virtualFlag = $order->getVirtualFlag();
            // 运费
            $freightMoney = 0;
            $address = 0;
            if ($virtualFlag !== 1) {
                $address = new MemberAddress($this->memberId);
                // 获取默认地址
                $address = $address->getDefaultAddress();
                if ($address) {
                    $order->setAddressId($address['id']);
                    $freightMoney = $order->calFreight();
                }
            }
            // 订单初始金额
            $totalMoney = $order->calTotalMoney();
            // 所有的优惠金额
            $allDiscount = moneyCent2Yuan($order->getAllDiscount());
            //满额包邮
            $productIds = [];
            $freeFreight = (new FreeFreight())->getWithProducts([$productIds]);
            if($freeFreight) $freeFreight->money = moneyCent2Yuan($freeFreight->money);
            return makeApiResponseSuccess('ok', [
                'productList' => $order->getProductListInfo(),
                'couponList' => $coupons,
                'totalMoney' => moneyCent2Yuan($totalMoney),
                'productMoney' => moneyCent2Yuan($orderProductMoney),
                'point' => $pointInfo,
                'address' => $address,
                'freightMoney' => moneyCent2Yuan($freightMoney),
                'headDiscount' => $headDiscount,
                'virtualFlag' => $virtualFlag,
                'allDiscount' => $allDiscount,
                'supplierConfig' => SupplierBaseSetting::getCurrentSiteSetting(),
                'free_freight' => $freeFreight
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function orderBeforeCheck($order)
    {
        // 检测活动状态
        $checkSetting = $order->checkActivitySetting();
        if ($checkSetting !== true) {
            return $checkSetting;
        }
        $checkGroupBuyingStatus = $order->checkGroupBuyingStatus();
        if ($checkGroupBuyingStatus !== true) {
            return $checkGroupBuyingStatus;
        }
        $inGroupBuying = $order->checkInCurrentGroupBuying();
        if ($inGroupBuying) {
            return makeApiResponse(1001, trans('shop-front.activity.in_current_group_buying'));
        }
        return true;
    }

    /**
     * @param $groupBuyingId
     * @param $productList
     * @param $activityId
     * @return mixed|null
     * @throws \Exception
     */
    public function initOrder($groupBuyingId, $productList, $activityId)
    {
        $order = new OrderFront($this->memberId);
        // 订单类型设为拼团订单
        $order->setOrderType(Constants::OrderType_GroupBuying);
        // 新开的团是团长
        $isHead = $groupBuyingId > 0 ? 0 : 1;
        $order->initOrder(null, [
            'is_head' => $isHead,
            'group_buying_setting_id' => $activityId,
            'group_buying_id' => $groupBuyingId
        ]);
        $order->setOrderProduct($productList);
        return $order->getOrder();
    }

    /**
     * 设置订单的地址 优惠券 积分
     * @param Request $request
     * @return array
     */
    public function setOrder(Request $request)
    {
        try {
            $usePoint = $request->input('usePoint', 0);
            $couponId = $request->input('couponId', 0);
            $addressId = $request->input('addressId', 0);
            $productList = $request->input('product', '');

            $activityId = $request->input('activity_id', 0); // 活动id
            $groupBuyingId = $request->input('group_buying_id', 0); // 具体团的id 为0则是新开团

            $order = $this->initOrder($groupBuyingId, $productList, $activityId);
            // 检测活动状态
            $check = $this->orderBeforeCheck($order);
            if ($check !== true) {
                return $check;
            }
            $order->setCouponID($couponId);
            $productMoney = $order->calProductMoney();
            $couponMoney = moneyCent2Yuan($order->calCoupon());
            // 使用积分
            if ($usePoint != 0) {
                $pointInfo = $order->calPoint();
            } else {
                $pointInfo = $order->calPoint(false, true);
            }
            $headDiscount = $order->calOtherDiscount();
            $pointInfo['money'] = $pointInfo['money'] ? moneyCent2Yuan($pointInfo['money']) : 0;
            // 运费
            $freightMoney = 0;
            if ($addressId) {
                $order->setAddressId($addressId);
                $freightMoney = $order->calFreight();
            }
            // 重新选择优惠券后 要重新获取订单金额 和可以使用的积分
            $totalMoney = $order->calTotalMoney();
            $money = $totalMoney - $freightMoney;
            $allDiscount = $order->getAllDiscount();
            return makeApiResponseSuccess('ok', [
                'money' => moneyCent2Yuan($money),
                'total_money' => moneyCent2Yuan($totalMoney),
                'point' => $pointInfo,
                'couponMoney' => $couponMoney,
                'freightMoney' => moneyCent2Yuan($freightMoney),
                'headDiscount' => moneyCent2Yuan($headDiscount),
                'productMoney' => moneyCent2Yuan($productMoney),
                'allDiscount' => moneyCent2Yuan($allDiscount)
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 创建订单
     * @param Request $request
     * @return array|mixed
     */
    public function createOrder(Request $request)
    {
        try {
            $productList = $request->input('product', '');
            if (empty($productList)) {
                return makeApiResponseFail(trans('shop-front.shop.data_error'));
            }
            $usePoint = $request->input('usePoint', 0);
            $couponId = $request->input('couponId', 0);
            $addressId = $request->input('addressId', 0);
            $remark = $request->input('remark', '');
            $originMoneyData = $request->input('originMoneyData', []);
            $goBuy = $request->input('goBuy', 0);
            $activityId = $request->input('activity_id', 0); // 活动id
            $groupBuyingId = $request->input('group_buying_id', 0); // 具体团的id 为0则是新开团

            $order = $this->initOrder($groupBuyingId, $productList, $activityId);
            // 检测活动状态
            $check = $this->orderBeforeCheck($order);
            if ($check !== true) {
                return $check;
            }
            if (!$addressId && $order->getVirtualFlag() !== 1) {
                return makeApiResponseFail(trans('shop-front.shop.must_choose_the_shipping_address'));
            }
            $order->calProductMoney();
            $order->calOtherDiscount();
            $order->setCouponID($couponId);
            $order->setAddressId($addressId);
            $order->setRemark($remark);
            $couponMoney = moneyCent2Yuan($order->calCoupon());
            // 使用积分
            if ($usePoint != 0) {
                $pointInfo = $order->calPoint();
            } else {
                $pointInfo = $order->calPoint(false);
            }
            $freightMoney = moneyCent2Yuan($order->calFreight());
            $saveOrder = $order->save([
                'originMoneyData' => $originMoneyData,
                'goBuy' => $goBuy,
                'order_data' => [
                    'activity_id' => $groupBuyingId,
                    'type_status' => Constants::OrderType_GroupBuyingStatus_No
                ],
                'product_data' => [
                    'activity_sku_id' => $productList[0]['sku_id'],
                    'type' => Constants::OrderType_GroupBuying
                ]
            ]);
            if ($saveOrder['code'] != 200) {
                return $saveOrder;
            } else {
                $orderInfo = $saveOrder['data'];
                $orderMoney = intval($orderInfo['money']);
                $orderInfo['couponMoney'] = $couponMoney;
                $orderInfo['freightMoney'] = $freightMoney;
                $orderInfo['pointInfo'] = $pointInfo;
                $orderInfo['money'] = moneyCent2Yuan($orderMoney);
                $orderInfo['direct_pay'] = $orderMoney == 0 ? true : false; // 0元订单直接余额支付成功

                return makeApiResponseSuccess('ok', $orderInfo);
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function orderPayResult(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            if (!$orderId) {
                return makeApiResponseFail(trans('shop-front.shop.data_error'));
            }
            $groupBuying = GroupBuyingModel::query()
                ->from('tbl_group_buying as gb')
                ->join('tbl_order as order', 'order.activity_id', 'gb.id')
                ->where('gb.site_id', getCurrentSiteId())
                ->where('order.id', $orderId)
                ->select(['gb.*'])
                ->first();
            if (!$groupBuying) {
                return makeApiResponseFail(trans('shop-front.activity.cant_found_activity'));
            }
            $groupBuying = $groupBuying->toArray();
            unset($groupBuying['snapshot']);
            // 获取头像
            $memberIds = json_decode($groupBuying['member_ids'], true);
            $groupBuying['member_ids'] = $memberIds;
            $memberIds = array_slice($memberIds, 0, 2); // 最多返回两个头像
            $headurl = MemberModel::query()
                ->where('site_id', getCurrentSiteId())
                ->whereIn('id', $memberIds)
                ->orderByRaw("find_in_set(id, '" . implode(',', $memberIds) . "')")
                ->pluck('headurl');
            $groupBuying['headurl'] = $headurl;
            // 获取商品详情
            $product = OrderItemModel::query()
                ->where('site_id', $groupBuying['site_id'])
                ->where('order_id', $orderId)
                ->select(['name', 'image', 'price'])
                ->first();
            $product['price'] = moneyCent2Yuan($product['price']);
            $groupBuying['product'] = $product;
            return makeApiResponseSuccess('ok', $groupBuying);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 返回拼团商品的限购量和最低购买量
     * @param Request $request
     */
    public function getGroupBuyingProductLimitInfo(Request $request){
        try {
            $skuId = $request->get('sku_id');
            $num = $request->get('num');
            $product = new GroupBuyingShopProduct($skuId, $num);
            $limit = $product->getBuyLimit();
            $min = $product->getMinBuyNum();
            return makeApiResponseSuccess('ok',['min' => $min,'limit' => $limit]);
        }catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}