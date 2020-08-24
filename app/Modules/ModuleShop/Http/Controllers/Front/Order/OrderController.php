<?php
/**
 * 前台订单
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Order;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Activities\FreeFreight;
use App\Modules\ModuleShop\Libs\Constants as shopConstants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberAddress;
use App\Modules\ModuleShop\Libs\Model\DistributorModel;
use App\Modules\ModuleShop\Libs\Model\OrderConfigModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Shop\NormalShopProduct;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use App\Modules\ModuleShop\Libs\Order\Order;
use App\Modules\ModuleShop\Libs\Order\OrderFront;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use App\Modules\ModuleShop\Libs\Shop\Discount;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use App\Modules\ModuleShop\Libs\Shop\ShoppingCart;
use App\Modules\ModuleShop\Libs\Supplier\SupplierBaseSetting;
use App\Modules\ModuleShop\Libs\Wx\WxSubscribeSetting;
use Illuminate\Http\Request;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Member\Auth;
use YZ\Core\Payment\Payment;
use YZ\Core\Site\Site;

class OrderController extends BaseMemberController
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
            $memberId = $this->memberId;

            $order = new OrderFront($memberId);
            $order->initOrder();
            $order->setOrderProduct($productList);
            $order = $order->getOrder();
            // 可用的优惠券列表
            $coupons = (new Discount())->getValidCoupons($order);
            $productMoney = $order->calProductMoney();
            // 可用积分
            $pointInfo = $order->calPoint();
            $pointInfo['money'] = $pointInfo['money'] ? moneyCent2Yuan($pointInfo['money']) : 0;
            // 运费
            $freightMoney = 0;
            $address = 0;
            // 是否虚拟商品
            $virtualFlag = $order->getVirtualFlag();
            if ($virtualFlag !== 1) {
                $address = new MemberAddress($this->memberId);
                $address = $address->getDefaultAddress();
                if ($address) {
                    $order->setAddressId($address['id']);
                    $freightMoney = $order->calFreight();
                }
            }
            // 订单初始金额
            $totalMoney = $order->calTotalMoney();
            $allDiscount = $order->getAllDiscount();
            //满额包邮
            $productIds = [];
            $freeFreight = (new FreeFreight())->getWithProducts([$productIds]);
            if($freeFreight) $freeFreight->money = moneyCent2Yuan($freeFreight->money);

            return makeApiResponseSuccess('ok', [
                'productList' => $order->getProductListInfo(),
                'couponList' => $coupons,
                'totalMoney' => moneyCent2Yuan($totalMoney),
                'productMoney' => moneyCent2Yuan($productMoney),
                'point' => $pointInfo,
                'address' => $address,
                'freightMoney' => moneyCent2Yuan($freightMoney),
                'virtualFlag' => $virtualFlag,
                'allDiscount' => moneyCent2Yuan($allDiscount),
                'supplierConfig'=>SupplierBaseSetting::getCurrentSiteSetting(),
                'free_freight' => $freeFreight
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
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

            $memberId = $this->memberId;
            $order = new OrderFront($memberId);
            $order->initOrder();
            $order->setOrderProduct($productList);
            $order = $order->getOrder();
            $order->setCouponID($couponId);
            $productMoney = $order->calProductMoney();
            $couponMoney = moneyCent2Yuan($order->calCoupon());
            // 使用积分
            if ($usePoint != 0) {
                $pointInfo = $order->calPoint();
            } else {
                $pointInfo = $order->calPoint(false, true);
            }

            // 运费
            $freightMoney = 0;
            if ($addressId) {
                $order->setAddressId($addressId);
                $freightMoney = $order->calFreight();
            }

            $pointInfo['money'] = $pointInfo['money'] ? moneyCent2Yuan($pointInfo['money']) : 0;
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
                'allDiscount' => moneyCent2Yuan($allDiscount),
                'productMoney' => moneyCent2Yuan($productMoney),
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
            // 产品在购物车中的id数组
            $productCartIds = $request->input('cart_ids', '');
            if (empty($productList)) {
                return makeApiResponseFail(trans('shop-front.shop.data_error'));
            }
            $memberId = $this->memberId;
            $usePoint = $request->input('usePoint', 0);
            $couponId = $request->input('couponId', 0);
            $addressId = $request->input('addressId', 0);
            $remark = $request->input('remark', '');
            $originMoneyData = $request->input('originMoneyData', []);
            $goBuy = $request->input('goBuy', 0);
            $order = new OrderFront($memberId);            
            $order->initOrder();
            $order->setOrderProduct($productList);
            $order = $order->getOrder();
            $order->calProductMoney();
            $order->setCouponID($couponId);
            $order->setAddressId($addressId);
            $order->setRemark($remark);
            $couponMoney = moneyCent2Yuan($order->calCoupon());
            if (!$addressId && $order->getVirtualFlag() !== 1) {
                return makeApiResponseFail(trans('shop-front.shop.must_choose_the_shipping_address'));
            }
            // 使用积分
            if ($usePoint != 0) {
                $pointInfo = $order->calPoint();
            } else {
                $pointInfo = $order->calPoint(false);
            }
            $freightMoney = moneyCent2Yuan($order->calFreight());
            $saveOrder = $order->save([
                'originMoneyData' => $originMoneyData,
                'goBuy' => $goBuy
            ]);
            if ($saveOrder['code'] != 200) {
                return $saveOrder;
            } else {
                if (!empty($productCartIds)) {
                    // 生成订单成功后 要把购物车中的的对应产品删掉
                    $cart = new ShoppingCart($this->memberId);
                    $cart->removeProduct($productCartIds);
                }

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

    /**
     * 返回单个商品的限购量和最低购买量
     * @param Request $request
     */
    public function getProductLimitInfo(Request $request){
        try {
            $productId = $request->get('product_id');
            $skuId = $request->get('sku_id');
            $num = $request->get('num');
            $product = new NormalShopProduct($productId, $skuId, $num);
            $limit = $product->getBuyLimit();
            $min = $product->getMinBuyNum();
            return makeApiResponseSuccess('ok',['min' => $min,'limit' => $limit]);
        }catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 订单支付
     * @param Request $request
     * @return array
     */
    public function payOrder(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            $payType = $request->input('pay_type', Constants::PayType_Balance);
            $password = $request->input('pay_password', '');

            $order = ShopOrderFactory::createOrderByOrderId($orderId);
            $money = $order->getTotalMoney();

            // 如果是余额支付 要验证支付密码
            if ($payType == Constants::PayType_Balance) {
                $member = new Member($this->memberId);
                // 0元订单不检查支付密码
                if ($money != 0) {
                    if ($member->payPasswordIsNull()) {
                        return makeApiResponse(402, trans('shop-front.shop.pay_password_error'));
                    }
                    if (!$member->payPasswordCheck($password)) {
                        return makeApiResponse(406, trans('shop-front.shop.pay_password_error'));
                    }
                }
            }

            $memberId = $this->memberId;
            // TODO 判断订单是否已经支付过
            $orderModel = $order->getOrderModel();
            if ($orderModel->status != shopConstants::OrderStatus_NoPay) {
                switch (intval($orderModel->status)) {
                    case shopConstants::OrderStatus_Cancel:
                        return makeApiResponse(405, trans('shop-front.shop.order_canceled'));
                    case shopConstants::OrderStatus_Deleted:
                        return makeApiResponse(407, trans('shop-front.shop.order_deleted'));
                    default:
                        return makeApiResponse(405, trans('shop-front.shop.order_paid'));
                }
            }

            $callback = "\\" . static::class . "@payOrderCallBack";
            $res = Payment::doPay($orderId, $memberId, $money, $callback, $payType, 1);
			if(getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxApp) $res['backurl'] = '#/product/payment-success?order_id='.$orderId.'&is_callback=1'; //小程序专用，用来标记在小程序端支付成功后，应该跳转到哪里
            return makeApiResponseSuccess('ok', [
                'orderid' => $orderId,
                'memberid' => $memberId,
                'result' => $res,
                'callback' => $callback,
                'order_type' => $orderModel->type,
                'subscribe' => (new WxSubscribeSetting())->getSubscribeInfo()
            ]);
        } catch (\Exception $e) {
            if ($e->getCode() > 0) {
                return makeApiResponse($e->getCode(), $e->getMessage());
            }
            return makeApiResponseError($e);
        }
    }

    /**
     * 支付订单后的回调
     * @param $info
     * @return array|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function payOrderCallBack($info)
    {
        try {
            $orderIds = [];
            $orderModel = OrderModel::find($info['order_id']);
            //供应商已经拆分的订单
            if(!$orderModel) {
                $orderList = OrderModel::query()->where('original_id',$info['order_id'])->get();
            }else{
                $orderList = [$orderModel];
            }
            // 支付宝return过来的 跳到支付结果页
            if (\Illuminate\Support\Facades\Request::isMethod('get') && strpos(\Illuminate\Support\Facades\Request::path(), '/alipayreturn/') !== false) {
                $alipay = Payment::getAlipayInstance();
                $res = $alipay->checkReturn();
                if ($res->success) {
                    foreach ($orderList as $orderModel) {
                        if ($orderModel->status === \App\Modules\ModuleShop\Libs\Constants::OrderStatus_NoPay) {
                            $order = ShopOrderFactory::createOrderByOrderId($orderModel);
                            $orderIds = array_merge($orderIds,$order->pay($info));
                        } else {
                            $orderIds[] = $orderModel->id;
                        }
                    }
                    $url = '/shop/front/#/product/payment-success?order_id=' . implode(',',$orderIds) . '&is_callback=1';
                    return redirect($url);
                }
            }
			//通联return过来的 跳到支付结果页
			if (\Illuminate\Support\Facades\Request::isMethod('get') && strpos(\Illuminate\Support\Facades\Request::path(), '/tlpayreturn/') !== false) {
                $tlpay = Payment::getTLPayInstance();
                $res = $tlpay->checkReturn();
                if ($res->success) {
                    foreach ($orderList as $orderModel) {
                        if ($orderModel->status === \App\Modules\ModuleShop\Libs\Constants::OrderStatus_NoPay) {
                            $order = ShopOrderFactory::createOrderByOrderId($orderModel);
                            $orderIds = array_merge($orderIds,$order->pay($info));
                        } else {
                            $orderIds[] = $orderModel->id;
                        }
                    }
                    $url = '/shop/front/#/product/payment-success?order_id=' . implode(',',$orderIds) . '&is_callback=1';
                    return redirect($url);
                }
            }
            foreach ($orderList as $orderModel){
                $order = ShopOrderFactory::createOrderByOrderId($orderModel);
                $orderIds = array_merge($orderIds,$order->pay($info));
            }
            $info['order_id'] = implode(',',$orderIds);
            return $info;
        } catch (\Exception $ex) {
            Log::writeLog("pay-callback-error", "error = " . var_export($ex->getMessage(), true));
            return makeApiResponseError($ex);
        }

    }

    /**
     * 临时的订单结果页
     * @param Request $request
     */
    public function orderPayResult(Request $request)
    {
        $orderId = $request->input('order_id', '');
        $status = $request->input('status', 0);
        echo "订单 {$orderId} 状态为 ：$status";
    }

    /**
     * 订单详情
     * @param Request $request
     * @return array
     */
    public function orderInfo(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            if (!$orderId) {
                return makeApiResponseFail(trans('shop-front.shop.data_error'));
            } else {
                $order = new OrderFront($this->memberId);
                $order->initOrder($orderId, ['initProduct' => false]);
                $orderInfo = $order->orderInfo();
                // 订单关闭原因
                $orderInfo['orderCancelReason'] = OrderFront::getOrderCancelReasonList();
                $orderInfo['aftersale_isopen']=(new OrderConfig())->getInfo()['aftersale_isopen'];
                return makeApiResponseSuccess('ok', $orderInfo);
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 关闭未付款或未发货订单
     * @param Request $request
     * @return array
     */
    public function cancelOrder(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            $msg = $request->input('msg', 1);
            if (!$orderId) {
                return makeApiResponseFail(trans('shop-front.shop.data_error'));
            } else {
                $order = new OrderFront($this->memberId);
                $order->initOrder($orderId);
                $order->cancelOrder($msg);
                return makeApiResponseSuccess('ok');
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 会员订单列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            // 获取数据
            $orderFront = new OrderFront($this->memberId);
            $status = $request->status;
            $param = [];
            if ($status == shopConstants::OrderStatus_OrderReceive) {
                $status = [shopConstants::OrderStatus_OrderReceive, shopConstants::OrderStatus_OrderSuccess, shopConstants::OrderStatus_OrderFinished];
                $param['comment_status'] = 0;
            }
            $param['status'] = $status;
            $data = $orderFront->orderList($param, intval($request->page), intval($request->page_size), $this->siteId);
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取支付时间
     * @return array
     */
    public function getPayOrderTime()
    {
        try {
            $orderConfig = OrderConfigModel::find(Site::getCurrentSite()->getSiteId());
            $payDay = $orderConfig->nopay_close_day ?: 0;
            $payHour = $orderConfig->nopay_close_hour ?: 0;
            $payMin = $orderConfig->nopay_close_minute ?: 0;
            return makeApiResponseSuccess('ok', ['day' => $payDay, 'hour' => $payHour, 'min' => $payMin]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 订单确认收货
     * @param Request $request
     * @return array
     */
    public function confirmReceipt(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $orderFront = new OrderFront($this->memberId);
            $confirmReceipt = $orderFront->orderConfirmReceipt($orderId);
            return $confirmReceipt;
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 订单支付简易信息
     * @param Request $request
     * @return array
     */
    public function orderPayInfo(Request $request)
    {
        try {
            $distributorStatus = shopConstants::DistributorStatus_Null; // 分销商审核状态
            $isPaid = false;
            $isCommissionProduct = false;
            $orderId = $request->input('order_id', '');
            if ($orderId) {
                //当要拆单时，为简化前台的流程，前台传过来的原来未拆分前的订单号，因为拆分的订单会在 original_id 字段中记录原订单号
                $orderList = OrderModel::query()->where('id',$orderId)->orWhere('original_id',$orderId)->get();
                $order = $orderList->first();
                $status = intval($order->status);
                if (in_array($status, BaseShopOrder::getPaidStatusList())) {
                    $isPaid = true;
                }

                $items = OrderItemModel::query()->whereIn('order_id',$orderList->pluck('id')->values())->first();
                foreach ($items as $item) {
                    if ($item->is_commission_product) {
                        $isCommissionProduct = true;
                        break;
                    }
                }
                $distributorModel = DistributorModel::query()
                    ->where('site_id', Site::getCurrentSite()->getSiteId())
                    ->where('member_id', $order->member_id)
                    ->first();
                if ($distributorModel) {
                    $distributorStatus = intval($distributorModel->status);
                }
            }
            return makeApiResponseSuccess('ok', [
                'order_id' => $orderList->pluck('id')->values(),
                'is_paid' => $isPaid, // 是否已支付
                'is_commission_product' => $isCommissionProduct, // 是否申请分销产品
                'distributor_status' => $distributorStatus, // 分销商审核状态
            ]);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取未评价的明细数据
     * @param Request $request
     * @return array
     */
    public function getNoCommentItemList(Request $request)
    {
        try {
            $orderId = $request->input('order_id', '');
            if (!$orderId) {
                return makeApiResponseFail(trans('shop-front.shop.data_error'));
            } else {
                $order = new OrderFront($this->memberId);
                $order->initOrder($orderId);
                $itemList = $order->getNoCommentItemList();
                return makeApiResponseSuccess('ok', $itemList);
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function testPay(Request $request)
    {

        $callback = $request->input('callback');
        $orderid = $request->input('orderid');
        $amount = $request->input('amount');
        $memberid = $this->memberId;
        return view('Member/Payment/choose', ['callback' => $callback, 'orderid' => $orderid, 'amount' => $amount, 'memberid' => $memberid]);
    }
}