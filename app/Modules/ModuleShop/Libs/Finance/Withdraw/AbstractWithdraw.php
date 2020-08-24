<?php
namespace App\Modules\ModuleShop\Libs\Finance\Withdraw;

use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberWithdrawAccount;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Finance\Finance;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Locker\Locker;
use YZ\Core\Model\FinanceModel;

abstract class AbstractWithdraw implements IWithdraw
{
    /**
     * 初始化模型
     * @return array
     */
    abstract public function __construct();

    /**
     * 获取可提现的余额
     * @param $financeType 财务类型
     * @param $memberId 会员ID
     * @return mixed;
     */
    abstract function getAvailableBalance($financeType, $memberId);

    /**
     * 获取提现配置，包括提现方式 金额限制 时间限制这些，目前几个地方的设置字段大部分相同，所以暂时不需要做特殊处理
     * 如果后面差异比较大的时候，要注意要将些接口返回的数据进行一下统一封装，以便统一返回的数据格式
     * @return mixed
     */
    abstract public function getConfig();

    /**
     * 修改提现配置
     * @param array $info，设置信息，对应 Model 的字段信息
     * @return null
     */
    abstract public function editConfig(array $info);

    /**
     * 获取提现方式的配置，直接读取相应字段，不进行检测
     * @return array
     */
    abstract function getWithdrawWayConfig();

    /**
     * 返回经过数据格式封装后的配置
     * @param array $info 原始数据库里记录的信息
     */
    protected function getParsedConfig(array $data){
        $data['withdraw_date'] = json_decode($data['withdraw_date'],true);
        $data['withdraw_way'] = $this->getWithdrawWay();
        return $data;
    }

    /**
     * 获取可用的提现方式
     * @return array
     */
    public function getWithdrawWay() : WithdrawWay
    {
        $info = $this->getWithdrawWayConfig();
        $way = new WithdrawWay();
        if(is_array($info)){
            $payData = (new PayConfig())->getInfo();
            if($payData->wxpay_mchid) $way->wxpay = intval($info['wxpay']) === 1 ? 1 : 0;
            if($payData->alipay_appid) $way->alipay = intval($info['alipay']) === 1 ? 1 : 0;
            $way->wxQrcode = intval($info['wx_qrcode']) === 1 ? 1 : 0;
            $way->alipayQrcode = intval($info['alipay_qrcode']) === 1 ? 1 : 0;
            $way->alipayAccount = intval($info['alipay_account']) === 1 ? 1 : 0;
            $way->bankAccount = intval($info['bank_account']) === 1 ? 1 : 0;
            $way->balance = intval($info['balance']) === 1 ? 1 : 0;
        }
        return $way;
    }

    /**
     * 提现
     * @
     * @return string
     */
    public function withdraw($financeType, $payType, $money, $memberId)
    {
        // 检测财务类型是否符合
        $this->checkFinanceType($financeType);
        // 检测提现时间
        $this->checkWithdrawDate();
        // 检测提现的金额
        $this->checkWithdrawMoney($money, $financeType, $memberId);
        // 检测可用提现方式
        $this->checkAccessPayType($payType, $financeType);
        // 获取outType
        $outType = $this->getOutType($payType, $financeType);
        // 获取备注
        $about = $this->getAbout($payType, $financeType);
        if (in_array($payType, [Constants::PayType_Balance, Constants::PayType_Commission, Constants::PayType_CloudStockGoods, Constants::PayType_Supplier])) {
            // 余额提现
            $this->balanceWithdraw($memberId, $money,  $outType, $payType, $financeType, $about);
        } else if (in_array($payType, array_merge(Constants::getOfflinePayType(), Constants::getOnlinePayType()))) {
            // 检测提现金额是否符合最大金额最小金额
            $this->checkMinMaxWithdrawMoney($money);
            // 计算提现手续费
            $moneyFee = $this->calWithdrawMoneyFee($money);
            // 计算提现真实金额 提现真实金额 = 提现金额 - 提现手续费
            $moneyReal = $this->calWithdrawMoney($money, $moneyFee);
            // 第三方提现
            $this->thirdWithdraw($memberId, $moneyReal, $moneyFee, $outType, $payType, $financeType, $about);
        }
    }

