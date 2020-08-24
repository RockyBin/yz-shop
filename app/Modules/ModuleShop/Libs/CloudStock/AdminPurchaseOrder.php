<?php
/**
 * 后台代理进货单业务逻辑
 * User: liyaohui
 * Date: 2019/8/23
 * Time: 14:40
 */

namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Jobs\UpgradeDealerLevelJob;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\CloudStockModel;
use App\Modules\ModuleShop\Libs\VerifyLog\VerifyLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use YZ\Core\Constants as CoreConstants;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderItemModel;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use YZ\Core\Logger\Log;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\MemberModel;
use YZ\Core\Model\WxUserModel;
use YZ\Core\Site\Site;
use Illuminate\Foundation\Bus\DispatchesJobs;

class AdminPurchaseOrder
{
    use DispatchesJobs;
    use PurchaseOrderTrait;
    protected $_siteId = 0;

    public function __construct()
    {
        $this->_siteId = Site::getCurrentSite()->getSiteId();
    }

    /**
     * 获取进货订单列表
     * @param array $params
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function getList($params = [], int $page = 1, int $pageSize = 20)
    {
        $showAll = $params['show_all'] || ($params['ids'] && strlen($params['ids'] > 0)) ? true : false; // 是否显示所有，导出功能用，默认False

        $query = CloudStockPurchaseOrderModel::query()->from('tbl_cloudstock_purchase_order as order')
            ->where('order.site_id', $this->_siteId)
            ->leftJoin('tbl_member as m', 'm.id', 'order.member_id');
        // 状态
        if (isset($params['status']) && is_numeric($params['status'])) {
            $query->where('order.status', $params['status']);
        }
        // 关键词搜索
        if (isset($params['keyword']) && trim($params['keyword']) !== '') {
            $keyword = '%' . $params['keyword'] . '%';
            $paramsKeyword = $params['keyword'];
            $query->where(function ($q) use ($keyword, $paramsKeyword) {
                $q->where('m.nickname', 'like', $keyword);
                $q->orWhere('m.name', 'like', $keyword);
                if (preg_match('/^\w+$/i', $paramsKeyword)) {
                    $q->orWhere('order.id', 'like', $keyword)
                        ->orWhere('m.mobile', 'like', $keyword);
                }
            });
        }
        // 下单时间
        if (isset($params['created_at_start']) && trim($params['created_at_start']) !== '') {
            $query->where('order.created_at', '>=', $params['created_at_start']);
        }
        if (isset($params['created_at_end']) && trim($params['created_at_end']) !== '') {
            $query->where('order.created_at', '<=', $params['created_at_end']);
        }
        // ids 用于导出
        if ($params['ids']) {
            $ids = myToArray($params['ids']);
            if (count($ids) > 0) {
                $query->whereIn('order.id', $ids);
            }
        }
        $total = $query->count();
        $lasePage = ceil($total / $pageSize);
        $page = $page < 1 ? 1 : $page;
        $page = $page > $lasePage ? $lasePage : $page;
        if ($total > 0 && $showAll) {
            $page = 1;
            $pageSize = $total;
        }
        $list = $query->orderByDesc('order.created_at')
            ->forPage($page, $pageSize)
            ->select([
                'order.id as order_id',
                'm.mobile',
                'm.nickname',
                'm.name',
                'member_id',
                'order.total_money',
                'order.created_at',
                'order.status',
                'order.payee'
            ])
            ->get();
        $list = $this->formatOrderList($list);
        return [
            'total' => $total,
            'page_size' => $pageSize,
            'current' => $page,
            'last_page' => $lasePage,
            'list' => $list
        ];
    }

    /**
     * 获取进货单详情
     * @param $orderId
     * @return array
     * @throws \Exception
     */
    public function getOrderInfo($orderId)
    {
        $order = $this->getOrderModel($orderId);
        $this->formatOrderInfo($order);
        // 先获取该订单会员信息
        $memberInfo = MemberModel::query()
            ->where('site_id', $this->_siteId)
            ->where('id', $order->member_id)
            ->select(['nickname', 'headurl', 'mobile','name'])
            ->first()->toArray();
        // 收款人
        $payeeName = '公司收款';
        if ($order->payee && in_array($order->pay_type, CoreConstants::getOfflinePayType())) {
            $payeeName = MemberModel::query()
                ->where('site_id', $this->_siteId)
                ->where('id', $order->payee)
                ->value('nickname');
            $payeeName = "上级收款【{$payeeName}】";
        }
        $memberInfo['payee_name'] = $payeeName;
        $query = CloudStockPurchaseOrderItemModel::query()->from('tbl_cloudstock_purchase_order_item as item')
            ->where('item.site_id', $this->_siteId)
            ->where('item.order_id', $order->id);
        // 根据上级云仓 查找是否缺货 上级为总仓时 使用tbl_product_skus表的库存
        // 上级为其他代理时 使用对应云仓的库存
        // TODO 目前版本是只有上级和总仓两种情况 不会有交叉的情况 暂时这样处理
        // TODO 后面可以跨仓库时 这里需要修改
        if ($order->cloudstock_id > 0) {
            // 查找上级云仓信息
            $parentInfo = CloudStockModel::query()
                ->from('tbl_cloudstock as c')
                ->leftJoin('tbl_member as m', 'm.id', 'c.member_id')
                ->where('c.site_id', $this->_siteId)
                ->where('c.id', $order->cloudstock_id)
                ->select(['c.id', 'm.nickname', 'c.member_id'])
                ->first()->toArray();
            if (!$parentInfo) {
                throw new \Exception('找不到该订单所属的云仓信息');
            }
            $order->cloudstock_name = $parentInfo['nickname'];
            $query->leftJoin('tbl_cloudstock_sku as sku', function ($join) use ($parentInfo) {
                $join->on('sku.sku_id', 'item.sku_id')
                    ->where('sku.member_id', $parentInfo['member_id']);
            });
        } else {
            $query->leftJoin('tbl_product_skus as sku', 'sku.id', 'item.sku_id');
        }
        $productList = $query->select(['item.*', 'sku.inventory'])
            ->get();
        return [
            'order_info' => array_merge($order->toArray(), $memberInfo),
            'product_list' => $this->formatOrderProductList($productList, $order)
        ];
    }

