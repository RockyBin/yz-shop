<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use App\Modules\ModuleShop\Libs\Dealer\DealerBaseSetting;
use App\Modules\ModuleShop\Libs\Finance\Recharge;
use App\Modules\ModuleShop\Libs\Finance\Withdraw\WithdrawFactory;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForRecharge;
use App\Modules\ModuleShop\Libs\VerifyLog\VerifyLog;
use Illuminate\Http\Request;
use Nwidart\Modules\Collection;
use YZ\Core\Constants;
use YZ\Core\FileUpload\FileUpload;
use YZ\Core\Finance\FinanceHelper;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\SiteConfig\WithdrawConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Promotions\RechargeBonus;
use YZ\Core\Payment\Payment;
use YZ\Core\Site\Config;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants as LibsConstants;

class FinanceController extends BaseController
{
    /**
     * 配置
     * @return array
     */
    public function config()
    {
        try {
            $terminal = getCurrentTerminal(); // 当前终端
            $payConfig = Finance::getPayConfig(0);
            $withdrawConfig = $this->getWithdrawConfig();
            $rechargeBonus = new RechargeBonus($this->siteId);
            $rechargeBonusData = $rechargeBonus->getInfo(1);
            $rechargeBonusData = $rechargeBonus->toYuan($rechargeBonusData);
            $member = (new Member($this->memberId))->getModel();
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), [
                'terminal' => $terminal,
                'withdraw_config' => $withdrawConfig,
                'pay_config' => $payConfig,
                'recharge_bonus' => $rechargeBonusData,
                'dealer_level' => $member->dealer_level
            ]);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 余额首页
     * @return array
     */
    public function balanceInfo(Request $request)
    {
        try {
            $payConfigType = intval($request->get('pay_config_type', 0));
            $member = new Member($this->memberId, $this->siteId);
            $balanceInfo = FinanceHelper::getBalanceInfo($this->memberId);
            $balanceInfo['withdrawal_done'] = FinanceHelper::getMemberBalanceWithdrawal($this->memberId);
            $balanceInfo['withdrawal_ing'] = FinanceHelper::getMemberBalanceWithdrawCheck($this->memberId);
            // 把分转为元
            foreach ($balanceInfo as &$balance) {
                $balance = moneyCent2Yuan($balance);
            }
            $payConfig = Site::getCurrentSite()->getConfig()->getPayConfig();
            $withdrawConfig = $this->getWithdrawConfig();
            $withdrawConfigTypeArr = $withdrawConfig['balance_type'];
            //若是没开启支付设置则不允许余额提现
            foreach ($withdrawConfigTypeArr as $key => $value) {
                if ($key == 'alipay' && !$payConfig->alipay_appid) $withdrawConfigTypeArr[$key] = 0;
                if ($key == 'wxpay' && !$payConfig->wxpay_mchid) $withdrawConfigTypeArr[$key] = 0;
            }
            $withdrawConfig['balance_type'] = new Collection($withdrawConfigTypeArr);
            $site = Site::getCurrentSite();
            $hasBalanceGiveFunction = $site->getSn()->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_DISTRIBUTION)
                | $site->getSn()->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_AGENT)
                | $site->getSn()->hasPermission(\App\Modules\ModuleShop\Libs\Constants::FunctionPermission_ENABLE_CLOUDSTOCK);
            $config = (new Config($this->siteId))->getModel();
            $balanceGiveConfig = ['balance_give_status' => $config->balance_give_status,
                'balance_give_target' => $config->balance_give_target,
                'has_balance_give_function' => ($member->getModel()->agent_level || $member->getModel()->dealer_level || $member->getModel()->is_distributor) && $hasBalanceGiveFunction];
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), [
                'finance' => $balanceInfo,
                'withdraw_config' => $withdrawConfig,
                'pay_config' => Finance::getPayConfig($payConfigType),
                'balance_give_config' => $balanceGiveConfig,
                'terminal' => getCurrentTerminal(),
                'pay_password_status' => $member->payPasswordIsNull() ? 0 : 1,
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 会员余额冻结明细
     * @return array
     */
    public function memberBalanceFrozenList(Request $request)
    {
        try {
            $finance = New Finance($this->siteId);
            $params['single_member'] = true;
            $params['member_id'] = $this->memberId;
            $params['types'] = Constants::FinanceType_Normal;
            $params['status'] = Constants::FinanceStatus_Freeze;
            $params['out_types'] = [Constants::FinanceOutType_PayOrder, Constants::FinanceOutType_Withdraw];
            $params['page'] = $request->page;
            $params['page_size'] = $request->page_size;
            $data = $finance->getList($params);
            // 数据处理
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $item->money = moneyCent2Yuan($item->money);
                    $item->money_fee = moneyCent2Yuan($item->money_fee);
                    $item->money_real = moneyCent2Yuan($item->money_real);
                    $item->inout_type_text = FinanceHelper::getFinanceInOutTypeForBalance($item->in_type, $item->out_type, $item->pay_type, $item->tradeno);
                }
            }

            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 会员提现的余额列表
     * @param Request $request
     * @return array
     */
    public function memberBalanceWithdrawList(Request $request)
    {
        try {
            $finance = New Finance($this->siteId);
            $params['single_member'] = true;
            $params['member_id'] = $this->memberId;
            $params['time_order_by'] = true;
            $params['types'] = Constants::FinanceType_Normal;
            $params['status'] = $request->get('status');
            $params['out_types'] = [Constants::FinanceOutType_Withdraw];
            $params['page'] = $request->page;
            $params['page_size'] = $request->page_size;
            $params['order_by'] = 'time';
            $data = $finance->getList($params);
            // 数据处理
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $item->money = moneyCent2Yuan($item->money);
                    $item->money_fee = moneyCent2Yuan($item->money_fee);
                    $item->money_real = moneyCent2Yuan($item->money_real);
                    $item->inout_type_text = '提现至' . Constants::getPayTypeWithdrawText($item->pay_type);
                }
            }

            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 会员余额明细
     * @return array
     */
    public function memberBalanceList(Request $request)
    {
        try {
            $finance = New Finance($this->siteId);
            $params['single_member'] = true;
            $params['member_id'] = $this->memberId;
            $params['status'] = Constants::FinanceStatus_Active;
            $params['types'] = Constants::FinanceType_Normal;
            $params['trade_type'] = 7;
            $params['page'] = $request->page;
            $params['page_size'] = $request->page_size;
            $params['order_by'] = 'active_at';
            $data = $finance->getList($params);
            // list 是时间倒序的
            $list = $data['list'];
            // 拿出需要统计的时间范围
            $minMonth = ''; // 最小月份
            $maxMonth = ''; // 最大月份
            // 处理数据
            foreach ($list as $item) {
                $item->money = moneyCent2Yuan($item->money);
                $item->money_fee = moneyCent2Yuan($item->money_fee);
                $item->money_real = moneyCent2Yuan($item->money_real);
                $item->inout_type_text = FinanceHelper::getFinanceInOutTypeForBalance(
                    $item->in_type,
                    $item->out_type,
                    $item->pay_type,
                    $item->tradeno,
                    ['about' => $item->about]
                );
                if ($item->in_type == Constants::FinanceInType_Give
                    || in_array($item->out_type, [
                        Constants::FinanceOutType_Give,
                        Constants::FinanceOutType_DealerPerformanceReward,
                        Constants::FinanceOutType_DealerRecommendReward,
                        Constants::FinanceOutType_DealerSaleReward,
                        Constants::FinanceOutType_DealerOrderReward
                        ])
                ) {
                    $item->inout_type_text = $item->about;
                }
                if ($maxMonth == '') {
                    $maxMonth = date('Y-m-d 23:59:59', strtotime($item->active_at));
                }
                $minMonth = date('Y-m-01', strtotime($item->active_at));
            }
            $params['active_at_start'] = $minMonth;
            $params['active_at_end'] = $maxMonth;
            if ($minMonth && $maxMonth) {
                // 统计入账
                $countInData = $finance->countByMonth(array_merge($params, ['money_sign' => 1]))->pluck('money', 'date_sign')->toArray();
                // 统计出账
                $countOutData = $finance->countByMonth(array_merge($params, ['money_sign' => -1]))->pluck('money', 'date_sign')->toArray();
                // 按月份整合数据
                $dataList = [];
                foreach ($list as $item) {
                    $data_sign = date('Y-m', strtotime($item->active_at));
                    if (!array_key_exists($data_sign, $dataList)) {
                        $dataList[$data_sign]['finance_list'] = [];
                        $dataList[$data_sign]['finance_in'] = array_key_exists($data_sign, $countInData) ? moneyCent2Yuan(abs($countInData[$data_sign])) : 0;
                        $dataList[$data_sign]['finance_out'] = array_key_exists($data_sign, $countOutData) ? moneyCent2Yuan(abs($countOutData[$data_sign])) : 0;
                    }
                    $dataList[$data_sign]['finance_list'][] = $item;
                }
                // 调整结构
                $data['list'] = [];
                foreach ($dataList as $key => $dataItem) {
                    $dataItem['finance_month'] = $key;
                    $data['list'][] = $dataItem;
                }
            }

            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取提现配置
     * @return array
     */
    private function getWithdrawConfig()
    {
        $withdrawConfig = New WithdrawConfig();
        $withdrawConfigData = $withdrawConfig->getInfo();
        if ($withdrawConfigData['balance_type']) {
            $withdrawConfigData['balance_type'] = json_decode($withdrawConfigData['balance_type'], true);
        } else {
            $withdrawConfigData['balance_type'] = [];
        }
        if ($withdrawConfigData['commission_type']) {
            $withdrawConfigData['commission_type'] = json_decode($withdrawConfigData['commission_type'], true);
        } else {
            $withdrawConfigData['commission_type'] = [];
        }

        return $withdrawConfigData;
    }

    /**
     * 充值
     * @param Request $request
     * @return array
     */
    public function memberBalanceRecharge(Request $request)
    {
        try {
            $money = abs(floatval($request->money));
            if ($money == 0) {
                return makeApiResponseFail('充值金额不可为0元');
            }
            $pay_type = intval($request->pay_type);
            $payArr = array_merge(Constants::getOnlinePayType(), Constants::getOfflinePayType());
            if (!in_array($pay_type, $payArr)) {
                return makeApiResponseFail('请选择充值方式');
            }

            $callback = 'App\Modules\ModuleShop\Libs\Finance\Recharge@afterRecharge'; // 充值后的事件
            $amount = moneyYuan2Cent($money); // 元转分
            $orderId = 'RECHARGE_' . generateOrderId();
            if (in_array($pay_type, Constants::getOnlinePayType())) {
                $res = Payment::doPay($orderId, $this->memberId, $amount, $callback, $pay_type);
				if(getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxApp) $res['backurl'] = '#/member/balance-home'; //小程序专用，用来标记在小程序端支付成功后，应该跳转到哪里
            } elseif (in_array($pay_type, [6, 7, 8, 9])) { //线下支付
                // 直接使用数组传多个文件 有些浏览器会有兼容性 （iPhone qq浏览器）
                $vouchers = [$request->file('voucher1'), $request->file('voucher2'), $request->file('voucher3')];
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
                $arr = [];
                $arr['order_id'] = $orderId;
                $arr['member_id'] = $this->memberId; // 当前充值人
                $arr['money'] = $amount;
                $arr['pay_type'] = $pay_type;
                $arr['money_fee'] = 0;//暂时无手续费
                $arr['money_real'] = $amount;
                $arr['in_type'] = Constants::FinanceInType_Recharge;
                $arr['snapshot'] = implode(',', $voucherFiles); // 快照暂时只装凭证
                $arr['terminal_type'] = getCurrentTerminal();
                $bonus = Recharge::calcRechargeBonus($amount);
                $arr['recharge_bonus'] = json_encode($bonus);//充值送的优惠
                $pointGive = new PointGiveForRecharge($this->memberId,null);
                $point = $pointGive->calcPoint($amount);
                $arr['give_point'] = $point;//充值的赠送积分
                VerifyLog::Log(LibsConstants::VerifyLogType_BalanceVerify, $arr);
                return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
            } else {
                return makeApiResponse(400, "支付方式错误");
            }
            return makeApiResponseSuccess('ok', [
                'result' => $res
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


    /**
     * 经销商中心充值
     * 暂时只有线下充值
     * return array
     */
    public function dealerBalanceRecharge(Request $request)
    {
        try {
            $payType = intval($request->get('pay_type'));
            $dealerParentId = (new Member($this->memberId))->getModel();
            $money = abs(floatval($request->money));
            if ($dealerParentId->dealer_parent_id != $request->dealer_parent_id) {
                return makeApiResponseFail('经销商父级有变，请重新刷新页面');
            }
            if ($money == 0) {
                return makeApiResponseFail('充值金额不可为0元');
            }
            if (!in_array($payType, Constants::getOfflinePayType())) {
                return makeApiResponseFail('请选择充值方式');
            }

            $amount = moneyYuan2Cent($money); // 元转分
            $orderId = 'RECHARGE_' . generateOrderId();
            if (in_array($payType, [6, 7, 8, 9])) { //线下支付
                // 直接使用数组传多个文件 有些浏览器会有兼容性 （iPhone qq浏览器）
                $vouchers = [$request->file('voucher1'), $request->file('voucher2'), $request->file('voucher3')];
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
                $arr = [];
                $arr['order_id'] = $orderId;
                $arr['member_id'] = $this->memberId;
                $arr['money'] = $amount;
                $arr['pay_type'] = $payType;
                $arr['in_type'] = Constants::FinanceInType_Give;
                $arr['money_fee'] = 0;//暂时无手续费
                $arr['money_real'] = $amount;
                $arr['snapshot'] = implode(',', $voucherFiles); // 快照暂时只装凭证
                $arr['terminal_type'] = getCurrentTerminal();
                //充值给上级无优惠
                $arr['recharge_bonus'] = 0;
                $arr['give_point'] = 0;
                VerifyLog::Log(LibsConstants::VerifyLogType_BalanceVerify, $arr);
                return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
            } else {
                return makeApiResponse(400, "支付方式错误");
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 余额转现搜索会员
     *
     * @param Request $request
     * @return void
     */
    public function balanceGiveSearchMember(Request $request)
    {
        if (!$request->mobile) {
            return makeApiResponseFail('请输入需要搜索的电话号码');
        }
        $res = Finance::balanceGiveSearchMember($request->mobile, $this->memberId);
        if ($res) {
            if ($res->id == $this->memberId) return makeApiResponseFail('不能给自己转现哦，请重新输入~');
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $res);
        } else {
            return makeApiResponseFail('找不到该会员哦，请重新输入~');
        }
    }

    /**
     * 执行余额转现
     *
     * @param Request $request
     * @return void
     */
    public function balanceGive(Request $request)
    {
        try {
            if (!$request->income_member_id) {
                return makeApiResponseFail('请输入需要赠送的会员ID');
            }
            // 如果是余额支付 要验证支付密码
            $member = new Member($this->memberId);
            $password = $request->get('password');
            if ($member->payPasswordIsNull()) {
                return makeApiResponse(402, trans('shop-front.shop.pay_password_error'));
            }
            if (!$member->payPasswordCheck($password)) {
                return makeApiResponse(406, trans('shop-front.shop.pay_password_error'));
            }
            Finance::balanceGive($request->income_member_id, $this->memberId, moneyYuan2Cent($request->money));
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function balanceJump()
    {
        try {
            // 判断此人是经销商且后台配置是转给上级且这个人有上级
            $member = (new Member($this->memberId))->getModel();
            $dealerBase = DealerBaseSetting::getCurrentSiteSetting();
            if ($member->dealer_level > 0 && $member->dealer_parent_id > 0 && $dealerBase->recharge_balance_target == 1) {
                $redirectUrl = '/dealer/dealer-recharge';
            } else {
                $redirectUrl = '/member/balance-recharge';
            }
            return makeApiResponseSuccess('ok', $redirectUrl);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}