    /**
     * 余额提现
     * @return array
     */
    public function balanceWithdraw($memberId, $money, $outType, $payType, $financeType, $about)
    {
        if ($financeType == Constants::FinanceType_Transfer) {
            throw new \Exception('error finance type');
        }
        $moneyReal = abs($money);
        $moneyTotal = $moneyReal;

        // 检测是否已经有相应的退款记录，避免重复退款
        $lockerId = 'checkBalance_' . $memberId;
        $locker = new Locker($lockerId, 120);
        try {
            if ($locker->lock()) {
                $member = new Member($memberId);
                // 验证金额是否足够提现
                if ($this->getAvailableBalance($financeType, $memberId) < $money) {
                    throw new \Exception("提现失败，账户余额不足");
                }
                //处理提现快照
                $snapshot = (new MemberWithdrawAccount($memberId))->getMemberWithdrawAccount($payType);
                // 插入数据
                $finInfo = [
                    'site_id' => $member->getModel()->site_id,
                    'member_id' => $memberId,
                    'type' => $financeType,
                    'is_real' => Constants::FinanceIsReal_No,
                    'out_type' => $outType,
                    'operator' => '',
                    'terminal_type' => getCurrentTerminal(),
                    'money' => $moneyTotal * -1,
                    'money_fee' => 0,
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
                $finance = $financeObj->add($finInfo);
                if ($finance) {
                    FinanceHelper::Withdraw($memberId, $finance, $about ? $about : '提现到余额，直接生效');
                }
                DB::commit();
            } else {
                throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('base-front.locker.lock_fail'));
            }

        } finally {
            $locker->unlock();
        }
    }

