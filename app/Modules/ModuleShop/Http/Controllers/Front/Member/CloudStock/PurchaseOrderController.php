<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\CloudStock\AdminPurchaseOrder;
use App\Modules\ModuleShop\Libs\CloudStock\CloudStockPurchaseOrder;
use App\Modules\ModuleShop\Libs\CloudStock\FrontPurchaseOrder;
use App\Modules\ModuleShop\Libs\CloudStock\ShopCart;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerAccount;
use App\Modules\ModuleShop\Libs\Dealer\DealerBaseSetting;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Logger\Log;
use YZ\Core\Payment\Payment;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;

/**
 * 云仓我的进货单
 * Class PurchaseOrderController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock
 */
class PurchaseOrderController extends BaseMemberController
{
    /**
     * 在确认下单前，获取订单的商品列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $getSub = $request->input('get_sub', false);
            $order = new FrontPurchaseOrder($getSub);
            $params = $request->all();
            $params['member_id'] = $this->memberId;
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 20);
            $data = $order->getList($params, $page, $pageSize);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取订单详情
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $getSub = $request->input('get_sub', false);
            $order = new FrontPurchaseOrder($getSub);
            $cloudStockOrMemberId = $getSub ? $order->getMemberCloudStockId($this->memberId) : $this->memberId;
            $data = $order->getOrderInfo($cloudStockOrMemberId, $request->get('order_id'), $this->memberId);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 取消进货单
     * @param Request $request
     * @return array
     */
    public function cancel(Request $request)
    {
        try {
            $order = new FrontPurchaseOrder();
            $data = $order->cancel($this->memberId, $request->get('order_id'), $request->get('cancel_reason'));
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取支付配置信息
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getPayConfig(Request $request)
    {
        try {
            $config = Finance::getPayConfig(2);
            $order = CloudStockPurchaseOrderModel::find($request->order_id);
            $config['purchases_money_target'] = $order->payee > 0 ? 1 : 0;
            if ($order->payee ) {
                $config['parent_pay_config'] = DealerAccount::getDealerPayConfig($order->payee);
            }
            return makeApiResponseSuccess('ok', $config);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function payOrder(Request $request)
    {
        try {
            $order = new FrontPurchaseOrder();
            $payType = intval($request->get('pay_type'));
            if ($payType === 1) { //余额支付
                return $order->pay($this->memberId, $request->get('order_id'), $request->get('pay_type'), $request->get('pay_password'));
            } elseif (in_array($payType, [6, 7, 8, 9])) { //线下支付
                // 直接使用数组传多个文件 有些浏览器会有兼容性 （iPhone qq浏览器）
                $vouchers = [$request->file('voucher1'), $request->file('voucher2'), $request->file('voucher3')];
                return $order->pay($this->memberId, $request->get('order_id'), $request->get('pay_type'), $vouchers);
            } elseif (in_array($payType, \YZ\Core\Constants::getOnlinePayType())) { //线上支付
                $order = CloudStockPurchaseOrderModel::query()
                    ->where('member_id', $this->memberId)
                    ->where('id', $request->get('order_id'))
                    ->first();
                // 判断订单是否已经支付过
                if ($order->status != Constants::CloudStockPurchaseOrderStatus_NoPay) {
                    return makeApiResponseFail(trans('shop-front.shop.order_paid'));
                }
                $callback = "\\" . static::class . "@payOrderCallBack";
                $res = Payment::doPay($request->get('order_id'), $this->memberId, $order->total_money, $callback, $payType, 1, 2);
				if(getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxApp) $res['backurl'] = '#/cloudstock/purchase-success?order_id='.$request->get('order_id'); //小程序专用，用来标记在小程序端支付成功后，应该跳转到哪里
                return makeApiResponse(302, 'ok', [
                    'orderid' => $request->get('order_id'),
                    'memberid' => $this->memberId,
                    'result' => $res,
                    'callback' => $callback,
                ]);
            } else {
                return makeApiResponse(400, "支付方式错误");
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function checkPay(Request $request)
    {
        $order = CloudStockPurchaseOrderModel::query()
            ->where('member_id', $this->memberId)
            ->where('id', $request->get('order_id'))
            ->first();
        $status = intval($order->status);
        if (in_array($status, [1, 2, 3])) {
            $isPaid = true;
        }
        return makeApiResponseSuccess('ok', [
            'is_paid' => $isPaid, // 是否已支付
        ]);
    }

    /**
     * 支付订单后的回调
     * @param $info
     * @return array|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function payOrderCallBack($info)
    {
        try {
            $order = CloudStockPurchaseOrderModel::query()
                ->where('site_id', $this->siteId)
                ->where('id', $info['order_id'])
                ->first();

            // 支付宝return过来的 跳到支付结果页
            if (\Illuminate\Support\Facades\Request::isMethod('get') && strpos(\Illuminate\Support\Facades\Request::path(), '/alipayreturn/') !== false) {
                $alipay = Payment::getAlipayInstance();
                $res = $alipay->checkReturn();
                if ($res->success) {
                    if ($order->status === Constants::CloudStockPurchaseOrderStatus_NoPay) {
                        (new FrontPurchaseOrder())->pay($info['member_id'], $info['order_id'], $info['pay_type'], $info);
                    }
                    $url = '/shop/front/#/cloudstock/purchase-success?order_id=' . $info['order_id'];
                    return redirect($url);
                }
            }
            // 通联return过来的 跳到支付结果页
            if (\Illuminate\Support\Facades\Request::isMethod('get') && strpos(\Illuminate\Support\Facades\Request::path(), '/tlpayreturn/') !== false) {
                $tlpay = Payment::getTLPayInstance();
                $res = $tlpay->checkReturn();
                if ($res->success) {
                    if ($order->status === Constants::CloudStockPurchaseOrderStatus_NoPay) {
                        (new FrontPurchaseOrder())->pay($info['member_id'], $info['order_id'], $info['pay_type'], $info);
                    }
                    $url = '/shop/front/#/cloudstock/purchase-success?order_id=' . $info['order_id'];
                    return redirect($url);
                }
            }
            (new FrontPurchaseOrder())->pay($info['member_id'], $info['order_id'], $info['pay_type'], $info);
            return $info;
        } catch (\Exception $ex) {
            Log::writeLog("purchase-pay-callback-error", "error = " . var_export($ex->getMessage(), true));
            return makeApiResponseError($ex);
        }
    }

    /**
     * 订单配仓
     * @param Request $request
     * @return array|bool
     */
    public function orderStockDeliver(Request $request)
    {
        try {
            $order = new FrontPurchaseOrder();
            $orderId = $request->input('order_id', '');
            $deliver = $order->stockDeliver($orderId, $this->memberId);
            if ($deliver === true) {
                return makeApiResponseSuccess('ok');
            } else {
                return $deliver;
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 检测库存是否可以配仓
     * @param Request $request
     * @return array
     */
    public function checkInventory(Request $request)
    {
        try {
            $order = new FrontPurchaseOrder();
            $orderId = $request->input('order_id', '');
            $check = $order->checkInventory($orderId, $this->memberId);
            return makeApiResponseSuccess('ok', $check);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function verify(Request $request)
    {
        try {
            $params = $request->toArray();
            if (!$params['order_id']) {
                return makeApiResponseFail('请输入正确的订单ID');
            }
            $params['member_id'] = $this->memberId;
            $orderId = $params['order_id'];
            $review_status = $params['status']; // 审核状态
            $paymentStatus = $params['status'] == 1 ? Constants::CloudStockPurchaseOrderPaymentStatus_Yes : Constants::CloudStockPurchaseOrderPaymentStatus_Refuse; // 是否确认收到了货款
            $remark = $params['reject_reason'] ?: '';
            $purchaseOrder = (new FrontPurchaseOrder());
            $purchaseOrderModel = $purchaseOrder->getOrderModel($orderId);
            if ($purchaseOrderModel->payee == $params['member_id']) {
                $purchaseOrder->financeReview($orderId, $review_status, $paymentStatus, trim($remark), $this->memberId);
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}