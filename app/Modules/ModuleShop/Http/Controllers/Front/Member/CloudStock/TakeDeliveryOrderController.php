<?php
/**
 * 提货订单接口
 * User: liyaohui
 * Date: 2019/9/5
 * Time: 14:43
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\CloudStock;


use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\CloudStock\FrontTakeDeliveryOrder;
use App\Modules\ModuleShop\Libs\Model\CloudStockTakeDeliveryOrderModel;
use Illuminate\Http\Request;
use YZ\Core\Logger\Log;
use YZ\Core\Payment\Payment;
use YZ\Core\Constants;
use App\Modules\ModuleShop\Libs\Constants as LibConstants;
use YZ\Core\Site\Site;

class TakeDeliveryOrderController extends BaseMemberController
{
    /**
     * 获取创建提货订单所需要的数据
     * @param Request $request
     * @return array
     */
    public function getCreateOrderData(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            $addressId = $request->input('address_id');
            $data = (new FrontTakeDeliveryOrder($this->memberId))->getCreateOrderData($ids, $addressId);
            if ($data['code'] == 200) {
                return makeApiResponseSuccess('ok', $data['data']);
            } else {
                return makeApiResponseFail('此区域不在配送区域', $data['data']);
            }

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


    /**
     * 创建订单
     * @param Request $request
     * @return array|bool
     */
    public function createOrder(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            $addressId = $request->input('address_id');
            if(!$addressId)   return makeApiResponseFail('address_id错误');
            $data = $request->all(['remark', 'update_inventory']);
            $order = new FrontTakeDeliveryOrder($this->memberId);
            $create = $order->createOrder($ids, $addressId, $data);
            return $create;
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 确认收货
     * @param Request $request
     * @return array
     */
    public function orderReceipt(Request $request)
    {
        try {
            $order_id = $request->input('order_id', '');
            $order = new FrontTakeDeliveryOrder($this->memberId);
            $receipt = $order->receipt($order_id);
            if ($receipt) {
                return makeApiResponseSuccess('ok');
            } else {
                return makeApiResponse(400, trans('shop-front.shop.data_error'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取订单列表
     * @param Request $request
     * @return array
     */
    public function getOrderList(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 15);
            $params = $request->all(['status']);
            $order = new FrontTakeDeliveryOrder($this->memberId);
            $list = $order->getList($params, $page, $pageSize);
            return makeApiResponseSuccess('ok', $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取订单详情
     * @param Request $request
     * @return array
     */
    public function getOrderInfo(Request $request)
    {
        try {
            $order_id = $request->input('order_id');
            $order = new FrontTakeDeliveryOrder($this->memberId);
            return makeApiResponseSuccess('ok', ['order_info' => $order->getOrderInfo($order_id), 'orderCancelReason' => self::getOrderCancelReasonList()]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取订单关闭文案列表
     * @return array
     */
    public static function getOrderCancelReasonList()
    {
        return [
            LibConstants::CloudStock_OrderCancelReason_NotTake => '暂时不提货了',
            LibConstants::CloudStock_OrderCancelReason_AdressError => '地址填错了',
            LibConstants::CloudStock_OrderCancelReason_SellerNotEnought => '卖家缺货',
            LibConstants::CloudStock_OrderCancelReason_Other => '其他原因',
        ];
    }

    /**
     * 取消进货单
     * @param Request $request
     * @return array
     */
    public function cancel(Request $request)
    {
        try {
            $order = new FrontTakeDeliveryOrder($this->memberId);
            $data = $order->cancel($this->memberId, $request->get('order_id'), $request->get('cancel_reason'));
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function payOrder(Request $request)
    {
        try {
            $order = new FrontTakeDeliveryOrder($this->memberId);
            $payType = intval($request->get('pay_type'));
            if ($payType === 1) { //余额支付
                return $order->pay($this->memberId, $request->get('order_id'), $request->get('pay_type'), $request->get('pay_password'));
            } elseif (in_array($payType, \YZ\Core\Constants::getOnlinePayType())) { //线上支付
                $order = CloudStockTakeDeliveryOrderModel::query()
                    ->where('member_id', $this->memberId)
                    ->where('id', $request->get('order_id'))
                    ->first();
                // 判断订单是否已经支付过
                if ($order->status != LibConstants::CloudStockTakeDeliveryOrderStatus_Nopay) {
                    return makeApiResponseFail(trans('shop-front.shop.order_paid'));
                }
                $callback = "\\" . static::class . "@payOrderCallBack";
                $res = Payment::doPay($request->get('order_id'), $this->memberId, $order->freight, $callback, $payType, 1, Constants::FinanceOrderType_CloudStock_TakeDelivery);
				if(getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxApp) $res['backurl'] = '#/takedelivery/takedelivery-success?order_id='.$request->get('order_id'); //小程序专用，用来标记在小程序端支付成功后，应该跳转到哪里
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

    /**
     * 支付订单后的回调
     * @param $info
     * @return array|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function payOrderCallBack($info)
    {
        try {
            $order = CloudStockTakeDeliveryOrderModel::query()
                ->where('site_id', Site::getCurrentSite()->getSiteId())
                ->where('id', $info['order_id'])
                ->first();
            // 支付宝return过来的 跳到支付结果页
            if (\Illuminate\Support\Facades\Request::isMethod('get') && strpos(\Illuminate\Support\Facades\Request::path(), '/alipayreturn/') !== false) {
                $alipay = Payment::getAlipayInstance();
                $res = $alipay->checkReturn();
                if ($res->success) {
                    if ($order->status === LibConstants::CloudStockTakeDeliveryOrderStatus_Nopay) {
                        (new FrontTakeDeliveryOrder($this->memberId))->pay($info['member_id'], $info['order_id'], $info['pay_type'], $info);
                    }
                    $url = '/shop/front/#/takedelivery/takedelivery-success?order_id=' . $info['order_id'];
                    return redirect($url);
                }
            }
            (new FrontTakeDeliveryOrder($this->memberId))->pay($info['member_id'], $info['order_id'], $info['pay_type'], $info);
            return $info;
        } catch (\Exception $ex) {
            Log::writeLog("purchase-pay-callback-error", "error = " . var_export($ex->getMessage(), true));
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取线下支付配置信息
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getPayConfig()
    {
        try {
            $config = FrontTakeDeliveryOrder::getPayConfig();
            return makeApiResponseSuccess('ok', $config);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    public function checkPay(Request $request)
    {
        $order = CloudStockTakeDeliveryOrderModel::query()
            ->where('member_id', $this->memberId)
            ->where('id', $request->get('order_id'))
            ->first();
        $status = intval($order->status);
        if (in_array($status, [0, 1, 2])) {
            $isPaid = true;
        }
        return makeApiResponseSuccess('ok', [
            'is_paid' => $isPaid, // 是否已支付
        ]);
    }
}