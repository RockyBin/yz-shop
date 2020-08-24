<?php
/**
 * Created by Wenke.
 */

namespace App\Modules\ModuleShop\Libs\Finance;

use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use YZ\Core\Constants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Site\Site;

/**
 * 后台用的提现业务类
 * Class WithdrawAdmin
 * @package App\Modules\ModuleShop\Libs\Finance
 */
class WithdrawAdmin
{
    private $siteId = 0; // 站点ID
    private $finance = null;

    /**
     * 初始化
     * Finance constructor.
     * @param int $siteId
     */
    public function __construct($siteId = 0)
    {
        if ($siteId) {
            $this->siteId = $siteId;
        } else if ($siteId == 0) {
            $this->siteId = Site::getCurrentSite()->getSiteId();
        }
        $this->finance = new Finance($this->siteId);
    }

    /**
     * 查询列表
     * 这里先特殊处理一些事情，后面再调整
     * @param $params
     * @return array
     */
    public function getList($params)
    {
        $params['out_types'] = [
            Constants::FinanceOutType_Withdraw,
            Constants::FinanceOutType_CommissionToBalance,
            Constants::FinanceOutType_AreaAgentCommissionToBalance,
            Constants::FinanceOutType_CloudStockGoodsToBalance,
            Constants::FinanceOutType_SupplierToBalance
        ];
        $payTypesArr = myToArray($params['pay_types'], ',', -1);
        $hasPayTypesParam = count($payTypesArr) > 0;
        $toBalanceArr = [
            Constants::PayType_Balance,
            Constants::PayType_Commission,
            Constants::PayType_CloudStockGoods,
            Constants::PayType_Supplier
        ];
        $toBalance = count(array_diff($toBalanceArr, $payTypesArr)) < count($toBalanceArr);
//        $toBalance = $params['pay_types'] == Constants::PayType_Balance.','.Constants::PayType_Commission.','.Constants::PayType_CloudStockGoods.','.Constants::PayType_Supplier ? true : false; // 是否去余额  不知道为什么这么些 暂时改掉 by hui
        $thirdPartyTypes = Constants::getThirdPartyPayType();
        if ($params['transaction_type'] == 1) {
            $params['types'] = Constants::FinanceType_Commission;
            if ($hasPayTypesParam && $toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = $thirdPartyTypes;
            }
        } else if ($params['transaction_type'] == 2) {
            $params['types'] = Constants::FinanceType_Normal;
            if (!$hasPayTypesParam) $params['pay_types'] = $thirdPartyTypes;
        } else if ($params['transaction_type'] == 3) {
            $params['types'] = Constants::FinanceType_Commission;
            if ($hasPayTypesParam && !$toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = Constants::PayType_Commission;
            }
        } else if ($params['transaction_type'] == 4) {
            $params['types'] = Constants::FinanceType_AgentCommission;
            if ($hasPayTypesParam && $toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = $thirdPartyTypes;
            }
        } else if ($params['transaction_type'] == 5) {
            $params['types'] = Constants::FinanceType_AgentCommission;
            if ($hasPayTypesParam && !$toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = Constants::PayType_Commission;
            }
        } else if ($params['transaction_type'] == 6) {
            $params['types'] = Constants::FinanceType_CloudStock;
            if ($hasPayTypesParam && !$toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = Constants::PayType_CloudStockGoods;
            }
        } else if ($params['transaction_type'] == 7) {
            $params['types'] = Constants::FinanceType_CloudStock;
            if ($hasPayTypesParam && $toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = $thirdPartyTypes;
            }
        } else if ($params['transaction_type'] == 8) { //区代返佣提现到余额
            $params['types'] = Constants::FinanceType_AreaAgentCommission;
            if ($hasPayTypesParam && !$toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = Constants::PayType_Commission;
            }
        } else if ($params['transaction_type'] == 9) { //区代返佣提现到第三方
            $params['types'] = Constants::FinanceType_AreaAgentCommission;
            if ($hasPayTypesParam && $toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = $thirdPartyTypes;
            }
        } else if ($params['transaction_type'] == 23) { //区代返佣提现到余额
            $params['types'] = Constants::FinanceType_Supplier;
            if ($hasPayTypesParam && !$toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = Constants::PayType_Supplier;
            }
        } else if ($params['transaction_type'] == 24) { //供应商货款提现到第三方
            $params['types'] = Constants::FinanceType_Supplier;
            if ($hasPayTypesParam && $toBalance) {
                $params['pay_types'] = '-99'; // 强行不显示数据
            } else if (!$hasPayTypesParam) {
                $params['pay_types'] = $thirdPartyTypes;
            }
        }
        $data = $this->finance->getList($params);
        return $data;
    }

    /**
     * 查询某信息
     * @param int $id 财务ID
     * @return array
     */
    public function getInfo(int $id)
    {
        return $this->finance->getInfo(['id' => $id, 'isShowMemberInfo' => true]);
    }