    /**
     * 第三方提现
     * @return array
     */
    public function thirdWithdraw($memberId, $moneyReal, $moneyFee, $outType, $payType, $financeType, $about)
    {
        if ($financeType == Constants::FinanceType_Transfer) {
            throw new \Exception('error finance type');
        }
        $moneyReal = abs($moneyReal);
        $moneyFee = abs($moneyFee);
        $moneyTotal = $moneyReal + $moneyFee;

        // 检测是否已经有相应的退款记录，避免重复退款
        $lockerId = 'checkBalance_' . $memberId;
        $locker = new Locker($lockerId, 120);
        try {
            if ($locker->lock()) {
                // 验证金额是否足够提现
                if ($this->getAvailableBalance($financeType, $memberId) < $moneyTotal) {
                    throw new \Exception("提现失败，账户余额不足");
                }
                $member = new \App\Modules\ModuleShop\Libs\Member\Member($memberId);
                if ($payType == Constants::PayType_Weixin) {
                    // 检查微信信息
                    $openid = $member->getWxOpenId();
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
                    'type' => $financeType,
                    'is_real' => Constants::FinanceIsReal_Yes,
                    'out_type' => $outType,
                    'operator' => '',
                    'terminal_type' => getCurrentTerminal(),
                    'money' => $moneyTotal * -1,
                    'money_fee' => $moneyFee * -1,
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
                $finance = $financeObj->add($finInfo);
                DB::commit();
                if ($finance) {
                    MessageNoticeHelper::sendMessageWithdrawApply(FinanceModel::find($finance));
                }
            } else {
                throw new \Exception(trans('shop-front.withdraw.fail') . ':' . trans('base-front.locker.lock_fail'));
            }

        } finally {
            $locker->unlock();
        }
    }


    /**
     * 检测提现时间
     * @param $config
     * @return array|bool|mixed
     */
    public function checkWithdrawDate()
    {
        $withdrawConfig = $this->getConfig();
        if ($withdrawConfig) {
            // 查找不到提现时间设置 认为是默认的任意时间
            $withdrawDate = $withdrawConfig['withdraw_date'] ? $withdrawConfig['withdraw_date'] : ['date' => 0];
            // 任意时间
            if ($withdrawDate['date'] == 0) {
                return true;
            }
            // 特定时间
            // 每周
            if ($withdrawDate['date_type'] == 0) {
                $day = date('w'); // 当前星期几 周日为0
            } else {
                // 每月
                $day = date('j'); // 当前是这个月的第几天
                // 如果有选择每月最后一天 把当前月的最后一天插入到最后
                if (in_array(-1, $withdrawDate['date_days'])) {
                    array_push($withdrawDate['date_days'], date('t'));
                }
            }
            if (!in_array($day, $withdrawDate['date_days'])) {
                throw new \Exception("当前时间不在规定提现时间内");
            }
        }
        return true;
    }

    /**
     * 检测提现的金额
     * @param $money 金额
     * @param $financeType
     * @param $memberId
     * @return bool
     */
    private function checkWithdrawMoney($money)
    {
        if ($money <= 0) {
            throw new \Exception("提现金额必须大于0");
        }
    }

    /**
     * 检测财务类型是否符合
     * @param $money 金额
     * @param $financeType
     * @param $memberId
     * @return bool
     */
    private function checkFinanceType($financeType)
    {
        $checkArray = [
            Constants::FinanceType_Normal,
            Constants::FinanceType_Commission,
            Constants::FinanceType_AgentCommission,
            Constants::FinanceType_AreaAgentCommission,
            Constants::FinanceType_CloudStock,
            Constants::FinanceType_Supplier
        ];
        // 只接受 余额、佣金、分红 、云仓货款 申请
        if (!in_array($financeType, $checkArray)) {
            throw new \Exception("提现帐户类型错误");
        }
    }

    /**
     * 检测提现金额是否符合最大金额最小金额（余额不需要检测）
     * @param $money 提现金额
     * @param $withdrawConfig 配置信息
     * @return bool
     */
    private function checkMinMaxWithdrawMoney($money)
    {
        $withdrawConfig = $this->getConfig();
        if (($money < $withdrawConfig['min_money'] || $money > $withdrawConfig['max_money'])) {
            throw new \Exception('提现金额过大或过小');
        }
    }

    /**
     * 检测可提现方式
     * @param $payType 提现方式
     * @param $financeType 提现到合何处
     * @return bool
     */
    private function checkAccessPayType($payType, $financeType)
    {
        $payWay = $this->getWithdrawWay();
        $accessPayType = array_merge(
            Constants::getOnlinePayType(),
            Constants::getOfflinePayType(),
            [Constants::PayType_Commission, Constants::PayType_CloudStockGoods, Constants::PayType_Supplier]
        );
        if (!in_array($payType, $accessPayType)) {
            throw new \Exception('提现方式不允许');
        }
        // 不允许余额转余额
        if ($payType == Constants::PayType_Balance && $financeType == Constants::FinanceType_Normal) {
            throw new \Exception('不允许余额转余额');
        }
        if($payType == Constants::PayType_Balance && !$payWay->balance){
            throw new \Exception('不允许提现到余额');
        }
        if($payType == Constants::PayType_Weixin && !$payWay->wxpay){
            throw new \Exception('不允许提现到微信');
        }
        if($payType == Constants::PayType_WeixinQrcode && !$payWay->wxQrcode){
            throw new \Exception('不允许线下提现到微信收款码');
        }
        if($payType == Constants::PayType_Alipay && !$payWay->alipay){
            throw new \Exception('不允许提现到支付宝');
        }
        if($payType == Constants::PayType_AlipayAccount && !$payWay->alipayAccount){
            throw new \Exception('不允许线下提现到支付宝帐户');
        }
        if($payType == Constants::PayType_AlipayQrcode && !$payWay->alipayQrcode){
            throw new \Exception('不允许线下提现到支付宝收款码');
        }
        if($payType == Constants::PayType_Bank && !$payWay->bankAccount){
            throw new \Exception('不允许线下提现到银行帐户');
        }
    }

    /**
     * 计算提现手续费（余额无手续费）
     * @param $money 提现金额
     * @param $withdrawConfig 配置信息
     * @return int
     */
    private function calWithdrawMoneyFee($money)
    {
        $withdrawConfig = $this->getConfig();
        $moneyFee = floor($money * floatval($withdrawConfig['poundage_rate'] / 100)); // 向下取整
        if (floatval($withdrawConfig['poundage_rate'])) $moneyFee = $moneyFee < 1 ? 1 : $moneyFee; //如果手续费率为0,则不算手续费
        return $moneyFee;
    }

    /**
     * 计算提现真实金额 提现真实金额 = 提现金额 - 提现手续费
     * @param $money 提现金额
     * @return mix|int
     */
    private function calWithdrawMoney($money, $moneyFee)
    {
        $moneyReal = $money - $moneyFee;
        if ($moneyReal <= 0) {
            throw new \Exception('实际到帐金额小于等于0');
        }
        return $moneyReal;
    }

    /**
     * 获取outType
     * @param $payType 提现的方式
     * @param $financeType 提现到何处
     * @return int
     */
    private function getOutType($payType, $financeType)
    {
        // 构造outType
        $outType = Constants::FinanceOutType_Unknow;
        if ($payType == Constants::PayType_Commission) {
            $outType = Constants::FinanceOutType_CommissionToBalance;
            if ($financeType == Constants::FinanceType_AreaAgentCommission) {
                $outType = Constants::FinanceOutType_AreaAgentCommissionToBalance;
            }
        } elseif ($payType == Constants::PayType_CloudStockGoods) {
            $outType = Constants::FinanceOutType_CloudStockGoodsToBalance;
        } elseif ($payType == Constants::PayType_Supplier) {
            $outType = Constants::FinanceOutType_SupplierToBalance;
        } else {
            $outType = Constants::FinanceOutType_Withdraw;
        }
        return $outType;
    }

    /**
     * 获取备注
     * @param $payType 提现的方式
     * @param $financeType 提现到何处
     * @return string
     */
    private function getAbout($payType, $financeType)
    {
        $about = '';
        if ($payType == Constants::PayType_Commission) {
            $about = '佣金提现到余额';
            if ($financeType == Constants::FinanceType_AreaAgentCommission) {
                $about = '区代返佣提现到余额';
            }
        } elseif ($payType == Constants::PayType_CloudStockGoods) {
            $about = '云仓货款提现到余额';
        } else {
            $about = Constants::getFinanceTypeText($financeType) . '提现';
            switch ($payType) {
                case 2:
                    $about .= "-微信对公账户";
                    break;
                case 3:
                    $about .= "-支付宝对公账户";
                    break;
                case 6:
                    $about .= "-线下微信收款码";
                    break;
                case 7:
                    $about .= "-线下支付宝收款码";
                    break;
                case 8:
                    $about .= "-线下支付宝账户";
                    break;
                case 9:
                    $about .= "-线下银行卡账户";
                    break;
            }
        }
        return $about;
    }
}