<?php
/**
 * Created by Wenke.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Finance;

use App\Modules\ModuleShop\Libs\Finance\Finance;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Site\Site;
use YZ\Core\Common\Export;
use YZ\Core\Constants;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Finance\WithdrawAdmin;
use Maatwebsite\Excel\Events\AfterSheet;

class WithdrawController extends BaseAdminController
{
    private $siteId = 0;
    private $withdraw;

    /**
     * 初始化
     * MemberController constructor.
     */
    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
        $this->withdraw = New WithdrawAdmin($this->siteId);
    }

    /**
     * 提现管理列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $data = $this->withdraw->getList($param);
            // 处理数据
            foreach ($data['list'] as $item) {
                $item->money = moneyCent2Yuan(abs($item->money));
                $item->money_real = moneyCent2Yuan(abs($item->money_real));
                $item->money_fee = moneyCent2Yuan(abs($item->money_fee));
                $item->inout_type_text = FinanceHelper::getFinanceInOutTypeText($item->in_type, $item->out_type);
                $item->account_type_text = Constants::getAccountTypeText(FinanceHelper::getFinanceAccountType($item->is_real, $item->money));
                $item->pay_type_text = Constants::getPayTypeText(intval($item->pay_type));
                $item->snapshot = json_decode($item->snapshot);
                $this->convertOutputData($item);
            }

            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 提现管理
     * @param Request $request
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponseFail('数据异常：ID不能不空');
            }
            $data = $this->withdraw->getInfo($request->id);
            $data_arr = $data->toArray();
            $data_arr['money'] = moneyCent2Yuan(abs($data_arr['money']));
            $data_arr['money_fee'] = moneyCent2Yuan(abs($data_arr['money_fee']));
            $data_arr['money_real'] = moneyCent2Yuan(abs($data_arr['money_real']));
            $data_arr['snapshot'] = json_decode($data_arr['snapshot']);
            $this->convertOutputData($data_arr);
            $data = new Collection($data_arr);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public function withDraw(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponseFail('数据异常：ID不能不空');
            }
            $data = $this->withdraw->withdraw($request->id);
            if ($data) {
                return makeApiResponseSuccess('ok', $data);
            } else {
                return makeApiResponseFail('提现失败');
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 拒绝提现
     * @param Request $request
     * @return array
     */
    public function reject(Request $request)
    {
        try {
            if (!$request->get('id')) {
                return makeApiResponseFail(trans('shop-admin.common.data_error'));
            }
            $result = $this->withdraw->reject($request->get('id'), $request->get('reason'));
            if ($result) {
                return makeApiResponseSuccess(trans('shop-admin.common.action_ok'));
            } else {
                return makeApiResponseFail(trans('shop-admin.common.action_fail'));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 提现列表导出
     * $param 搜索条件
     */
    public function export(Request $request)
    {
        try {
            $param = $request->all();
            //$param['withdraw'] = 1;
            $data = $this->withdraw->getList($param);
            $exportHeadings = [
                '申请时间',
                '提现单号',
                '出/入账时间',
                '流水号',
                'ID',
                '昵称',
                '姓名',
                '手机号',
                '类型',
                '提现至',
                '提现帐户类型',
                '提现帐户名',
                '提现帐户',
                '提现金额',
                '手续费',
                '到账金额',
                '提现状态',
            ];
            $exportData = [];
            $rowCount = count($data['list']);
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $item = $this->withdraw->convertOutputData($item);
                    $this->convertOutputData($item);
                    $withdrawFrom = $item['withdraw_from'];
                    if ($item['withdraw_from_extend']) {
                        $withdrawFrom .= '(' . $item['withdraw_from_extend'] . ')';
                    }
                    $transactionType = $item['transaction_type'];
                    if ($item['transaction_type_extend']) {
                        $transactionType .= '(至' . $item['transaction_type_extend'] . ')';
                    }
                    $bank = '--';
                    $accountName = '--';
                    $account = '--';
                    if ($item->snapshot) $snapshot = json_decode($item->snapshot, true);
                    if ($item->pay_type == 6 && $snapshot) {
                        $bank = '微信收款码';
                        $account = getHttpProtocol() . '://' . getHttpHost() . Site::getSiteComdataDir() . $snapshot['wx_qrcode'];
                    }
                    if ($item->pay_type == 7 && $snapshot) {
                        $bank = '支付宝收款码';
                        $account = getHttpProtocol() . '://' . getHttpHost() . Site::getSiteComdataDir() . $snapshot['alipay_qrcode'];
                    }
                    if ($item->pay_type == 8 && $snapshot) {
                        $bank = '支付宝账户';
                        $accountName = $snapshot['alipay_name'] ?: '';
                        $account = "\t" . $snapshot['alipay_account'] ?: '' . "\t";
                    }
                    if ($item->pay_type == 9 && $snapshot) {
                        $bank = $snapshot['bank'].' '.$snapshot['bank_branch'];
                        $accountName = $snapshot['bank_card_name'] ?: '';
                        $account = "\t" . $snapshot['bank_account'] ?: '' . "\t";
                    }
                    $exportData[] = [
                        $item->created_at,
                        $item->id,
                        $item->status == Constants::FinanceStatus_Active ? $item->active_at : '--',
                        $item->status == Constants::FinanceStatus_Active ? $item->tradeno : '--',
                        $item->member_id,
                        $item->nickname,
                        $item->name,
                        "\t" . $item->mobile . "\t",
                        $transactionType,
                        $withdrawFrom,
                        $bank,
                        $accountName,
                        $account,
                        $item->money ,
                        $item->money_fee,
                        $item->status == Constants::FinanceStatus_Active ? $item->money_real  : '--',
                        $item->status_text,
                    ];
                }
            }
            $exportObj = new Export(new Collection($exportData), 'TiXian-' . date("YmdHis") . '.xlsx', $exportHeadings);
            // 设置列宽等格式
            $exportObj->setRegisterEvents(
                function () use ($rowCount) {
                    return [
                        AfterSheet::class => function (AfterSheet $event) use ($rowCount) {
                            for ($i = 0; $i < $rowCount; $i++) {
                                $cell = $event->sheet->getDelegate()->getCell('L' . ($i + 1));
                                $value = $cell->getValue();
                                if (strpos($value, 'http://') !== false || strpos($value, 'https://') !== false) {
                                    $cell->getHyperlink()->setUrl($value);
                                    $cell->getStyle()->applyFromArray(array('font' => array('color' => ['rgb' => '0000FF'], 'underline' => 'single')));
                                }
                            }
                        }
                    ];
                }
            );

            return $exportObj->export();
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 处理数据
     * @param $item
     */
    private function convertOutputData(&$item)
    {
        // 收款方账户烈性
        $item['withdraw_from'] = '';
        if (in_array($item['out_type'], [Constants::FinanceInType_CommissionToBalance, Constants::FinanceInType_CloudStockGoodsToBalance,Constants::FinanceInType_AreaAgentCommissionToBalance,Constants::FinanceInType_SupplierToBalance])) {
            $item['withdraw_from'] = '余额';
        } else if (in_array($item['pay_type'], [Constants::PayType_Weixin, Constants::PayType_WeixinQrcode])) {
            $item['withdraw_from'] = '微信钱包';
        } else if (in_array($item['pay_type'], [Constants::PayType_Alipay, Constants::PayType_AlipayQrcode, Constants::PayType_AlipayAccount])) {
            $item['withdraw_from'] = '支付宝';
        } else if (in_array($item['pay_type'], [Constants::PayType_Bank])) {
            $item['withdraw_from'] = '银行账户';
        }
        // 线上或线下结算
        $item['withdraw_from_extend'] = '';
        if (in_array(intval($item['pay_type']), Constants::getOnlinePayType())) {
            $item['withdraw_from_extend'] = "线上结算";
        } else if (in_array(intval($item['pay_type']), Constants::getOfflinePayType())) {
            $item['withdraw_from_extend'] = "线下结算";
        }
        // 收款账户
        $item['beneficiary_type'] = '';
        $item['beneficiary_account'] = '';
        if (in_array($item['out_type'], [Constants::FinanceInType_CommissionToBalance, Constants::FinanceInType_CloudStockGoodsToBalance])) {
            $item['beneficiary_type'] = '会员账户';
            $item['beneficiary_account'] = $item['beneficiary_type'] . ($item['mobile'] ? "：" . $item['mobile'] : "");
        } elseif (in_array($item['pay_type'], [Constants::PayType_Weixin, Constants::PayType_WeixinQrcode])) {
            $item['beneficiary_type'] = '微信账户';
            $item['beneficiary_account'] = $item['beneficiary_type'] . ($item['auth_nickname'] ? '：' . $item['auth_nickname'] : "");
        } elseif (in_array($item['pay_type'], [Constants::PayType_Alipay, Constants::PayType_AlipayQrcode, Constants::PayType_AlipayAccount])) {
            $item['beneficiary_type'] = '支付宝账户';
            $item['beneficiary_account'] = $item['beneficiary_type'] . ($item['auth_nickname'] ? '：' . $item['auth_nickname'] : "");
        } elseif (in_array($item['pay_type'], [Constants::PayType_Bank])) {
            $item['beneficiary_type'] = '银行账户';
            $item['beneficiary_account'] = $item['beneficiary_type'];
        }
        // 交易类型
        $item['transaction_type'] = '';
        $item['transaction_type_extend'] = '';
        //是否显示此会员曾经参加过充值优惠活动
        $item['show_recharge_discount'] = false;
        if ($item['type'] == Constants::FinanceType_Normal && (in_array($item['pay_type'], Constants::getThirdPartyPayType()))) {
            $item['transaction_type'] = '余额提现';
            $item['transaction_type_extend'] = '第三方';
            $item['show_recharge_discount'] = Finance::MemberJoinShowRechargedDiscount($item['member_id']);
        } else if ($item['type'] == Constants::FinanceType_Commission) {
            $item['transaction_type'] = '分销佣金提现';
            $item['transaction_type_extend'] = $item['pay_type'] == Constants::PayType_Commission ? '余额' : '第三方';
        } else if ($item['type'] == Constants::FinanceType_AgentCommission) {
            $item['transaction_type'] = '代理分红提现';
            $item['transaction_type_extend'] = $item['pay_type'] == Constants::PayType_Commission ? '余额' : '第三方';
        } else if ($item['type'] == Constants::FinanceType_AreaAgentCommission) {
            $item['transaction_type'] = '区代返佣提现';
            $item['transaction_type_extend'] = $item['pay_type'] == Constants::PayType_Commission ? '余额' : '第三方';
        } else if ($item['type'] == Constants::FinanceType_CloudStock) {
            $item['transaction_type'] = '经销商资金提现';
            $item['transaction_type_extend'] = $item['pay_type'] == Constants::PayType_CloudStockGoods ? '余额' : '第三方';
        } else if ($item['type'] == Constants::FinanceType_Supplier) {
            $item['transaction_type'] = '供应商货款提现';
            $item['transaction_type_extend'] = $item['pay_type'] == Constants::PayType_Supplier ? '余额' : '第三方';
        }
    }
}