    /**
     * 提现接口
     * @param $id
     * @return bool
     * @throws \Exception
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function withdraw($id)
    {
        $financeModel = $this->finance->getInfo(['id' => $id]);
        if ($financeModel && in_array(intval($financeModel['out_type']), [Constants::FinanceOutType_Withdraw, Constants::FinanceOutType_CommissionToBalance])) {
            $data = FinanceHelper::Withdraw($financeModel['member_id'], $financeModel['id'], $financeModel['about']);
            if ($data && $data['balance_id']) {
                // 提现到余额
                MessageNoticeHelper::sendMessageBalanceChange(FinanceModel::find($data['balance_id']));
            } else if (intval($financeModel['out_type']) == Constants::FinanceOutType_Withdraw) {
                // 提现到外部
                if ($financeModel->type == Constants::FinanceType_Commission) {
                    // 佣金提现到外部
                    MessageNoticeHelper::sendMessageCommissionWithdraw($financeModel);
                } else if ($financeModel->type == Constants::FinanceType_Normal) {
                    // 余额提现到外部
                    MessageNoticeHelper::sendMessageBalanceChange($financeModel);
                } else if ($financeModel->type == Constants::FinanceType_AgentCommission) {
                    // 代理分红提现到外部
                    MessageNoticeHelper::sendMessageAgentCommissionWithdraw($financeModel);
                }else if ($financeModel->type == Constants::FinanceType_AreaAgentCommission) {
                    // 代理分红提现到外部
                    MessageNoticeHelper::sendMessageAreaAgentWithdrawCommission($financeModel);
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 拒绝提现
     * @param $id
     * @param $reason 原因
     * @return bool
     */
    public function reject($id, $reason)
    {
        $financeModel = $this->finance->getInfo(['id' => $id]);
        if ($financeModel && intval($financeModel->status) == Constants::FinanceStatus_Freeze && in_array(intval($financeModel->out_type), [Constants::FinanceOutType_Withdraw, Constants::FinanceOutType_CommissionToBalance])) {
            $financeModel->status = Constants::FinanceStatus_Invalid;
            $financeModel->invalid_at = date('Y-m-d H:i:s');
            $financeModel->reason = trim($reason);
            $financeModel->save();
            return true;
        }
        return false;
    }

    /**
     * 数据转换接口*
     * $item
     */
    public function convertOutputData($item)
    {
        //transaction_type 交易类型 : 1 外部提现-佣金提现 2 外部提现-余额提现 3 内部提现-佣金提现
        if ($item['type'] == Constants::FinanceType_Commission && ($item['pay_type'] == Constants::PayType_Weixin || $item['pay_type'] == Constants::PayType_Alipay)) {
            $item->transaction_type = '佣金提现（至第三方）';
        } else if ($item['type'] == Constants::FinanceType_Normal && ($item['pay_type'] == Constants::PayType_Weixin || $item['pay_type'] == Constants::PayType_Alipay)) {
            $item->transaction_type = '余额提现（至第三方）';
        } else if ($item['type'] == Constants::FinanceType_Commission && $item['pay_type'] == Constants::PayType_Commission) {
            $item->transaction_type = '佣金提现（至余额）';
        }
        if ($item->status !== '') {
            if ($item->status == Constants::FinanceStatus_Freeze) {
                $item->status_text = '待审核';
            } elseif ($item->status == Constants::FinanceStatus_Active) {
                $item->status_text = '提现成功';
            } elseif ($item->status == Constants::FinanceStatus_Invalid) {
                $item->status_text = '提现失败';
            }
        }
        if ($item->terminal_type != '') {
            $item->terminal_type = Constants::getTerminalTypeText($item->terminal_type);
        }

        $item->money = moneyCent2Yuan(abs($item->money));
        $item->money_real = moneyCent2Yuan(abs($item->money_real));
        $item->money_fee = moneyCent2Yuan(abs($item->money_fee));
        $item->inout_type_text = FinanceHelper::getFinanceInOutTypeText($item->in_type, $item->out_type);
        $item->account_type_text = Constants::getAccountTypeText(FinanceHelper::getFinanceAccountType($item->is_real, $item->money));
        $item->pay_type_text = Constants::getPayTypeText(intval($item->pay_type));
        $item->pay_account_text = Constants::getPayTypeText(intval($item->pay_type), '账户');
        if (intval($item->pay_type) == Constants::PayType_Weixin && $item->wx_nickname) {
            $item->pay_account_text .= "：" . $item->wx_nickname;
        }
        //因为提现管理中，提现只有在佣金转余额的时候，才需要显示为余额账户，其他情况一律跟paytype显示
        if ($item['out_type'] == Constants::FinanceInType_CommissionToBalance) {
            $item->withdraw_from = '余额账户';
        } elseif ($item['pay_type'] == Constants::PayType_Weixin) {
            $item->withdraw_from = '微信账户';
        } elseif ($item['pay_type'] == Constants::PayType_Alipay) {
            $item->withdraw_from = '支付宝账户';
        }
        //收款账户
        if ($item['out_type'] == Constants::FinanceInType_CommissionToBalance) {
            $item->beneficiary_account = '会员账户：' . $item['mobile'];
        } elseif ($item['pay_type'] == Constants::PayType_Weixin) {
            $item->beneficiary_account = '微信账户：' . $item['nickname'];
        } elseif ($item['pay_type'] == Constants::PayType_Alipay) {
            $item->beneficiary_account = '支付宝账户：' . $item['nickname'];
        }

        return $item;
    }
}