    /**
     * 格式化订单详情数据
     * @param $order
     */
    public function formatOrderInfo(&$order)
    {
        $order->total_money = moneyCent2Yuan($order->total_money);
        $order->status_text = self::getOrderStatusText($order->status, $order->payee);
        // 支付类型文案
        $order->pay_type_text = self::getOrderSettleTypeText($order->pay_type);
        // 支付凭证
        $order->payment_voucher = $order->payment_voucher ? explode(',', $order->payment_voucher) : [];
        // 订单流程
        $order->order_log = json_decode($order->order_log, true);
        // 收款信息
        $order->receipt_info = json_decode($order->receipt_info, true);
    }

    /**
     * 格式化订单商品列表
     * @param $list
     * @param $orderModel
     * @return array
     */
    public function formatOrderProductList($list, $orderModel)
    {
        if (!$list) return [];
        // 如果订单状态为待配仓 商品的状态则有待配仓和已配仓两种
        $itemStatus = $orderModel->status == Constants::CloudStockPurchaseOrderStatus_Reviewed ?
            '' : $orderModel->status_text;
        foreach ($list as &$item) {
            $item['stock_status_text'] = self::getOrderItemStatusText(intval($item['stock_status']), $itemStatus);
            $item['money'] = moneyCent2Yuan($item['money']);
            $item['price'] = moneyCent2Yuan($item['price']);
            // 规格名称
            $item['sku_name'] = $item['sku_names'] ? json_decode($item['sku_names'], true) : [];
//            $item['sku_name'] = $item['sku_name'] ? implode(',', $item['sku_name']) : '';
            unset($item['sku_names']);
            // 云仓名称
            $item['cloudstock_name'] = $item['cloudstock_id'] > 0 ? $orderModel->cloudstock_name : '总仓';
            unset($item['nickname']);
            // 判断是否缺货
            $item['shortage_stock'] = $item['inventory'] < $item['num'];
        }
        return $list;
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
     * 编辑内部备注
     * @param string $orderId
     * @param string $text
     * @return bool
     * @throws \Exception
     */
    public function editRemarkInside($orderId, $text = '')
    {
        $order = $this->getOrderModel($orderId);
        $order->remark_inside = $text;
        return $order->save();
    }

    /**
     * 获取财务审核需要的信息  给列表审核用
     * @param string $orderId
     * @return array
     * @throws \Exception
     */
    public function getFinanceReviewInfo($orderId)
    {
        $field = ['total_money', 'status', 'pay_type', 'payment_voucher', 'receipt_info', 'member_id', 'payee'];
        $order = $this->getOrderModel($orderId, $field);
        $memberInfo = MemberModel::query()
            ->where('site_id', $this->_siteId)
            ->where('id', $order->member_id)
            ->select(['nickname', 'headurl', 'mobile'])
            ->first()->toArray();
        $this->formatOrderInfo($order);
        return array_merge($order->toArray(), $memberInfo);
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
        if ($order->payee) {
            throw new \Exception('该订单需要上级审核');
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
            $deliver = $this->orderManualStockDeliver($order, false, true);
            // 配仓不成功 只修改订单为已审核
            if ($deliver !== true) {
                // 修改log
                $order->order_log = Constants::getCloudStockOrderLogText(4, '', '', $order->pay_type);
                $order->save();
            }
            // 直接向平台进货的订单 审核通过后 需要添加一条财务记录
            $this->addFinance($order);
            // 是否触发升级
            $this->dispatch(new UpgradeDealerLevelJob($order->member_id));
            //消息通知
            $this->dispatch(new MessageNotice(CoreConstants::MessageType_Order_PaySuccess, $order, ['order_send' => 1]));
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
     * 向平台进货的订单 审核通过后要增加财务记录
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

        // 向平台进货 并且状态为已审核
        if (
            $order->payment_status == Constants::CloudStockPurchaseOrderPaymentStatus_Yes
            && ($order->status == Constants::CloudStockPurchaseOrderStatus_Reviewed || $order->status == Constants::CloudStockPurchaseOrderStatus_Finished)
        ) {
            // 查找会员推荐人
            $parentId = MemberModel::query()
                ->where('site_id', $this->_siteId)
                ->where('id', $order->member_id)
                ->value('invite1');

            return FinanceHelper::addCloudStockPurchaseMoney(
                $this->_siteId,
                $order->member_id,
                $parentId,
                $order->id,
                $order->total_money,
                $order->pay_type
            );
        }
    }

    /**
     * 进货单手动配仓，前台线上支付成功后也会调用此方法进行自动配仓
     * @param CloudStockPurchaseOrderModel|string $orderIdOrModel 订单模型或订单id
     * @param bool $throw 是否抛出错误
     * @param bool $isAuto 是否是自动配仓
     * @return array|bool
     * @throws \Exception
     */
    public function orderManualStockDeliver($orderIdOrModel, $throw = false, $isAuto = false)
    {
        return CloudStock::stockDeliverByOrder($orderIdOrModel, $throw, $isAuto);
    }

    /**
     * 格式化订单列表数据
     * @param $list
     * @return array
     */
    public function formatOrderList($list)
    {
        if (!$list) return [];
        foreach ($list as &$item) {
            $item['status_text'] = self::getOrderStatusText(intval($item['status']), $item['payee']);
            $item['total_money'] = moneyCent2Yuan($item['total_money']);
        }
        return $list;
    }

    /**
     * 获取进货订单列表状态文案
     * @param int $status
     * @param int $payee 收款人为0则是平台收款
     * @return string
     */
    public static function getOrderStatusText(int $status, int $payee)
    {
        switch ($status) {
            case Constants::CloudStockPurchaseOrderStatus_NoPay:
                return '待付款';
            case Constants::CloudStockPurchaseOrderStatus_Pay:
                if ($payee) {
                    return '待上级审核';
                }
                return '待公司审核';
            case Constants::CloudStockPurchaseOrderStatus_Reviewed:
                return '待配仓';
            case Constants::CloudStockPurchaseOrderStatus_Finished:
                return '已完成';
            case Constants::CloudStockPurchaseOrderStatus_Cancel:
                return '订单取消';
            default:
                return '未知';
        }
    }

    /**
     * 配仓状态
     * @param int $status
     * @param string $statusText 需要直接使用订单的状态
     * @return string
     */
    public static function getOrderItemStatusText(int $status, $statusText = '')
    {
        if ($statusText != '') return $statusText;
        switch ($status) {
            case Constants::CloudStockPurchaseOrderItemStatus_No:
                return '待配仓';
            case Constants::CloudStockPurchaseOrderItemStatus_Yes:
                return '已配仓';
            default:
                return '未知';
        }
    }

    public static function getOrderSettleTypeText(int $type)
    {
        if (!$type) return '';
        switch ($type) {
            case CoreConstants::PayType_Unknow:
                return '';
            case CoreConstants::PayType_Manual:
                return '线下结算';
            case CoreConstants::PayType_Balance:
                return '线上结算-余额';
            case CoreConstants::PayType_Weixin:
                return '线上结算-微信';
            case CoreConstants::PayType_Alipay:
                return '线上结算-支付宝';
            case CoreConstants::PayType_TongLian:
                return '通联支付';
            case CoreConstants::PayType_WeixinQrcode:
                return '线下结算-微信';
            case CoreConstants::PayType_AlipayQrcode:
                return '线下结算-支付宝';
            case CoreConstants::PayType_AlipayAccount:
                return '线下结算-支付宝';
            case CoreConstants::PayType_Bank:
                return '线下结算-银行账户';
            default:
                return '未知结算方式';
        }
    }

    /**
     * 支付类型文案 给财务记录用
     * @param int $type
     * @return string
     */
    public static function getOrderSettleTypeTextForFinance(int $type)
    {
        if (in_array($type, CoreConstants::getOfflinePayType())) {
            switch ($type) {
                case CoreConstants::PayType_Manual:
                    return '线下结算';
                case CoreConstants::PayType_WeixinQrcode:
                    return '线下微信收款码';
                case CoreConstants::PayType_AlipayQrcode:
                    return '线下支付宝收款码';
                case CoreConstants::PayType_AlipayAccount:
                    return '线下支付宝账户';
                case CoreConstants::PayType_Bank:
                    return '线下银行账户';
                default:
                    return '未知结算方式';
            }
        } else {
            return CoreConstants::getPayTypeText($type);
        }
    }
}