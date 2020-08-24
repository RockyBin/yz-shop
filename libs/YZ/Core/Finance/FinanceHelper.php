<?php

namespace YZ\Core\Finance;

use App\Modules\ModuleShop\Jobs\UpgradeAgentLevelJob;
use App\Modules\ModuleShop\Jobs\UpgradeDistributionLevelJob;
use App\Modules\ModuleShop\Libs\CloudStock\AdminPurchaseOrder;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Member\MemberWithdrawAccount;
use App\Modules\ModuleShop\Libs\Message\MessageNotice;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;
use YZ\Core\Constants;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use YZ\Core\Locker\Locker;
use YZ\Core\Payment\Payment;
use YZ\Core\Member\Member;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * 财务工具类，通用是写一些用于财务的静态方法
 * Class FinanceHelper
 * @package YZ\Core\Finance
 */
class FinanceHelper
{
    use DispatchesJobs;

    /**
     * 统计指定会员的佣金总收入
     * @param $memberId
     * @param int $type 默认佣金
     * @return mixed
     */
    public static function getMemberTotalCommission($memberId, $type = Constants::FinanceType_Commission)
    {
        $money = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Active)
            ->where('type', $type)
            ->where('money', '>', 0)
            ->sum('money');
        return $money;
    }

    /**
     * 统计指定会员的佣金余额
     * @param $memberId
     * @param int $type 默认佣金
     * @return mixed
     */
    public static function getMemberCommissionBalance($memberId, $type = Constants::FinanceType_Commission)
    {
        $in = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('type', $type)
            ->where('status', Constants::FinanceStatus_Active)
            ->where('money', '>', 0)
            ->sum('money');
        $out = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('type', $type)
            ->where('status', '<>', Constants::FinanceStatus_Invalid)
            ->where('money', '<', 0)
            ->sum('money');
        // 所有入帐并且为生效的钱 - 所有出帐并且状态为生效或冻结的钱
        $balance = $in - abs($out);
        return $balance;
    }

    /**
     * 待提现审核佣金
     * @param $memberId
     * @param int $type 默认佣金
     * @return mixed
     */
    public static function getMemberCommissionCheck($memberId, $type = Constants::FinanceType_Commission)
    {
        $money = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Freeze)
            ->where('type', $type)
            ->whereIn('out_type', [Constants::FinanceOutType_Withdraw, Constants::FinanceOutType_CommissionToBalance])
            ->where('money', '<', 0)
            ->sum('money');
        return $money;
    }

    /**
     * 待结算佣金
     * @param $memberId
     * @param int $type 默认佣金
     * @return mixed
     */
    public static function getMemberCommissionUnsettled($memberId, $type = Constants::FinanceType_Commission)
    {
        $money = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Freeze)
            ->where('type', $type)
            ->where('money', '>', 0)
            ->sum('money');
        return $money;
    }

    /**
     * 结算失败佣金
     * @param $memberId
     * @param int $type 默认佣金
     * @return mixed
     */
    public static function getMemberCommissionFail($memberId, $type = Constants::FinanceType_Commission)
    {
        $money = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Invalid)
            ->where('type', $type)
            ->where('money', '>', 0)
            ->sum('money');
        return $money;
    }

    /**
     * 统计指定会员的财务余额，包括正常财务和赠金
     * @param $memberId
     * @return mixed
     */
    public static function getMemberBalance($memberId)
    {
        $in = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Active)
            ->whereIn('type', [Constants::FinanceType_Normal, Constants::FinanceType_Gift])
            ->where('money', '>', 0)
            ->sum('money');
        $out = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', '<>', Constants::FinanceStatus_Invalid)
            ->where('money', '<', 0)
            ->whereIn('type', [Constants::FinanceType_Normal, Constants::FinanceType_Gift])
            ->sum('money');
        // 所有入帐并且为生效的钱 - 所有出帐并且状态为生效或冻结的钱，$out 为负数
        $balance = $in - abs($out);
        return $balance;
    }

    /**
     * 统计指定会员的财务余额，包括正常财务和赠金
     * @param $memberId
     * @return mixed
     */
    public static function getFinanceBalanceList($params)
    {
        // 分页参数
        $page = intval($params['page']);
        $page_size = intval($params['page_size']);
        $showAll = $params['show_all'] || ($params['ids'] && strlen($params['ids'] > 0)) ? true : false; // 是否显示所有，导出功能用，默认False
        if ($page < 1) $page = 1;
        if ($page_size < 1) $page_size = 20;

        $expression = MemberModel::query()
            ->where('site_id', Site::getCurrentSite()->getSiteId());

        // 所有入帐并且为生效的钱
        $expression->addSelect(DB::raw('(SELECT sum(f1.money) from tbl_finance as f1 where f1.member_id=tbl_member.id and type in (' . Constants::FinanceType_Normal . ',' . Constants::FinanceType_Gift . ') and money > 0 and status=' . Constants::FinanceStatus_Active . ') as in_money'))
            // 所有出帐并且状态为生效或冻结的钱
            ->addSelect(DB::raw('(SELECT sum(f1.money) from tbl_finance as f1 where f1.member_id=tbl_member.id and type in (' . Constants::FinanceType_Normal . ',' . Constants::FinanceType_Gift . ') and money < 0 and status<>' . Constants::FinanceStatus_Invalid . ') as out_money'))
            // 累计充值
            ->addSelect(DB::raw('(SELECT sum(f1.money) from tbl_finance as f1 where f1.member_id=tbl_member.id and type in (' . Constants::FinanceType_Normal . ') and pay_type <>' . Constants::PayType_Bonus . ' and in_type in (' . Constants::FinanceInType_Recharge . ',' . Constants::FinanceInType_Give . ',' . Constants::FinanceInType_Manual . ') and money > 0 and status=' . Constants::FinanceStatus_Active . ') as cumulative_recharge'))
            // 已提现
            ->addSelect(DB::raw('(SELECT sum(f1.money) from tbl_finance as f1 where f1.member_id=tbl_member.id and type in (' . Constants::FinanceType_Normal . ') and money < 0 and out_type = ' . Constants::FinanceOutType_Withdraw . ' and status=' . Constants::FinanceStatus_Active . ') as withdrawal_done'))
            // 提现中
            ->addSelect(DB::raw('(SELECT sum(f1.money) from tbl_finance as f1 where f1.member_id=tbl_member.id and type in (' . Constants::FinanceStatus_Freeze . ') and money < 0 and out_type = ' . Constants::FinanceOutType_Withdraw . ' and status=' . Constants::FinanceStatus_Freeze . ') as withdrawal_ing'))
            ->addSelect('tbl_member.*');

        $total_in_money = $expression->get()->sum('in_money');
        $total_out_money = $expression->get()->sum('out_money');
        $total_cumulative_recharge = $expression->get()->sum('cumulative_recharge');
        $total_withdrawal_done = $expression->get()->sum('withdrawal_done');
        $total_withdrawal_ing = $expression->get()->sum('withdrawal_ing');

        if (isset($params['keyword'])) {
            $expression->where(function ($query) use ($params) {
                $query->where('tbl_member.nickname', 'like', '%' . $params['keyword'] . '%');
                $query->orWhere('tbl_member.name', 'like', '%' . $params['keyword'] . '%');
                $query->orWhere('tbl_member.mobile', 'like', '%' . $params['keyword'] . '%');
            });
        };

        // ids
        if ($params['ids']) {
            $ids = myToArray($params['ids']);
            if (count($ids) > 0) {
                $expression->whereIn('tbl_member.id', $ids);
            }
        }

        $total = $expression->count();
        if ($total > 0 && $showAll) {
            $page = 1;
            $page_size = $total;
        }

        $expression->forPage($page, $page_size);
        $expression->orderBy('tbl_member.created_at', 'desc');
        $list = $expression->get();
        foreach ($list as &$item) {
            $item->available_balance = moneyCent2Yuan(abs($item->in_money) - abs($item->out_money));
            $item->cumulative_recharge = $item->cumulative_recharge ? moneyCent2Yuan(abs($item->cumulative_recharge)) : 0;
            $item->withdrawal_done = $item->withdrawal_done ? moneyCent2Yuan(abs($item->withdrawal_done)) : 0;
            $item->withdrawal_ing = $item->withdrawal_ing ? moneyCent2Yuan(abs($item->withdrawal_ing)) : 0;
        }
        //输出-最后页数
        $last_page = ceil($total / $page_size);
        $result = [
            'total_available_balance' => moneyCent2Yuan(abs($total_in_money) - abs($total_out_money)),
            'total_cumulative_recharge' => $total_cumulative_recharge ? moneyCent2Yuan(abs($total_cumulative_recharge)) : 0,
            'total_withdrawal_done' => $total_withdrawal_done ? moneyCent2Yuan(abs($total_withdrawal_done)) : 0,
            'total_withdrawal_ing' => $total_withdrawal_ing ? moneyCent2Yuan(abs($total_withdrawal_ing)) : 0,
            'total' => $total,
            'page_size' => $page_size,
            'current' => $page,
            'last_page' => $last_page,
            'list' => $list
        ];
        return $result;
    }

    /**
     * 统计指定会员的云仓收入余额
     *
     * @param int $memberId
     * @return int
     */
    public static function getCloudStockBalance($memberId)
    {
        $in = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Active)
            ->whereIn('type', [Constants::FinanceType_CloudStock])
            ->where('money', '>', 0)
            ->sum('money');
        $out = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', '<>', Constants::FinanceStatus_Invalid)
            ->where('money', '<', 0)
            ->whereIn('type', [Constants::FinanceType_CloudStock])
            ->sum('money');
        // 所有入帐并且为生效的钱 - 所有出帐并且状态为生效或冻结的钱，$out为负数
        $balance = $in - abs($out);
        return $balance;
    }

    /**
     * 统计指定会员的供应商收入余额
     *
     * @param int $memberId
     * @return int
     */
    public static function getSupplierBalance($memberId)
    {
        $in = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Active)
            ->whereIn('type', [Constants::FinanceType_Supplier])
            ->where('money', '>', 0)
            ->sum('money');
        $out = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', '<>', Constants::FinanceStatus_Invalid)
            ->where('money', '<', 0)
            ->whereIn('type', [Constants::FinanceType_Supplier])
            ->sum('money');
        // 所有入帐并且为生效的钱 - 所有出帐并且状态为生效或冻结的钱，$out为负数
        $balance = $in - abs($out);
        return $balance;
    }

    /**
     * 云仓待提现审核金额
     * @param $memberId
     * @return mixed
     */
    public static function getCloudStockCheck($memberId)
    {
        $money = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Freeze)
            ->where('type', Constants::FinanceType_CloudStock)
            ->whereIn('out_type', [Constants::FinanceOutType_Withdraw, Constants::FinanceOutType_CloudStockGoodsToBalance])
            ->where('money', '<', 0)
            ->sum('money');
        return abs($money);
    }

    /**
     * 订单交费（余额支付会生成订单的负数的交费记录，第三方支付只需检测状态）
     * @param $siteId 网站ID
     * @param $memberId 会员ID
     * @param $orderId 订单ID
     * @param $money 订单金额（单位：分）
     * @param array $payInfo 原在线支付时生成的入帐方向的财务信息
     * @param int $orderType 订单类型，1 = 零售订单，2 = 代理进货单，3 = 代理加盟费
     * @throws \Exception
     */
    public static function payOrder($siteId, $memberId, $orderId, $money, array $payInfo, $orderType = 1)
    {
        $financeId = 0;
        $locker = new Locker('doBalancePay-' . Site::getCurrentSite()->getSiteId() . $memberId, 20);
        try {
            if ($locker->lock()) {
                $payType = intval($payInfo['pay_type']);
                if ($payType == Constants::PayType_Balance) {
                    // 检查用户余额
                    $balance = FinanceHelper::getMemberBalance($memberId);
                    if ($balance < $money) {
                        throw new \Exception(trans('shop-front.finance.balance_not_enough'));
                    }
                    // 插入负数的交费记录
                    $about = '支付订单，订单号：' . $orderId;
                    $outType = Constants::FinanceOutType_PayOrder;
                    if ($orderType == 2) {
                        $about = '支付云仓进货单，进货单号：' . $orderId;
                        $outType = Constants::FinanceOutType_CloudStock_PayOrder;
                    } elseif ($orderType == Constants::FinanceOrderType_CloudStock_TakeDelivery) {
                        $about = '支付云仓提货运费，提货单号：' . $orderId;
                        $outType = Constants::FinanceOutType_CloudStock_TakeDeliver_Fright;
                    }
                    $financeModel = new Finance();
                    $finInfo = [
                        'site_id' => $siteId,
                        'member_id' => $memberId,
                        'type' => Constants::FinanceType_Normal,
                        'tradeno' => $payInfo['tradeno'],
                        'pay_type' => Constants::PayType_Balance,
                        'out_type' => $outType,
                        'order_id' => $orderId,
                        'order_type' => $orderType,
                        'operator' => '',
                        'terminal_type' => $payInfo['terminal_type'],
                        'money' => $money * -1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'about' => $about,
                        'status' => Constants::FinanceStatus_Active,
                        'active_at' => date('Y-m-d H:i:s'),
                    ];
                    $financeId = $financeModel->add($finInfo);
                } else if (in_array($payType, Constants::getOnlinePayType())) {
                    // 验证财务记录是否存在
                    $financeModel = FinanceModel::query()
                        ->where('site_id', $siteId)
                        ->where('member_id', $memberId)
                        ->where('type', Constants::FinanceType_Transfer)
                        ->where('is_real', Constants::FinanceIsReal_Yes)
                        ->where('pay_type', $payType)
                        ->where('in_type', Constants::FinanceInType_Trade)
                        ->where('tradeno', $payInfo['tradeno'])
                        ->where('status', Constants::FinanceStatus_Active)
                        ->first();
                    if (!$financeModel) {
                        throw new \Exception('No Online Pay');
                    }
                    // 验证金额大小
                    if (intval($financeModel->money_real) < intval($money)) {
                        throw new \Exception('Money Not Enough');
                    }
                    $financeId = $financeModel->id;
                } else {
                    throw new \Exception('Pay Type Not Support');
                }

                $locker->unlock();
            }
            return $financeId;
        } catch (\Exception $e) {
            $locker->unlock();
            throw $e;
        }
    }

    /**
     * 添加云仓进货货款记录（下级或者零售） 用于结算给进货人的
     * @param $siteId
     * @param $memberId
     * @param $fromMemberIds
     * @param $orderId
     * @param $money
     * @param int $payType
     * @return bool|mixed
     * @throws \Exception
     */
    public static function addCloudStockGoodsMoney($siteId, $memberId, $fromMemberIds, $orderId, $money, $payType = Constants::PayType_Manual)
    {
        $payTypeText = AdminPurchaseOrder::getOrderSettleTypeTextForFinance($payType);
        $finInfo = [
            'site_id' => $siteId,
            'member_id' => $memberId,
            'type' => Constants::FinanceType_CloudStock,
            'sub_type' => Constants::FinanceSubType_CloudStock_Goods,
            'order_type' => LibsConstants::CloudStockOrderType_Purchase,
            'pay_type' => $payType,
            'in_type' => Constants::FinanceInType_CloudStockGoods,
            'tradeno' => 'YC_' . $orderId . randInt(1000),
            'order_id' => $orderId,
            'terminal_type' => Constants::TerminalType_Unknown,
            'money' => $money,
            'created_at' => date('Y-m-d H:i:s'),
            'about' => "支付云仓进货单-{$payTypeText},进货单号：{$orderId}",
            'status' => Constants::FinanceStatus_Active,
            'active_at' => date('Y-m-d H:i:s'),
            'is_real' => 1
        ];
        if (is_array($fromMemberIds)) {
            $i = 1;
            foreach ($fromMemberIds as $id) {
                $finInfo['from_member' . $i] = $id;
                $i++;
            }
        } else {
            $finInfo['from_member1'] = $fromMemberIds;
        }
        if ($money > 0) {
            $financeObj = new Finance();
            $financeId = $financeObj->add($finInfo);
            $financeRedisKey = 'finance' . $orderId . Constants::FinanceType_CloudStockPurchase . Constants::FinanceInType_CloudStockGoods . $memberId;
            if (!Redis::exists($financeRedisKey)) {
                Redis::setex($financeRedisKey, 60, '');
                MessageNotice::dispatch(Constants::MessageType_CloudStock_Purchase_Commission_Under, $financeObj->getModel());
            }
        }
        return $financeId;
    }

    /**
     * 添加向平台进货时的货款记录 不用于结算
     * @param $siteId
     * @param $memberId
     * @param $fromMemberIds
     * @param $orderId
     * @param $money
     * @param int $payType
     * @return bool|mixed
     * @throws \Exception
     */
    public static function addCloudStockPurchaseMoney($siteId, $memberId, $fromMemberIds, $orderId, $money, $payType = Constants::PayType_Manual)
    {
        $payTypeText = AdminPurchaseOrder::getOrderSettleTypeTextForFinance($payType);
        $finInfo = [
            'site_id' => $siteId,
            'member_id' => $memberId,
            'type' => Constants::FinanceType_CloudStockPurchase,
            'order_type' => LibsConstants::CloudStockOrderType_Purchase,
            'pay_type' => $payType,
            'in_type' => Constants::FinanceInType_CloudStockGoods,
            'tradeno' => 'YC_' . $orderId . randInt(1000),
            'order_id' => $orderId,
            'terminal_type' => Constants::TerminalType_Unknown,
            'money' => $money,
            'created_at' => date('Y-m-d H:i:s'),
            'about' => "支付云仓进货单-{$payTypeText},进货单号：{$orderId}",
            'status' => Constants::FinanceStatus_Active,
            'active_at' => date('Y-m-d H:i:s'),
            'is_real' => 1
        ];
        if (is_array($fromMemberIds)) {
            $i = 1;
            foreach ($fromMemberIds as $id) {
                $finInfo['from_member' . $i] = $id;
                $i++;
            }
        } else {
            $finInfo['from_member1'] = $fromMemberIds;
        }
        if ($money > 0) {
            $financeObj = new Finance();
            $financeId = $financeObj->add($finInfo);
        }
        return $financeId;
    }

    /**
     * 添加代理加盟费入帐记录
     *
     * @param $siteId
     * @param $memberId
     * @param $orderId 订单ID，格式如 AgentInitial.date('YmdHis')
     * @param $money
     * @param $payType 支付方式，默认为手工
     * @return void
     */
    public static function addAgentInitialMoney($siteId, $memberId, $orderId, $money, $payType = Constants::PayType_Manual)
    {
        $isReal = Constants::FinanceIsReal_Yes;
        if ($payType == Constants::PayType_Balance) $isReal = Constants::FinanceIsReal_No;
        $finInfo = [
            'site_id' => $siteId,
            'member_id' => $memberId,
            'type' => Constants::FinanceType_AgentInitial,
            'pay_type' => $payType,
            'in_type' => Constants::FinanceInType_AgentInitial,
            'tradeno' => $orderId . randInt(1000),
            'order_id' => $orderId,
            'terminal_type' => Constants::TerminalType_Unknown,
            'money' => $money,
            'is_real' => $isReal,
            'created_at' => date('Y-m-d H:i:s'),
            'about' => '支付代理加盟费',
            'status' => Constants::FinanceStatus_Active,
            'active_at' => date('Y-m-d H:i:s')
        ];
        if ($money > 0) {
            $financeObj = new Finance();
            $financeId = $financeObj->add($finInfo);
        }
        return $financeId;
    }

    /**
     * 使用余额支付代理加盟费
     *
     * @param $siteId
     * @param $memberId
     * @param $orderId 订单ID，格式如 AgentInitial.date('YmdHis')
     * @param $money
     * @return void
     */
    public static function payAgentInitialMoneyWithBalance($siteId, $memberId, $orderId, $money)
    {
        $locker = new Locker('doBalancePay-' . Site::getCurrentSite()->getSiteId() . $memberId, 20);
        try {
            if ($locker->lock()) {
                // 检查用户余额
                $balance = FinanceHelper::getMemberBalance($memberId);
                if ($balance < $money) {
                    throw new \Exception(trans('shop-front.finance.balance_not_enough'));
                }
                // 插入负数的交费记录
                $about = '支付代理加盟费';
                $financeModel = new Finance();
                $finInfo = [
                    'site_id' => $siteId,
                    'member_id' => $memberId,
                    'type' => Constants::FinanceType_Normal,
                    'tradeno' => $orderId,
                    'pay_type' => Constants::PayType_Balance,
                    'out_type' => Constants::FinanceOutType_AgentInitial,
                    'order_id' => $orderId,
                    'order_type' => 0,
                    'operator' => '',
                    'terminal_type' => Constants::TerminalType_Unknown,
                    'money' => $money * -1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'about' => $about,
                    'status' => Constants::FinanceStatus_Active,
                    'active_at' => date('Y-m-d H:i:s'),
                ];
                $financeModel->add($finInfo);
                static::addAgentInitialMoney($siteId, $memberId, $orderId, $money, Constants::PayType_Balance);
                $locker->unlock();
            }
        } catch (\Exception $e) {
            $locker->unlock();
            throw $e;
        }
    }

    /**
     * 添加经销商加盟费入帐记录
     *
     * @param $siteId
     * @param $memberId
     * @param $orderId 订单ID，格式如 DealerInitial.date('YmdHis')
     * @param $money
     * @param $payType 支付方式，默认为手工
     * @return void
     */
    public static function addDealerInitialMoney($siteId, $memberId, $orderId, $money, $payType = Constants::PayType_Manual)
    {
        $isReal = Constants::FinanceIsReal_Yes;
        if ($payType == Constants::PayType_Balance) $isReal = Constants::FinanceIsReal_No;
        $finInfo = [
            'site_id' => $siteId,
            'member_id' => $memberId,
            'type' => Constants::FinanceType_DealerInitial,
            'pay_type' => $payType,
            'in_type' => Constants::FinanceInType_DealerInitial,
            'tradeno' => $orderId . randInt(1000),
            'order_id' => $orderId,
            'terminal_type' => Constants::TerminalType_Unknown,
            'money' => $money,
            'is_real' => $isReal,
            'created_at' => date('Y-m-d H:i:s'),
            'about' => '支付经销商加盟费',
            'status' => Constants::FinanceStatus_Active,
            'active_at' => date('Y-m-d H:i:s')
        ];
        if ($money > 0) {
            $financeObj = new Finance();
            $financeId = $financeObj->add($finInfo);
        }
        return $financeId;
    }

    /**
     * 使用余额支付经销商加盟费
     *
     * @param $siteId
     * @param $memberId
     * @param $orderId 订单ID，格式如 AgentInitial.date('YmdHis')
     * @param $money
     * @return void
     */
    public static function payDealerInitialMoneyWithBalance($siteId, $memberId, $orderId, $money)
    {
        $locker = new Locker('doBalancePay-' . Site::getCurrentSite()->getSiteId() . $memberId, 20);
        try {
            if ($locker->lock()) {
                // 检查用户余额
                $balance = FinanceHelper::getMemberBalance($memberId);
                if ($balance < $money) {
                    throw new \Exception(trans('shop-front.finance.balance_not_enough'));
                }
                // 插入负数的交费记录
                $about = '支付经销商加盟费';
                $financeModel = new Finance();
                $finInfo = [
                    'site_id' => $siteId,
                    'member_id' => $memberId,
                    'type' => Constants::FinanceType_Normal,
                    'tradeno' => $orderId,
                    'pay_type' => Constants::PayType_Balance,
                    'out_type' => Constants::FinanceOutType_DealerInitial,
                    'order_id' => $orderId,
                    'order_type' => 0,
                    'operator' => '',
                    'terminal_type' => Constants::TerminalType_Unknown,
                    'money' => $money * -1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'about' => $about,
                    'status' => Constants::FinanceStatus_Active,
                    'active_at' => date('Y-m-d H:i:s'),
                ];
                $financeModel->add($finInfo);
                static::addDealerInitialMoney($siteId, $memberId, $orderId, $money, Constants::PayType_Balance);
                $locker->unlock();
            }
        } catch (\Exception $e) {
            $locker->unlock();
            throw $e;
        }
    }

    /**
     * 添加分佣记录
     * @param $siteId 网站ID
     * @param $orderId 订单ID
     * @param array $commission 分佣表
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function addCommission($siteId, $orderId, array $commission)
    {
        // 先将此订单之前的分佣记录设置为无效
        FinanceModel::where(['type' => Constants::FinanceType_Commission, 'order_id' => $orderId])->delete();
        // 记录新的记录
        $noticeFinanceIDs = [];
        $financeObj = new Finance();
        $batchNumber = date('YmdHis');
        foreach ($commission as $item) {
            $finInfo = [
                'site_id' => $siteId,
                'member_id' => $item['member_id'],
                'type' => Constants::FinanceType_Commission,
                'pay_type' => Constants::PayType_Commission,
                'in_type' => Constants::FinanceInType_Commission,
                'tradeno' => 'COMMISSION_' . $batchNumber . '_' . genUuid(8),
                'order_id' => $orderId,
                'terminal_type' => Constants::TerminalType_Unknown,
                'money' => $item['money'],
                'created_at' => date('Y-m-d H:i:s'),
                'about' => '订单分佣，订单号：' . $orderId,
                'status' => Constants::FinanceStatus_Freeze
            ];
            if (is_array($item['chain'])) {
                $i = 1;
                foreach ($item['chain'] as $id) {
                    $finInfo['from_member' . $i] = $id;
                    $i++;
                }
            }
            if ($item['money'] > 0) {
                $financeId = $financeObj->add($finInfo);
                if ($financeId) {
                    $noticeFinanceIDs[] = $financeId;
                }
            }
        }
    }

    /**
     * 统计指定会员的历史余额
     * @param $memberId
     * @return mixed
     */
    public static function getMemberBalanceHistory($memberId)
    {
        return FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Active)
            ->where('money', '>', 0)
            ->where('type', Constants::FinanceType_Normal)
            ->whereIn('in_type', [
                Constants::FinanceInType_Recharge,
                Constants::FinanceInType_Manual,
                Constants::FinanceInType_Give
            ])->sum('money');
    }

    /**
     * 统计指定会员的冻结余额
     * @param $memberId
     * @return mixed
     */
    public static function getMemberBalanceBlocked($memberId)
    {
        // 支付冻结 和 提现金额
        $money = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Freeze)
            ->where('money', '<', 0)
            ->whereIn('out_type', [Constants::FinanceOutType_PayOrder, Constants::FinanceOutType_Refund, Constants::FinanceOutType_Withdraw])
            ->sum('money');

        return abs($money);
    }

    /**
     * 统计指定会员的余额状况
     * @param $memberId
     * @return array
     */
    public static function getBalanceInfo($memberId)
    {
        $balance = self::getMemberBalance($memberId); // 余额（包含赠金）
        $real = self::getMemberRealBalance($memberId); // 余额（不包含赠金）
        $history = self::getMemberBalanceHistory($memberId);
        $blocked = self::getMemberBalanceBlocked($memberId);
        return [
            'balance' => $balance,
            'real' => $real,
            'history' => $history,
            'blocked' => $blocked,
        ];
    }

    /**
     * 获取指定会员的真实财务余额(排除赠金)
     * @param $memberId
     * @return mixed
     */
    public static function getMemberRealBalance($memberId)
    {
        $in = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Active)
            ->where('money', '>', 0)
            ->where('type', Constants::FinanceType_Normal)
            ->sum('money');
        $out = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', '<>', Constants::FinanceStatus_Invalid)
            ->where('money', '<', 0)
            ->where('type', Constants::FinanceType_Normal)
            ->sum('money');
        // 所有入帐并且为生效的钱 - 所有出帐并且状态为生效或冻结的钱
        $balance = $in - abs($out);
        return $balance;
    }

    /**
     * 获取会员已提现的金额
     * @param $memberId
     * @return float|int
     */
    public static function getMemberBalanceWithdrawal($memberId)
    {
        $money = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Active)
            ->where('money', '<', 0)
            ->where('type', Constants::FinanceType_Normal)
            ->where('out_type', Constants::FinanceOutType_Withdraw)
            ->sum('money');
        return abs($money);
    }

    /**
     * 获取会员提现中的金额
     * @param $memberId
     * @return float|int
     */
    public static function getMemberBalanceWithdrawCheck($memberId)
    {
        $money = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Freeze)
            ->where('money', '<', 0)
            ->where('type', Constants::FinanceType_Normal)
            ->where('out_type', Constants::FinanceOutType_Withdraw)
            ->sum('money');
        return abs($money);
    }

    /**
     * 统计指定会员的赠金余额
     * @param $memberId
     * @return mixed
     */
    public static function getMemberBonusBalance($memberId)
    {
        $in = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', Constants::FinanceStatus_Active)
            ->where('money', '>', 0)
            ->where('type', Constants::FinanceType_Gift)
            ->sum('money');
        $out = FinanceModel::onWriteConnection()
            ->where('member_id', $memberId)
            ->where('status', '<>', Constants::FinanceStatus_Invalid)
            ->where('money', '<', 0)
            ->where('type', Constants::FinanceType_Gift)
            ->sum('money');
        // 所有入帐并且为生效的钱 - 所以出帐并且状态为生效或冻结的钱
        $balance = $in - abs($out);
        return $balance;
    }

    /**
     * 订单退款
     * @param $memberId 会员ID
     * @param $orderId 订单ID
     * @param $payType 原订单的支付方式
     * @param $transactionId 原支付订单时的交易号
     * @param $skuId 是退款的商品SKUID，如果是整张订单退款，请填写0
     * @param int $money 退款的金额，单位分
     * @param $reason 退款的原因
     * @param $afterSaleId
     * @return int
     * @throws \Exception
     */
    public static function refund($memberId, $orderId, $payType, $transactionId, $skuId, int $money, $reason, $afterSaleId)
    {
        $memberModel = MemberModel::find($memberId);
        $siteId = $memberModel->site_id;
        $financeId = 0;
        // 第一步，查找到原来的交易记录
        if (in_array($payType, Constants::getOnlinePayType())) { // 在线支付的情况
            $fin = FinanceModel::onWriteConnection()
                ->where('site_id', $siteId)
                ->where('member_id', $memberId)
                ->where('status', Constants::FinanceStatus_Active)
                ->where('type', Constants::FinanceType_Transfer)
                ->where('pay_type', $payType)
                ->where('in_type', Constants::FinanceInType_Trade)
                //->where('order_id', $orderId)
                ->where('tradeno', $transactionId)
                ->where('money', '>', 0)
                ->first();
        } else if (in_array($payType, [Constants::PayType_Balance])) { // 余额支付的情况
            $fin = FinanceModel::onWriteConnection()
                ->where('site_id', $siteId)
                ->where('member_id', $memberId)
                ->where('status', Constants::FinanceStatus_Active)
                ->where('type', Constants::FinanceType_Normal)
                ->where('pay_type', Constants::PayType_Balance)
                ->whereIn('out_type', [Constants::FinanceOutType_PayOrder, Constants::FinanceOutType_CloudStock_PayOrder])
                //->where('order_id', $orderId)
                ->where('tradeno', $transactionId)
                ->where('money', '<', 0)
                ->first();
        }
        if (!$fin) {
            throw new \Exception(trans('shop-admin.refund.ori_not_found'));
        }
        // 检测是否已经有相应的退款记录，避免重复退款
        $refundTradeNo = 'REFUND_' . $transactionId . '_' . $skuId.'_'.$afterSaleId;
        $locker = new Locker($refundTradeNo, 30);
        if ($locker->lock()) {
            $fincheck = FinanceModel::onWriteConnection()
                ->where('member_id', $memberId)
                ->where('status', Constants::FinanceStatus_Active)
                ->where('order_id', $orderId)
                ->where('tradeno', $refundTradeNo)
                ->first();
            if ($fincheck) {
                throw new \Exception(trans('shop-admin.refund.has_refund'));
            }
            $financeObj = new Finance();
            $balanceAbout = $afterSaleId
                ? trans('shop-admin.refund.to_balance') . ',' . ' 退款订单号:' . $afterSaleId . " " . $reason
                : trans('shop-admin.refund.to_balance') . $reason;
            // 余额
            $finBalance = [
                'site_id' => $fin->site_id,
                'member_id' => $memberId,
                'type' => Constants::FinanceType_Normal,
                'pay_type' => Constants::PayType_Balance,
                'in_type' => Constants::FinanceInType_Refund,
                'tradeno' => $refundTradeNo,
                'order_id' => $orderId,
                'operator' => '',
                'terminal_type' => $fin->terminal_type,
                'money' => $money,
                'created_at' => date('Y-m-d H:i:s'),
                'about' => $balanceAbout,
                'status' => Constants::FinanceStatus_Active,
                'active_at' => date('Y-m-d H:i:s'),
            ];
            $onlineAbout = $afterSaleId
                ? trans('shop-admin.refund.to_bank') . ',' . ' 退款订单号:' . $afterSaleId . " " . $reason
                : trans('shop-admin.refund.to_bank') . $reason;
            // 第三方金额
            $finOnline = [
                'site_id' => $fin->site_id,
                'member_id' => $memberId,
                'type' => Constants::FinanceType_Transfer,
                'is_real' => Constants::FinanceIsReal_Yes,
                'out_type' => Constants::FinanceOutType_Refund,
                'tradeno' => $refundTradeNo,
                'order_id' => $orderId,
                'operator' => '',
                'terminal_type' => $fin->terminal_type,
                'money' => $money * -1,
                'created_at' => date('Y-m-d H:i:s'),
                'about' => $onlineAbout,
                'status' => Constants::FinanceStatus_Active,
                'active_at' => date('Y-m-d H:i:s'),
            ];
            if ($fin->pay_type == Constants::PayType_Balance) {
                // 添加退到钱包的财务记录
                $financeId = $financeObj->add($finBalance);
                // $financeId = $financeObj->id;
            } else if ($fin->pay_type == Constants::PayType_Weixin) {
                $result = Payment::doWeixinPayRefund($transactionId, $refundTradeNo, $fin->money, $money, ['refund_desc' => $reason]);
                if ($result->success) {
                    // 添加退到原支付帐户财务记录
                    $finOnline['pay_type'] = Constants::PayType_Weixin;
                    $financeId = $financeObj->add($finOnline);
                    // $financeId = $financeObj->id;
                }
            } else if ($fin->pay_type == Constants::PayType_Alipay) {
                $result = Payment::doAlipayRefund($transactionId, $refundTradeNo, $fin->money, $money, ['refund_reason' => $reason]);
                if ($result->success) {
                    // 添加退到原支付帐户财务记录
                    $finOnline['pay_type'] = Constants::PayType_Alipay;
                    $financeId = $financeObj->add($finOnline);
                    // $financeId = $financeObj->id;
                }
            } else if ($fin->pay_type == Constants::PayType_TongLian) {
                $result = Payment::doTLPayRefund($transactionId, $orderId, $refundTradeNo, $fin->money, $money, ['refund_desc' => $reason]);
                if ($result->success) {
                    // 添加退到原支付帐户财务记录
                    $finOnline['pay_type'] = Constants::PayType_TongLian;
                    $financeId = $financeObj->add($finOnline);
                    // $financeId = $financeObj->id;
                }
            }
            $locker->unlock();
        } else {
            throw new \Exception("锁失败，请稍候再试");
        }
        return $financeId;
    }

    /**
     * 余额转现
     *
     * @param int $inMemberId 转入方会员ID
     * @param int $outMemberId 转出方会员ID
     * @param int $money 转现金额，单位分
     * @param $snapshot 快照
     * @return array 转入方和转出方的财务记录
     */
    public static function Give($inMemberId, $outMemberId, $money, $snapshot = null, $is_charge = false)
    {
        $locker = new Locker('Finance_Give_' . $outMemberId);
        if (!$locker->lock()) {
            throw new \Exception("加锁失败，请稍候重试");
        }
        $site = Site::getCurrentSite();
        $hasBalanceGiveFunction = $site->getSn()->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_DISTRIBUTION)
            || $site->getSn()->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_AGENT)
            || $site->getSn()->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_CLOUDSTOCK);
        $member = MemberModel::find($outMemberId);
        $hasBalanceGiveFunction &= $member->agent_level || $member->dealer_level || $member->is_distributor;
        // 判断转出方是否有余额转现的权限
        if (!$hasBalanceGiveFunction) {
            $locker->unlock();
            throw new \Exception("没有余额转现权限");
        }
        // 验证余额是否足够
        $maxPoint = FinanceHelper::getMemberBalance($outMemberId);
        if ($maxPoint < $money) {
            $locker->unlock();
            throw new \Exception("余额不足");
        }
        $financeObj = new Finance();
        $orderId = "GIVE_" . date('YmdHis') . randInt(1000);
        $outMemberInfo = MemberModel::find($outMemberId);
        $inMemberInfo = MemberModel::find($inMemberId);

        try {
            DB::beginTransaction();
            // 转出方记录
            $finIn = [
                'site_id' => $outMemberInfo->site_id,
                'member_id' => $outMemberId,
                'type' => Constants::FinanceType_Normal,
                'pay_type' => Constants::PayType_Balance,
                'is_real' => Constants::FinanceIsReal_No,
                'out_type' => Constants::FinanceOutType_Give,
                'order_id' => $orderId,
                'tradeno' => 'OUT_' . $orderId,
                'operator' => '',
                'terminal_type' => getCurrentTerminal(),
                'money' => $money * -1,
                'created_at' => date('Y-m-d H:i:s'),
                'about' => ($is_charge ? "给下级充值-" : "转现支出-转现给") . $inMemberInfo->nickname,
                'status' => Constants::FinanceStatus_Active,
                'active_at' => date('Y-m-d H:i:s'),
                'snapshot' => $snapshot
            ];
            $idIn = $financeObj->add($finIn);

            // 转入方记录
            $finOut = [
                'site_id' => $inMemberInfo->site_id,
                'member_id' => $inMemberId,
                'type' => Constants::FinanceType_Normal,
                'pay_type' => Constants::PayType_Balance,
                'is_real' => Constants::FinanceIsReal_No,
                'in_type' => Constants::FinanceInType_Give,
                'order_id' => $orderId,
                'tradeno' => 'IN_' . $orderId,
                'operator' => '',
                'terminal_type' => getCurrentTerminal(),
                'money' => $money,
                'created_at' => date('Y-m-d H:i:s'),
                'about' => ($is_charge ? "向上级充值-" : "转现收入-来自于") . $outMemberInfo->nickname,
                'status' => Constants::FinanceStatus_Active,
                'active_at' => date('Y-m-d H:i:s'),
                'snapshot' => $snapshot
            ];
            $idOut = $financeObj->add($finOut);
            DB::commit();
            $locker->unlock();
        } catch (\Exception $ex) {
            DB::rollBack();
            $locker->unlock();
            throw $ex;
        }
        // 相关分销商升级
        UpgradeDistributionLevelJob::dispatch($inMemberId, ['money' => $money]);
        //相关代理升级
        UpgradeAgentLevelJob::dispatch($inMemberId, ['money' => $money]);
        return [$idIn, $idOut];
    }

    /**
     * 前台会员提现（包括余额提现和佣金提现）
     * @param $memberId 会员ID
     * @param $moneyReal 实际到账金额（单位：分）
     * @param $serviceFee 手续费（单位：分）
     * @param $outType 财务方向支出的类型
     * @param int $payType 支付方式
     * @param int $type 财务类型
     * @param string $about 备注
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    /* 此方法已废弃，提现的过程统一放在 app\Modules\ModuleShop\Libs\Finance\Withdraw\AbstractWithdraw.php 中处理
    public static function withdrawAddFinance($memberId, $moneyReal, $serviceFee, $outType, $payType = Constants::PayType_Unknow, $type = Constants::FinanceType_Transfer, $about = '')
    {
        if ($type == Constants::FinanceType_Transfer) {
            throw new \Exception('error finance type');
        }
        $moneyReal = abs($moneyReal);
        $serviceFee = abs($serviceFee);
        $moneyTotal = $moneyReal + $serviceFee;

        // 检测是否已经有相应的退款记录，避免重复退款
        $lockerId = 'checkBalance_' . $memberId;
        $locker = new Locker($lockerId, 120);
        try {
            if ($locker->lock()) {
                // 检查金额额度
                if (!self::checkBalance($memberId, $moneyTotal, $type)) {
                    throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('shop-front.finance.balance_not_enough'));
                }
                $member = new Member($memberId);
                if ($payType == Constants::PayType_Weixin) {
                    // 检查微信信息
                    $openid = $member->getOfficialAccountOpenId();
                    if (!$openid) {
                        throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('shop-front.wx_nobind'));
                    }
                } else if ($payType == Constants::PayType_Alipay) {
                    // 检查支付宝信息
                    $openid = $member->getAlipayUserId();
                    $alipayAccount = $member->getAlipayAccount();
                    if (!$openid && !$alipayAccount) {
                        throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('shop-front.alipay_nobind'));
                    }
                }
                //处理提现快照
                $snapshot = (new MemberWithdrawAccount($memberId))->getMemberWithdrawAccount($payType);
                // 插入数据
                $finInfo = [
                    'site_id' => $member->getModel()->site_id,
                    'member_id' => $memberId,
                    'type' => $type,
                    'is_real' => $outType == Constants::FinanceOutType_Withdraw ? Constants::FinanceIsReal_Yes : Constants::FinanceIsReal_No,
                    'out_type' => $outType,
                    'operator' => '',
                    'terminal_type' => getCurrentTerminal(),
                    'money' => $moneyTotal * -1,
                    'money_fee' => $serviceFee * -1,
                    'money_real' => $moneyReal * -1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'active_at' => date('Y-m-d H:i:s'),
                    'about' => $about,
                    'status' => Constants::FinanceStatus_Freeze,
                    'pay_type' => $payType,
                    'tradeno' => 'WITHDRAW_' . generateOrderId(),
                    'snapshot' => $snapshot ? json_encode($snapshot) : ''
                ];
                DB::beginTransaction();
                $financeObj = new Finance();
                $financeId = $financeObj->add($finInfo);
                if ($financeId) {
                    // 处理佣金/货款 转余额直接生效
                    $toBalance = [Constants::FinanceOutType_CloudStockGoodsToBalance,Constants::FinanceOutType_SupplierToBalance, Constants::FinanceOutType_CommissionToBalance, Constants::FinanceOutType_AreaAgentCommissionToBalance];
                    if (in_array($outType, $toBalance)) {
                        self::Withdraw($memberId, $financeId, $about? $about : '提现到余额，直接生效');
                    }
                }
                DB::commit();
                if (!in_array($outType, $toBalance) && $financeId) {
                    // 申请提现通知 余额不发送通知
                    MessageNoticeHelper::sendMessageWithdrawApply(FinanceModel::find($financeId));
                }
            } else {
                throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('base-front.locker.lock_fail'));
            }

        } finally {
            $locker->unlock();
        }
    }*/

    /**
     * 会员提现，目前只支付提现到微信和支付宝
     * @param $memberId 会员ID
     * @param $financeId 财务记录id
     * @param string $about 备注
     * @return array
     * @throws \Exception
     */
    public static function Withdraw($memberId, $financeId, $about = '')
    {
        $finance = new Finance($financeId);
        $financeObj = $finance->getModel();
        $balanceId = 0; // 提现到余额的财务id
        if (!$financeObj) {
            throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('shop-front.finance.no_finance'));
        }
        $lockid = 'withdraw_' . $memberId;
        $locker = new Locker($lockid, 30);
        try {
            if ($locker->lock()) {
                // 冻结的情况下才能提现
                if (intval($financeObj->status) != constants::FinanceStatus_Freeze) {
                    throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('shop-front.withdraw.can_not_withdraw'));
                }
                // 因为已经生成冻结的财务数据，所以无需再检查额度
                $member = new Member($memberId);
                if (!$member->checkExist() || intval($financeObj->member_id) != $member->getModelId()) {
                    throw new \Exception(trans('shop-front.withdraw.fail') . ':' . 'member id not the same');
                }
                // 提现到余额的类型
                $toBalance = [
                    Constants::PayType_Commission,
                    Constants::PayType_CloudStockGoods,
                    Constants::PayType_Supplier
                ];
                // 处理第三方提现
                $money_real = abs($financeObj->money_real);
                if (in_array(intval($financeObj->pay_type), Constants::getOnlinePayType())) {
                    if ($financeObj->pay_type == Constants::PayType_Weixin) {
                        $openid = $member->getOfficialAccountOpenId();
                        if (!$openid) {
                            throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('shop-front.wx_nobind'));
                        }
                    } else if ($financeObj->pay_type == Constants::PayType_Alipay) {
                        $openid = $member->getAlipayUserId();
                        $alipayAccount = $member->getAlipayAccount();
                        if (!$openid && !$alipayAccount) {
                            throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('shop-front.alipay_nobind'));
                        }
                    } else {
                        throw new \Exception(trans('shop-front.withdraw.fail') . ':' . 'Error pay type');
                    }

                    if ($financeObj->pay_type == Constants::PayType_Weixin)
                        $res = Payment::doWeixinPayToUser($memberId, $openid, $money_real, $about);
                    else if ($financeObj->pay_type == Constants::PayType_Alipay) {
                        if ($openid) {
                            $res = Payment::doAlipayToUser($memberId, $openid, $money_real, $about);
                        } else {
                            $res = Payment::doAlipayToUserByAccount($memberId, $alipayAccount, $money_real, $about);
                        }
                    }

                    if ($res->success) {
                        // 支付成功，更改订单状态
                        $financeObj->tradeno = $res->tradeno;
                        $financeObj->order_id = $res->orderid;
                        $financeObj->active_at = date('Y-m-d H:i:s');
                        $financeObj->about = $about;
                        $financeObj->status = constants::FinanceStatus_Active;
                        $financeObj->save();
                    }

                } else if (in_array(intval($financeObj->pay_type), Constants::getOfflinePayType())) {
                    //处理提现提到线下的 更新原数据状态
                    $financeObj->about = $about;
                    $financeObj->status = constants::FinanceStatus_Active;
                    $financeObj->active_at = date('Y-m-d H:i:s');
                    $financeObj->save();
                } else if (in_array(intval($financeObj->pay_type), $toBalance)) {
                    $financePayType = intval($financeObj->pay_type);
                    $inType = Constants::FinanceInType_CommissionToBalance;
                    if (intval($financeObj->pay_type) == Constants::PayType_CloudStockGoods) {
                        $inType = Constants::FinanceInType_CloudStockGoodsToBalance;
                    }
                    if (intval($financeObj->type) == Constants::FinanceType_AreaAgentCommission) {
                        $inType = Constants::FinanceInType_AreaAgentCommissionToBalance;
                    }
                    // 处理佣金或货款提现到余额
                    $balanceData = FinanceModel::query()
                        ->where('site_id', $member->getSiteId())
                        ->where('member_id', $member->getModelId())
                        ->where('tradeno', 'RELATE_' . $financeObj->tradeno)
                        ->first();
                    if (!$balanceData) {
                        // 插入余额数据
                        $financeBalance = new Finance();
                        $balanceId = $financeBalance->add([
                            'site_id' => $member->getSiteId(),
                            'member_id' => $member->getModelId(),
                            'tradeno' => 'RELATE_' . $financeObj->tradeno,
                            'status' => constants::FinanceStatus_Active,
                            'about' => $about,
                            'active_at' => date('Y-m-d H:i:s'),
                            'type' => Constants::FinanceType_Normal,
                            'pay_type' => $financePayType,
                            'in_type' => $inType,
                            'terminal_type' => $financeObj->terminal_type,
                            'money' => $money_real,
                            'money_fee' => 0,
                            'money_real' => $money_real
                        ]);
                    }
                    // 更新原数据状态
                    $financeObj->about = $about;
                    $financeObj->status = constants::FinanceStatus_Active;
                    $financeObj->active_at = date('Y-m-d H:i:s');
                    $financeObj->save();
                }
                if ($financeObj->type == Constants::FinanceType_CloudStock) {
                    // 云仓收入提现到余额或者外部的消息通知
                    if ($financeObj->out_type != Constants::FinanceOutType_CloudStockGoodsToBalance) {
                        MessageNotice::dispatch(Constants::MessageType_CloudStock_Withdraw_Commission, $financeObj);
                    }
                }
                return [
                    'balance_id' => $balanceId
                ];
            }
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $locker->unlock();
        }
    }

    /**
     * 获取出入账类型描述
     * @param $inType
     * @param $outType
     * @return string
     */
    public static function getFinanceInOutTypeText($inType, $outType)
    {
        if (intval($inType) > 0) {
            return Constants::getFinanceInTypeText(intval($inType));
        } else if (intval($outType) > 0) {
            return Constants::getFinanceOutTypeText(intval($outType));
        } else {
            return '';
        }
    }

    /**
     * 获取出入账类型描述(用于后台财务管理)
     * @param $inType
     * @param $outType
     * @param $type
     * @return string
     */
    public static function getFinanceAdminInOutTypeText($inType, $outType, $type, $orderType)
    {
        if (($outType == Constants::FinanceOutType_PayOrder || $inType == Constants::FinanceInType_Trade) && $orderType == Constants::FinanceOrderType_Normal) {
            return '订单支付';
        } else if ($inType == Constants::FinanceInType_Recharge) {
            return '余额充值';
        } else if ($inType == Constants::FinanceInType_Refund || $outType == Constants::FinanceOutType_Refund) {
            return '订单退款';
        } else if ($inType == Constants::FinanceInType_Manual && $type == Constants::FinanceType_Normal) {
            return '手工充值';
        } else if ($inType == Constants::FinanceInType_CloudStockGoods && $type == Constants::FinanceType_CloudStock) {
            return '云仓货款';
        } else if ($inType == Constants::FinanceInType_AgentInitial && $type == Constants::FinanceType_AgentInitial) {
            return '代理加盟费';
        } else if ($inType == Constants::FinanceInType_DealerInitial && $type == Constants::FinanceType_DealerInitial) {
            return '经销商加盟费';
        } else if ($orderType == 2 && ($type == Constants::FinanceType_CloudStockPurchase || $outType == Constants::FinanceOutType_CloudStock_PayOrder || $inType == Constants::FinanceInType_Trade)) {
            return '云仓进货支付';
        } else if ($inType == Constants::FinanceInType_Bonus && $type == Constants::FinanceType_Normal) {
            return '充值赠送';
        } else if ($inType == Constants::FinanceInType_Give && $type == Constants::FinanceType_Normal) {
            return '余额转现收入';
        } else if ($outType == Constants::FinanceOutType_CommissionToBalance && $type == Constants::FinanceType_Commission) {
            return '佣金提现(至余额)';
        } else if ($outType == Constants::FinanceOutType_CommissionToBalance && $type == Constants::FinanceType_AgentCommission) {
            return '分红提现(至余额)';
        } else if ($outType == Constants::FinanceOutType_Withdraw && $type == Constants::FinanceType_Commission) {
            return '佣金提现(至第三方)';
        } else if ($outType == Constants::FinanceOutType_Withdraw && $type == Constants::FinanceType_AgentCommission) {
            return '分红提现(至第三方)';
        } else if ($outType == Constants::FinanceOutType_Withdraw && $type == Constants::FinanceType_Normal) {
            return '余额提现(至第三方)';
        } else if ($outType == Constants::FinanceOutType_Manual && $type == Constants::FinanceType_Normal) {
            return '手工扣减';
        } else if ($outType == Constants::FinanceOutType_CloudStockGoodsToBalance && $type == Constants::FinanceType_CloudStock) {
            return '经销商资金提现(至余额）';
        } else if ($outType == Constants::FinanceOutType_Withdraw && $type == Constants::FinanceType_CloudStock) {
            return '经销商资金提现(第三方）';
        } else if ($outType == Constants::FinanceOutType_AgentInitial && $type == Constants::FinanceType_Normal) {
            return '支付代理加盟费';
        } else if ($outType == Constants::FinanceOutType_DealerInitial && $type == Constants::FinanceType_Normal) {
            return '支付经销商加盟费';
        } else if ($outType == Constants::FinanceOutType_Give && $type == Constants::FinanceType_Normal) {
            return '余额转现支出';
        } else if ($orderType == Constants::FinanceOrderType_CloudStock_TakeDelivery) {
            return '云仓提货运费';
        } else if (($outType == Constants::FinanceOutType_AreaAgentCommissionToBalance || $outType == Constants::FinanceOutType_CommissionToBalance) && $type == Constants::FinanceType_AreaAgentCommission) {
            return '区代返佣提现(至余额)';
        } else if ($outType == Constants::FinanceOutType_Withdraw && $type == Constants::FinanceType_AreaAgentCommission) {
            return '区代返佣提现(至第三方)';
        } else if ($outType == Constants::FinanceOutType_SupplierToBalance && $type == Constants::FinanceType_Supplier) {
            return '供应商货款提现(至余额)';
        } else if ($outType == Constants::FinanceOutType_Withdraw && $type == Constants::FinanceType_Supplier) {
            return '供应商货款提现(至第三方)';
        } else {
            return '';
        }
    }

    /**
     * 获取出入账类型描述（前台余额文案）
     * @param $inType
     * @param $outType
     * @param $payType
     * @param string $tradeNo
     * @param array $params
     * @return mixed|string
     */
    public static function getFinanceInOutTypeForBalance($inType, $outType, $payType, $tradeNo = '', $params = [])
    {
        $text = '';
        if (intval($inType) > 0) {
            $text = self::getFinanceInTypeTextForBalance(intval($inType), intval($payType), $tradeNo, $params);
        } else if (intval($outType) > 0) {
            $text = self::getFinanceOutTypeTextForBalance(intval($outType), intval($payType), $tradeNo, $params);
        }
        $text = str_replace('微信', '微信钱包', $text);
        return $text;
    }

    /**
     * 返回出账类型的文本表示形式（前台余额文案）
     * @param int $outType
     * @param int $payType
     * @param string $tradeNo
     * @param array $params
     * @return string
     */
    public static function getFinanceOutTypeTextForBalance(int $outType, int $payType, $tradeNo = '', $params = [])
    {
        switch ($outType) {
            case Constants::FinanceOutType_PayOrder:
                return '订单支付';
            case Constants::FinanceOutType_CloudStock_PayOrder:
                return '云仓进货支付';
            case Constants::FinanceOutType_ServiceFee:
                return '手续费';
            case Constants::FinanceOutType_Reverse:
                return '冲帐';
            case Constants::FinanceOutType_Refund:
                return '退款';
            case Constants::FinanceOutType_Withdraw:
                return trans('shop-front.diy_word.balance') . '提现';
            case Constants::FinanceOutType_CommissionToBalance:
                $result = '提现到账户';
                if (stripos($tradeNo, 'RELATE_') === 0) {
                    $financeModel = FinanceModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('tradeno', substr($tradeNo, 7))->first();
                    if ($financeModel) {
                        if (intval($financeModel->type) == Constants::FinanceType_Commission) {
                            $result = trans('shop-front.diy_word.commission') . $result;
                        } else if (intval($financeModel->type) == Constants::FinanceType_AgentCommission) {
                            $result = trans('shop-front.diy_word.agent_reward') . $result;
                        }
                    }
                }
                return $result;
            case Constants::FinanceOutType_Manual:
                return '手工扣减';
            case Constants::FinanceOutType_AgentInitial:
                return '代理加盟费';
            case Constants::FinanceOutType_DealerInitial:
                return '经销商加盟费';
            case Constants::FinanceOutType_CloudStock_TakeDeliver_Fright:
                return '云仓提货运费';
            default:
                return '未知';
        }
    }

    /**
     * 返回入账类型的文本表示形式（前台余额文案）
     * @param int $inType
     * @param int $payType
     * @param string $tradeNo
     * @param array $params
     * @return array|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public static function getFinanceInTypeTextForBalance(int $inType, int $payType, $tradeNo = '', $params = [])
    {
        switch ($inType) {
            case Constants::FinanceInType_Recharge:
                return '账户充值';
            case Constants::FinanceInType_Reverse:
                return '冲账';
            case Constants::FinanceInType_Bonus:
                return '充值赠送';
            case Constants::FinanceInType_Refund:
                if ($params && strpos($params['about'], '未成团订单') !== false) {
                    return '拼单失败退款';
                }
                return '售后退款成功';
            case Constants::FinanceInType_CommissionToBalance:
                $result = '提现到账户';
                if (stripos($tradeNo, 'RELATE_') === 0) {
                    $financeModel = FinanceModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('tradeno', substr($tradeNo, 7))->first();
                    if ($financeModel) {
                        if (intval($financeModel->type) == Constants::FinanceType_Commission) {
                            $result = trans('shop-front.diy_word.commission') . $result;
                        } else if (intval($financeModel->type) == Constants::FinanceType_AgentCommission) {
                            $result = trans('shop-front.diy_word.agent_reward') . $result;
                        }
                    }
                }
                return $result;
            case Constants::FinanceInType_Commission:
                return trans('shop-front.diy_word.commission');
            case Constants::FinanceInType_Manual:
                return '手工充值';
            case Constants::FinanceInType_CloudStockGoodsToBalance:
                return '经销商资金提现到账户';
            case Constants::FinanceInType_AreaAgentCommissionToBalance:
                return '区域返佣提现到账户';
            default:
                return '未知';
        }
    }

    /**
     * @param $isReal
     * @param $money
     * @return int
     */
    public static function getFinanceAccountType($isReal, $money)
    {
        if ($isReal) {
            if (floatval($money) >= 0) {
                // 入账
                return Constants::FinanceAccountType_In;
            } else {
                // 出账
                return Constants::FinanceAccountType_Out;
            }
        }
        // 平账
        return Constants::FinanceAccountType_Flat;
    }

    /** 获取查找某会员的下级来源的 SQL
     * @param $memberIdOrStr
     * @param $maxLevel
     * @param int $startLevel
     * @param string $table
     * @return string
     */
    public static function getSubUserSql($memberIdOrStr, $maxLevel, $startLevel = 1, $table = '')
    {
        $max = Constants::MaxInviteLevel;
        if (!$maxLevel || $maxLevel > $max) {
            $maxLevel = $max;
        }
        $wheres = [];
        for ($i = $startLevel; $i <= $maxLevel; $i++) {
            $wheres[] = ($table ? $table . "." : "") . "from_member" . $i . " = " . $memberIdOrStr;
        }
        $str = implode(" or ", $wheres);
        return $str;
    }

    /**
     * 检查额度是否足够
     * @param $memberId 会员id
     * @param $moneyTotal 要扣除的金额，单位：分
     * @param $financeType 财务类型
     * @return bool
     */
    public static function checkBalance($memberId, $moneyTotal, $financeType)
    {
        $moneyTotal = abs($moneyTotal);
        $financeType = intval($financeType);
        $balance = 0;
        if ($financeType == Constants::FinanceType_Normal) {
            // 获取用户余额
            $balance = FinanceHelper::getMemberRealBalance($memberId);
        } else if (in_array($financeType, [Constants::FinanceType_Commission, Constants::FinanceType_AgentCommission, Constants::FinanceType_AreaAgentCommission])) {
            // 获取用户佣金
            $balance = FinanceHelper::getMemberCommissionBalance($memberId, $financeType);
        } else if ($financeType == Constants::FinanceType_CloudStock) {
            // 获取用户经销商余额
            $balance = FinanceHelper::getCloudStockBalance($memberId);
        } else if ($financeType == Constants::FinanceType_Supplier) {
            // 获取供应商余额
            $balance = FinanceHelper::getSupplierBalance($memberId);
        }
        return $balance >= $moneyTotal;
    }

    /**
     *  根据订单状态处理分销财务数据状态
     * @param $orderId
     * @return array|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function commissionChangeStatusByOrder($orderId)
    {
        if (!$orderId) return false;
        $orderModel = OrderModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('id', $orderId)->first();
        if (!$orderModel) return false;
        //避免发放两次，如果此字段为0是不需要佣金结算，若为2，3则订单是已经结算过的。
        if ($orderModel->has_commission != 1) {
            return false;
        }
        //判断假如后台佣金计算条件为付款后，立马放发佣金
        if ((new DistributionSetting())->getSettingModel()->calc_commission_valid_condition == 0) {
            // 付款后 并且没有售后成功 立马发放佣金
            if (in_array(intval($orderModel->status), [LibsConstants::OrderStatus_OrderPay, LibsConstants::OrderStatus_OrderSend, LibsConstants::OrderStatus_OrderSuccess, LibsConstants::OrderStatus_OrderFinished, LibsConstants::OrderStatus_OrderReceive])) {
                $commissionCanGrant = true;
            }
        } else {
            // 订单在非结束或完成状态时，不应该执行任何操作
            if (!in_array(intval($orderModel->status), [LibsConstants::OrderStatus_OrderFinished, LibsConstants::OrderStatus_OrderClosed])) {
                return false;
            }
            // 交易完成 并且没有售后成功
            if (intval($orderModel->status) == LibsConstants::OrderStatus_OrderFinished) {
                $commissionCanGrant = true;
            }
            // 交易完成 并且没有售后成功
            if (intval($orderModel->status) == LibsConstants::OrderStatus_OrderClosed) {
                $commissionCanGrant = false;
            }
        }
        // 处理分销财务数据状态
        if ($commissionCanGrant) {
            $financeQuery = FinanceModel::query()
                ->where('type', Constants::FinanceType_Commission)
                ->where('status', Constants::FinanceStatus_Freeze)
                ->where('in_type', Constants::FinanceInType_Commission)
                ->where('order_id', $orderId)
                ->where('money', '>=', '0');
            $financeList = $financeQuery->get();
            $financeQuery->update([
                'status' => Constants::FinanceStatus_Active,
                'active_at' => date('Y-m-d H:i:s'),
            ]);
            $financeIds = $financeList->pluck('id')->all();
            //佣金放发成功，需要把此字段改为2
            $orderModel->has_commission = 2;
            $orderModel->save();
            // 返回修改的记录
            return FinanceModel::query()
                ->where('type', Constants::FinanceType_Commission)
                ->where('order_id', $orderId)
                ->whereIn('id', $financeIds)
                ->get();
        } else {
            FinanceModel::query()->where([
                    'type' => Constants::FinanceType_Commission,
                    'status' => Constants::FinanceStatus_Freeze,
                    'order_id' => $orderId]
            )->where('money', '>=', '0')->update([
                'status' => Constants::FinanceStatus_Invalid,
                'invalid_at' => date('Y-m-d H:i:s')
            ]);
            $orderModel->has_commission = 3;
            $orderModel->save();
            return false;
        }
    }


    /**
     *  根据订单ID获取代理财务相关记录
     * @param $orderId
     * @return array|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getAgentRewardFinanceByOrderId($orderId){
        //返回经过汇总后的佣金记录
        $financeList = [];
        $query = FinanceModel::query()->where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'sub_type' => Constants::FinanceSubType_AgentCommission_Order, 'order_id' => $orderId]);
        $query = $query->select('member_id', 'order_id', 'type', 'sub_type', 'status', 'active_at','created_at')->selectRaw('sum(money) as money')->groupBy('member_id', 'order_id');
        $list = $query->get();
        if ($list) {
            $financeList['normal'] = $list;
        }
        $query = FinanceModel::query()->where(['type' => \YZ\Core\Constants::FinanceType_AgentCommission, 'sub_type' => Constants::FinanceSubType_AgentCommission_SaleReward, 'order_id' => $orderId]);
        $query = $query->select('member_id', 'order_id', 'type', 'sub_type', 'status', 'active_at','created_at')->selectRaw('sum(money) as money')->groupBy('member_id', 'order_id');
        $list = $query->get();
        if ($list) {
            $financeList['salereward'] = $list;
        }
        return $financeList;
    }

    /**
     *  根据订单ID获取分销财务相关记录
     * @param $orderId
     * @return array|\Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getCommissionFinanceByOrderId($orderId){
        $financeQuery = FinanceModel::query()
            ->where('type', Constants::FinanceType_Commission)
            ->where('in_type', Constants::FinanceInType_Commission)
            ->where('order_id', $orderId)
            ->where('money', '>=', '0');
        $financeList = $financeQuery->get();
        return $financeList;
    }
}
