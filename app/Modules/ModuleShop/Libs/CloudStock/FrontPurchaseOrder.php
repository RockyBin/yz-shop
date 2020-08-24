<?php
/**
 * 代理进货单业务逻辑
 * User: liyaohui
 * Date: 2019/8/23
 * Time: 14:40
 */

namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Jobs\UpgradeDealerLevelJob;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerAccount;
use App\Modules\ModuleShop\Libs\Dealer\DealerBaseSetting;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Message\DealerMessageNotice;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Model\CloudStockModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderItemModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use App\Modules\ModuleShop\Libs\VerifyLog\VerifyLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Payment\Payment;
use YZ\Core\Site\Site;
use Illuminate\Foundation\Bus\DispatchesJobs;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Finance\FinanceHelper;

class FrontPurchaseOrder
{
    use DispatchesJobs;
    use PurchaseOrderTrait;
    protected $_siteId = 0;
    protected $_getSub = false; // 是否是获取下级的订单信息

    public function __construct($getSub = false)
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
        $this->_getSub = $getSub;
    }

    /**
     * 获取会员/下级的订单列表(前台会员中心用)
     * @param array $params 查询参数
     * @param int $page 当前页
     * @param int $pageSize 每页多少个
     * @return array
     */
    public function getList($params, int $page = 1, int $pageSize = 20)
    {
        $query = CloudStockPurchaseOrderModel::query()
            ->from('tbl_cloudstock_purchase_order as order')
            ->where('order.site_id', $this->_siteId);
        $fields = [
            'order.id',
            'order.total_money',
            'order.created_at',
            'order.pay_at',
            'order.payment_status',
            'order.payee',
            'order.status',
            'order.cloudstock_id'
        ];
        // 下级进货单需要获取会员信息
        if ($this->_getSub) {
            $query->leftJoin('tbl_member as m', 'm.id', 'order.member_id');
            $fields = array_merge($fields, ['m.headurl', 'm.nickname']);
        }
        // 状态
        if (isset($params['status']) && is_numeric($params['status'])) {
            // 如果状态为待付款 需要把未审核的也查询出来
            if ($params['status'] == 0) {
                $query->whereIn('order.status', [
                    Constants::CloudStockPurchaseOrderStatus_NoPay,
                    Constants::CloudStockPurchaseOrderStatus_Pay
                ]);
            } else {
                $query->where('order.status', $params['status']);
            }
        }
        // 会员ID
        if (isset($params['member_id']) && is_numeric($params['member_id'])) {
            if ($this->_getSub) {
                // 获取当前会员的cloudstock_id
                $cloudStockId = $this->getMemberCloudStockId($params['member_id']);
                $query->where('order.cloudstock_id', $cloudStockId);
            } else {
                $query->where('order.member_id', $params['member_id']);
            }
        }
        $count = $query->count();
        $lastPage = ceil($count / $pageSize);
        $page = $page < 1 ? 1 : $page;
        $page = $page > $lastPage ? $lastPage : $page;
        $list = $query->orderByDesc('order.created_at')
            ->forPage($page, $pageSize)
            ->select($fields)
            ->get();
        // 列出订单商品
        $orderIds = $list->pluck('id')->values()->all();
        if ($orderIds) {
            $itemList = CloudStockPurchaseOrderItemModel::query()->whereIn('order_id', $orderIds)->get();
            foreach ($list as &$order) {
                $subList = $itemList->where('order_id', $order->id)->values();
                foreach ($subList as &$item) {
                    $item->price = moneyCent2Yuan($item->price);
                    $item->money = moneyCent2Yuan($item->money);
                    $item->sku_names = $item->sku_names ? json_decode($item->sku_names, true) : [];
                }
                unset($item);
                $order->items = $subList;
            }
            unset($order);
        }
        return [
            'total' => $count,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lastPage,
            'list' => $this->formatOrderList($list)
        ];
    }

    /**
     * 获取订单详情
     * @param int $memberOrCloudStockId 会员ID
     * @param string $orderId 订单ID
     * @param int $currMemberId 当前登录的会员id
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getOrderInfo($memberOrCloudStockId, $orderId, $currMemberId = 0)
    {
        $order = CloudStockPurchaseOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('id', $orderId);
        // 获取的下级订单
        if ($this->_getSub) {
            $order->where('cloudstock_id', $memberOrCloudStockId);
        } else {
            $order->where('member_id', $memberOrCloudStockId);
        }
        $order = $order->first();
        if ($order) {
            // 如果是下级进货单 需要获取该会员的信息
            if ($this->_getSub) {
                $member_info = MemberModel::query()->where('site_id', $this->_siteId)
                    ->where('id', $order->member_id)
                    ->select(['nickname', 'mobile'])
                    ->first();
                if ($member_info) {
                    $order->nickname = $member_info->nickname;
                    $order->member_mobile = $member_info->mobile;
                } else {
                    $order->nickname = '';
                    $order->member_mobile = '';
                }
                // 检测是否可以配仓
                $order->check_inventory = $this->checkInventory($order->id, $currMemberId)['all_enough'];
                $order->check_inventory = !!$order->check_inventory;
            }
            $subList = CloudStockPurchaseOrderItemModel::query()
                ->from('tbl_cloudstock_purchase_order_item as cpoi')
                ->leftJoin('tbl_cloudstock as c', 'cpoi.cloudstock_id', 'c.id')
                ->leftJoin('tbl_cloudstock_sku as cs', function ($join) {
                    $join->on('cs.sku_id', '=', 'cpoi.sku_id')->whereRaw('c.member_id=cs.member_id');
                })
                ->where(['cpoi.order_id' => $order->id, 'cpoi.site_id' => $this->_siteId])
                ->selectRaw('cpoi.*,(CAST(if(cs.inventory,cs.inventory,0) AS signed) - CAST(cpoi.num AS signed)) AS not_enough_num')
                ->get();
            foreach ($subList as &$item) {
                $item->price = moneyCent2Yuan($item->price);
                $item->money = moneyCent2Yuan($item->money);
                $item->sku_names = $item->sku_names ? json_decode($item->sku_names, true) : [];
            }
            unset($item);
            $order->status_text = self::getOrderStatusText(intval($order->status));
            $order->payment_status_text = self::getOrderPaymentStatusText($order->payment_status);
            $order->total_money = moneyCent2Yuan($order->total_money);
            $order->other_status_text = self::getOrderOtherStatusText(
                intval($order->status),
                intval($order->payment_status),
                intval($order->payee),
                $this->_getSub
            );
            $order->items = $subList;
            if ($order->payee === 0) {
                $order->payee_name = '公司';
            } else {
                $payfeeMember = (new Member($order->payee))->getModel();
                $order->payee_name = $payfeeMember->nickname;
            }
            // 显示配仓人名字
            if ($order->cloudstock_id == 0) {
                $order->cloudstock_name = '公司';
            } else {
                $cloudstok = CloudStockModel::query()
                    ->where('tbl_cloudstock.site_id', $this->_siteId)
                    ->where('tbl_cloudstock.id', $order->cloudstock_id)
                    ->leftJoin('tbl_member', 'tbl_cloudstock.member_id', 'tbl_member.id')
                    ->select(['nickname'])
                    ->first();
                $order->cloudstock_name = $cloudstok->nickname;
            }

            // 是否显示审核按钮
            $order->is_show_review = $order->status == Constants::CloudStockPurchaseOrderStatus_Pay ? ($order->payee == $currMemberId ? true : false) : false;
            if ($order->payment_voucher) $order->payment_voucher = explode(',', $order->payment_voucher);
            $order->pay_type_text = CoreConstants::getPayTypeTextTwo($order->pay_type);
            return $order;
        } else {
            throw new \Exception('订单不存在');
        }
    }

    /**
     * 获取cloud stock id
     * @param int $memberId
     * @return int|mixed
     */
    public function getMemberCloudStockId($memberId = 0)
    {
        // 获取当前会员的cloudstock_id
        $cloudStockId = CloudStockModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $memberId)
            ->value('id');
        $cloudStockId = $cloudStockId ?: -1; // 找不到id的返回-1
        return $cloudStockId;
    }

    /**
     * 取消订单
     * @param int $memberId 会员ID
     * @param string $orderId 订单ID
     * @param string $reason 取消原因
     * @throws \Exception
     */
    public function cancel($memberId, $orderId, $reason)
    {
        $order = CloudStockPurchaseOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $memberId)
            ->where('id', $orderId)
            ->first();
        if ($order) {
            $order->status = Constants::CloudStockPurchaseOrderStatus_Cancel;
            $order->cancel_reason = $reason;
            // 更新log
            // 拒绝后的取消
            if ($order->payment_status == Constants::CloudStockPurchaseOrderPaymentStatus_Refuse) {
                $order->order_log = Constants::getCloudStockOrderLogText(7, $order->order_log, $reason);
            } else {
                $order->order_log = Constants::getCloudStockOrderLogText(6, '', $reason);
            }
            $order->save();
        } else {
            throw new \Exception('订单不存在');
        }
    }

    /**
     * 支付订单
     * @param int $memberId 会员ID
     * @param string $orderId 订单ID
     * @param int $payType 支付类型
     * @param $vouchers 支付凭证
     *   当使用余额支付时，它是支付密码，此时数据格式为字符串
     *   当使用线下支付时，它是用户上传的线下支付凭证图片(最多三张)，此时数据格式为 \Illuminate\Http\UploadedFile|array $voucherFiles
     *   当使用线上支付时，它是支付成功后的入账财务记录
     * @throws \Exception
     */
    public function pay($memberId, $orderId, $payType, $vouchers)
    {
        $config = Finance::getPayConfig(2);
        $coll = new Collection($config['types']);
        $curConfig = $coll->where('type', $payType)->values()->first();

        $order = CloudStockPurchaseOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('member_id', $memberId)
            ->where('id', $orderId)
            ->first();
        $payee = $order->payee;
        if (!$curConfig && !$payee) {
            throw new \Exception('支付方式错误，无法支付');
        }

        // 判断订单是否已经支付过
        if ($order->status != Constants::CloudStockPurchaseOrderStatus_NoPay) {
            return makeApiResponseFail(trans('shop-front.shop.order_paid'));
        }
        // 余额支付的情况
        if ($payType == CoreConstants::PayType_Balance) {
            // 如果是余额支付 要验证支付密码
            $member = new Member($memberId);
            if ($member->payPasswordIsNull()) {
                return makeApiResponse(402, trans('shop-front.shop.pay_password_error'));
            }
            if (!$member->payPasswordCheck($vouchers)) {
                return makeApiResponse(406, trans('shop-front.shop.pay_password_error'));
            }
            // 扣钱
            $payInfo = ['pay_type' => $payType, 'tradeno' => 'PAYORDER_' . $order->id];
            $financeId = FinanceHelper::payOrder($order->site_id, $order->member_id, $order->id, $order->total_money,
                $payInfo, 2);
            $order->status = Constants::CloudStockPurchaseOrderStatus_Reviewed;
            $order->payment_status = Constants::CloudStockPurchaseOrderPaymentStatus_Yes;
            $order->order_log = Constants::getCloudStockOrderLogText(4, '', '', $payType);
            $this->bindInvite($order);
        }
        // 线下支付的情况
        if (in_array($payType, [6, 7, 8, 9])) {
            $voucherFiles = [];
            $voucherSaveDir = Site::getSiteComdataDir('', true) . '/payment_voucher/';
            foreach ($vouchers as $voucherFile) {
                if (!$voucherFile) {
                    continue;
                }
                $imageName = date('YmdHis') . substr(md5(mt_rand()), 0, 6);
                $upload = new FileUpload($voucherFile, $voucherSaveDir, $imageName);
                $upload->reduceImageSize(1500);
                //$upload->save();
                $filePath = '/payment_voucher/' . $upload->getFullFileName();
                $voucherFiles[] = $filePath;
            }
            if (!$voucherFiles) {
                return makeApiResponse(400, '请上传支付凭证');
            }
            if (intval($payee) > 0) {
                $parentPayConfig = DealerAccount::getDealerPayConfig($payee);
                if (count($parentPayConfig['types'])) {
                    $order->payee = $payee;
                    $parentColl = new Collection($parentPayConfig['types']);
                    $curConfig = $parentColl->where('type', $payType)->values()->first();
                }
            }
            $snap = Payment::makeOffLinePaymentReceiptInfo($payType, $curConfig['account'], $curConfig['bank'], $curConfig['account_name']);
            $order->payment_voucher = implode(',', $voucherFiles);
            $order->receipt_info = json_encode($snap, JSON_UNESCAPED_UNICODE);
            $order->status = Constants::CloudStockPurchaseOrderStatus_Pay;
            //线下支付的付款状态应该是未审核
            $order->payment_status = Constants::CloudStockPurchaseOrderPaymentStatus_No;
            // 拒绝后的重新支付
            if ($order->payment_status == Constants::CloudStockPurchaseOrderPaymentStatus_Refuse) {
                $order->order_log = Constants::getCloudStockOrderLogText(3, $order->order_log);
            } else {
                $order->order_log = Constants::getCloudStockOrderLogText(2);
            }
        }
        // 线上支付的情况
        if (in_array($payType, \YZ\Core\Constants::getOnlinePayType())) {
            $financeId = FinanceHelper::payOrder($order->site_id, $order->member_id, $order->id, $order->total_money, $vouchers, 2);
            $order->status = Constants::CloudStockPurchaseOrderStatus_Reviewed;
            $order->payment_status = Constants::CloudStockPurchaseOrderPaymentStatus_Yes;
            $order->transaction_id = $vouchers['tradeno'];
            $order->order_log = Constants::getCloudStockOrderLogText(4, '', '', $payType);
            $this->bindInvite($order);
        }
        $order->pay_type = $payType;
        $order->pay_at = date('Y-m-d H:i:s');
        $order->save();
        if ($order->id) {
            $logId = VerifyLog::Log(Constants::VerifyLogType_CloudStockPurchaseOrderFinanceVerify, $order);
            $order->verify_log_id = $logId;
            $order->save();
            if($logId){
                $VerifyLog = VerifyLogModel::find($logId);
                DealerMessageNotice::sendMessageDealerVerify($VerifyLog);
            }

        }
        //消息通知
        $this->dispatch(new MessageNotice(CoreConstants::MessageType_Order_PaySuccess, $order));
        $this->dispatch(new MessageNotice(CoreConstants::MessageType_Order_NewPay, $order));
        $this->dispatch(new MessageNotice(CoreConstants::MessageType_CloudStock_Purchase_Commission_Under, $order));
        //自动配仓
        if ($order->status == Constants::CloudStockPurchaseOrderStatus_Reviewed && $order->payment_status == Constants::CloudStockPurchaseOrderPaymentStatus_Yes) {
            CloudStock::stockDeliverByOrder($order, false, true);
        }
        return makeApiResponse(200, 'ok');
    }

    /**
     * 格式化订单列表数据
     * @param $list
     * @return array
     */
    public function formatOrderList($list)
    {
        if (!$list) {
            return [];
        }
        foreach ($list as &$item) {
            $item['status_text'] = self::getOrderStatusText(intval($item['status']), $this->_getSub);
            $item['total_money'] = moneyCent2Yuan($item['total_money']);
            $item['payment_status_text'] = self::getOrderPaymentStatusText(intval($item['payment_status']));
            $item['other_status_text'] = self::getOrderOtherStatusText(
                intval($item['status']),
                intval($item['payment_status']),
                intval($item['payee']),
                $this->_getSub
            );
        }
        unset($item);
        return $list;
    }

    /**
     * 获取进货订单列表状态文案
     * @param int $status
     * @param bool $getSub 是否是下级订单的状态
     * @return string
     */
    public static function getOrderStatusText(int $status, $getSub = false)
    {
        switch ($status) {
            case Constants::CloudStockPurchaseOrderStatus_NoPay:
                return '待付款';
            case Constants::CloudStockPurchaseOrderStatus_Pay:
                return '待付款';
            case Constants::CloudStockPurchaseOrderStatus_Reviewed:
                return $getSub ? '待配仓/待结算' : '待配仓';
            case Constants::CloudStockPurchaseOrderStatus_Finished:
                return $getSub ? '已配仓/已结算' : '已完成';
            case Constants::CloudStockPurchaseOrderStatus_Cancel:
                return '订单取消';
            default:
                return '未知';
        }
    }

    /**
     * 获取进货订单的财务审核状态
     * @param int $status
     * @return string
     */
    public static function getOrderPaymentStatusText(int $status)
    {
        switch ($status) {
            case Constants::CloudStockPurchaseOrderPaymentStatus_No:
                return '财务未审核';
            case Constants::CloudStockPurchaseOrderPaymentStatus_Yes:
                return '财务审核通过';
            case Constants::CloudStockPurchaseOrderPaymentStatus_Refuse:
                return '财务审核未通过';
            default:
                return '未知';
        }
    }

    /**
     * 获取订单状态的其他文案
     * @param int $status
     * @param int $paymentStatus
     * @param int $payee
     * @param bool $getSub
     * @return string
     */
    public static function getOrderOtherStatusText(int $status, int $paymentStatus, int $payee, $getSub = false)
    {
        // 已支付 未审核
        if (
            $status == Constants::CloudStockPurchaseOrderStatus_Pay
        ) {
            return $getSub ? "下级已完成线下支付，待" . ($payee ? "您" : "公司") . "审核货款" : "已完成线下支付，待" . ($payee ? "上级" : "公司") . "审核货款";
        }
        // 已支付 审核未通过 需重新付款
        if (
            $paymentStatus == Constants::CloudStockPurchaseOrderPaymentStatus_Refuse
            && $status == Constants::CloudStockPurchaseOrderStatus_NoPay
        ) {
            return $getSub ? "审核货款不通过，等待买家重新支付" : "审核货款不通过，请重新支付";
        }
        if ($status == Constants::CloudStockPurchaseOrderStatus_Reviewed
            && $getSub) {
            return '下级已完成付款，等待您的配仓';
        }
        return "";
    }

    /**
     * 订单配仓
     * @param string $orderId
     * @param int $memberId
     * @return array|bool
     * @throws \Exception
     */
    public function stockDeliver($orderId, $memberId)
    {
        // 这个方法应该可以用 CloudStock::stockDeliverByOrder() 替换掉，还没验证，后面再处理 (2019/10/16 泉)
        if (!$orderId) {
            throw new \Exception(trans('shop-front.shop.data_error'));
        }
        $orderModel = CloudStockPurchaseOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('id', $orderId)
            ->first();
        if (!$orderModel) {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
        // 订单状态是否可以配仓
        if ($orderModel->status != Constants::CloudStockPurchaseOrderStatus_Reviewed) {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
        // 检测库存
        $checkData = $this->checkInventory($orderId, $memberId, true);
        // 库存不足
        if (!$checkData['check']['all_enough']) {
            return makeServiceResult(402, trans('shop-front.shop.stock_no_enough_cant_deliver'), $checkData['check']);
        } else {
            try {
                DB::beginTransaction();
                // 可以配仓
                $items = $checkData['order_items'];
                foreach ($items as $item) {
                    CloudStockSku::stockDeliver(
                        $item['product_id'],
                        $item['sku_id'],
                        $memberId,
                        $orderModel->member_id,
                        Constants::CloudStockOrderType_Purchase,
                        $orderId,
                        $item['id'],
                        $orderModel->pay_type,
                        $item['num'],
                        $item['money']
                    );
                }
                // 配仓完成后 要改变订单和订单商品的状态
                $orderModel->status = Constants::CloudStockPurchaseOrderStatus_Finished;
                $orderModel->stock_status = 1;
                // 更新完成时间
                $orderModel->finished_at = Carbon::now();
                // 更新日志
                $orderModel->order_log = Constants::getCloudStockOrderLogText(5, $orderModel->order_log, '',
                    $orderModel->pay_type);
                $orderModel->save();
                DB::commit();
                return true;
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }
    }

    /**
     * 检测订单中商品的库存
     * @param string $orderId
     * @param int $memberId 该订单的上级会员id
     * @param bool $returnModel 是否返回查找到的原始数据
     * @return array
     * @throws \Exception
     */
    public function checkInventory($orderId, $memberId, $returnModel = false)
    {
        if (!$orderId || !$memberId) {
            throw new \Exception(trans('shop-front.shop.data_error'));
        }
        // 获取出货云仓的会员id
        $cloudStockId = CloudStockModel::query()->where('site_id', $this->_siteId)
            ->where('member_id', $memberId)
            ->value('id');
        if (!$cloudStockId) {
            throw new \Exception(trans('shop-front.shop.cant_found_stock'));
        }
        $orderItems = CloudStockPurchaseOrderItemModel::query()
            ->where('site_id', $this->_siteId)
            ->where('cloudstock_id', $cloudStockId)
            ->where('order_id', $orderId)
            ->get();
        if (!$orderItems->count()) {
            throw new \Exception(trans('shop-front.shop.cant_found'));
        }
        $items = [];
        foreach ($orderItems as $item) {
            $items[] = [
                'product_id' => $item->product_id,
                'sku_id' => $item->sku_id,
                'need' => $item->num
            ];
        }
        // 检测库存
        $check = CloudStockSku::checkStockInventory($items, $memberId);
        return $returnModel ? ['order_items' => $orderItems, 'check' => $check] : $check;
    }

    /**
     * 绑定上下级
     * @param $orderIdOrModel
     * @throws \Exception
     */
    public function bindInvite($orderIdOrModel){
        if($orderIdOrModel instanceof CloudStockPurchaseOrderModel) $order = $orderIdOrModel;
        else $order = $this->getOrderModel($orderIdOrModel);
        $memberModel = MemberModel::find($order->member_id);
        if(intval($memberModel->has_bind_invite) !== 1) {
            $fans = WxUserModel::query()->where('member_id',$order->member_id)->first();
            if ($fans && $fans->invite) $inviteCode = intval($fans->invite);
            if (!$inviteCode) $inviteCode = intval(Session::get('invite')); //其次从Session里取
            if (!$inviteCode) $inviteCode = intval(Request::cookie('invite')); //再次从Cookie里取
            $member = new \YZ\Core\Member\Member($memberModel);
            $member->setParent($inviteCode);
        }
    }

    /**
     * 进货单财务审核
     * @param string $orderId 订单id
     * @param int $reviewStatus 审核后的状态
     * @param int $paymentStatus 是否确认收到线下付款
     * @param string $remark 拒绝的原因
     * @param  int $reviewMemberId 审核人的会员ID 默认 0 为公司
     * @return bool
     * @throws \Exception
     */
    public function financeReview($orderId, $reviewStatus = 0, $paymentStatus = 0, $remark = '', $reviewMemberId = 0)
    {
        $order = $this->getOrderModel($orderId);
        // 未审核的状态才可以审核
        if ($order->status != Constants::CloudStockPurchaseOrderStatus_Pay) {
            throw new \Exception('该订单状态无法审核');
        }
        // 审核通过
        if ($reviewStatus == 1) {
            if ($paymentStatus != 1) {
                throw new \Exception('请确认已收到线下付款');
            }
            $this->bindInvite($order);
            $order->payment_status = Constants::CloudStockPurchaseOrderPaymentStatus_Yes;
            // 修改状态为已审核
            $order->status = Constants::CloudStockPurchaseOrderStatus_Reviewed;
            // 调用手动配仓的方法 试着去配仓
            $deliver = CloudStock::stockDeliverByOrder($order, false, true);
            // 配仓不成功 只修改订单为已审核
            if ($deliver !== true) {
                // 修改log
                $order->order_log = Constants::getCloudStockOrderLogText(4, '', '', $order->pay_type);
                $order->save();
            }
            // 直接向上级进货的订单 审核通过后 需要添加一条财务记录 暂时不产生财务记录
            //$this->addFinance($order);
            // 是否触发升级
            $this->dispatch(new UpgradeDealerLevelJob($order->member_id));
            //消息通知
            $this->dispatch(new MessageNotice(CoreConstants::MessageType_Order_PaySuccess, $order, ['order_send' => 1]));
            $this->dispatch(new MessageNotice(CoreConstants::MessageType_CloudStock_Purchase_Commission_Under, $order, ['verify' => 1]));
          //  MessageNotice::dispatch(Constants::MessageType_CloudStock_Purchase_Commission_Under, $order，['verify'=>1]);
        } else {
            // 审核不通过的必须有拒绝原因
            if (!$remark) {
                throw new \Exception('请输入拒绝原因');
            }
            // 审核不通过 重新进入到待支付
            $order->status = Constants::CloudStockPurchaseOrderStatus_NoPay;
            // 修改log
            $order->order_log = Constants::getCloudStockOrderLogText(1);
            $order->payment_status = Constants::CloudStockPurchaseOrderPaymentStatus_Refuse;
            $order->refuse_remark = $remark;
            $order->save();
        }
        // 审核记录
        if ($reviewMemberId) {
            $order->review_member_id = $reviewMemberId;
            VerifyLog::Log(Constants::VerifyLogType_CloudStockPurchaseOrderFinanceVerify, $order);
        }
        return true;
    }

    /**
     * 获取订单model
     * @param string $orderId
     * @param string|array $field 需要查找的字段
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     * @throws \Exception
     */
    public function getOrderModel($orderId, $field = '*')
    {
        if (!$orderId) {
            throw new \Exception('订单ID错误');
        }
        $order = CloudStockPurchaseOrderModel::query()
            ->where('site_id', $this->_siteId)
            ->where('id', $orderId)
            ->select($field)
            ->first();
        if ($order) {
            return $order;
        } else {
            throw new \Exception('订单不存在');
        }
    }

    /**
     * 向上级进货的订单 审核通过后要增加财务记录
     * @param string|CloudStockPurchaseOrderModel $orderIdOrModel
     * @return bool|mixed
     * @throws \Exception
     */
    private function addFinance($orderIdOrModel)
    {
        if ($orderIdOrModel instanceof CloudStockPurchaseOrderModel) {
            $order = $orderIdOrModel;
        } else {
            $order = $this->getOrderModel($orderIdOrModel);
        }

        // 向上级进货 并且状态为已审核
        if (
            $order->payment_status == Constants::CloudStockPurchaseOrderPaymentStatus_Yes
            && ($order->status == Constants::CloudStockPurchaseOrderStatus_Reviewed || $order->status == Constants::CloudStockPurchaseOrderStatus_Finished)
        ) {

            return FinanceHelper::addCloudStockPurchaseMoney(
                $this->_siteId,
                $order->member_id,
                $order->payee,
                $order->id,
                $order->total_money,
                $order->pay_type
            );
        }
    }
}