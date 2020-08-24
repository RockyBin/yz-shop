<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Finance;

use App\Modules\ModuleShop\Jobs\UpgradeAgentLevelJob;
use App\Modules\ModuleShop\Jobs\UpgradeDistributionLevelJob;
use App\Modules\ModuleShop\Libs\Member\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use YZ\Core\Common\Export;
use YZ\Core\Constants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * 后台财务 Controller
 * Class FinanceController
 * @package App\Modules\ModuleShop\Http\Controllers\Admin\Finance
 */
class FinanceController extends BaseAdminController
{
    use DispatchesJobs;
    private $siteId = 0;
    private $finance;

    /**
     * 初始化
     * MemberController constructor.
     */
    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
        $this->finance = New Finance($this->siteId);
    }

    /**
     * 财务列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->toArray();
            $param['for_business'] = true;
            $param['status'] = Constants::PointStatus_Active;
            $param['order_by'] = 'active_at';
            $data = $this->finance->getList($param);
            // 处理数据
            foreach ($data['list'] as $item) {
                $item->inout_type_text = FinanceHelper::getFinanceAdminInOutTypeText($item->in_type, $item->out_type, $item->type, $item->order_type);
                $item->account_type_text = Constants::getAccountTypeText(FinanceHelper::getFinanceAccountType($item->is_real, $item->money));
                $item->pay_type_text = Constants::getPayTypeText(intval($item->pay_type), in_array($item->pay_type, [Constants::PayType_Manual, Constants::PayType_Commission, Constants::PayType_WeixinQrcode, Constants::PayType_AlipayQrcode, Constants::PayType_AlipayAccount, Constants::PayType_Bank, Constants::PayType_CloudStockGoods]) ? '结算' : '');
                // 绝对值，所以要放到最后
                $item->money = moneyCent2Yuan(abs($item->money));
                $item->money_fee = moneyCent2Yuan(abs($item->money_fee));
                $item->money_real = moneyCent2Yuan(abs($item->money_real));
                // 交易类型
                $item->transaction_type = $item->inout_type_text;
                $item->transaction_type_extend = '';
                if (
                    in_array($item->out_type, [
                        Constants::FinanceOutType_CommissionToBalance,
                        Constants::FinanceOutType_Withdraw,
                        Constants::FinanceOutType_CloudStockGoodsToBalance,
                        Constants::FinanceOutType_SupplierToBalance,
                        Constants::FinanceOutType_AreaAgentCommissionToBalance,
                    ])
                ) {
                    if ($item->type == Constants::FinanceType_Normal && (in_array($item->pay_type, Constants::getThirdPartyPayType()))) {
                        $item->transaction_type = '余额提现';
                        $item->transaction_type_extend = '第三方';
                    } else if ($item->type == Constants::FinanceType_Commission) {
                        $item->transaction_type = '分销佣金提现';
                        $item->transaction_type_extend = $item->pay_type == Constants::PayType_Commission ? '余额' : '第三方';
                    } else if ($item->type == Constants::FinanceType_AgentCommission) {
                        $item->transaction_type = '代理分红提现';
                        $item->transaction_type_extend = $item->pay_type == Constants::PayType_Commission ? '余额' : '第三方';
                    } else if ($item->type == Constants::FinanceType_CloudStock) {
                        $item->transaction_type = '经销商资金提现';
                        $item->transaction_type_extend = $item->pay_type == Constants::PayType_CloudStockGoods ? '余额' : '第三方';
                    } else if ($item->type == Constants::FinanceType_Supplier) {
                        $item->transaction_type = '供应商货款提现';
                        $item->transaction_type_extend = $item->pay_type == Constants::PayType_Supplier ? '余额' : '第三方';
                    } else if ($item->type == Constants::FinanceType_AreaAgentCommission) {
                        $item->transaction_type = '区代返佣提现';
                        $item->transaction_type_extend = $item->pay_type == Constants::PayType_Commission ? '余额' : '第三方';
                    }
                }
                /*
                //这段代码有问题，为什么从经销商的相应佣金转出就认为是 余额转现支出 ??? 泉2020-06-20注释并备注
                if (
                in_array($item->out_type, [
                    Constants::FinanceOutType_DealerPerformanceReward,
                    Constants::FinanceOutType_DealerRecommendReward,
                    Constants::FinanceOutType_DealerSaleReward
                ])
                ) {
                    $item->transaction_type = '余额转现支出';
                }*/
                // 代理加盟费需要修改摘要
                if (($item->out_type == Constants::FinanceOutType_AgentInitial || $item->in_type == Constants::FinanceInType_AgentInitial || $item->out_type == Constants::FinanceOutType_DealerInitial || $item->in_type == Constants::FinanceInType_DealerInitial) && in_array($item->pay_type, Constants::getOfflinePayType())) {
                    switch ($item->pay_type) {
                        case $item->pay_type == Constants::PayType_WeixinQrcode:
                            $pay_type_text = '微信收款码';
                            break;
                        case $item->pay_type == Constants::PayType_AlipayQrcode:
                            $pay_type_text = '支付宝收款码';
                            break;
                        case $item->pay_type == Constants::PayType_AlipayAccount:
                            $pay_type_text = '支付宝账户';
                            break;
                        case $item->pay_type == Constants::PayType_Bank:
                            $pay_type_text = '银行账户';
                            break;
                    }
                    $item->about = $item->about . '-线下' . $pay_type_text;
                }
                $item->mobile = Member::memberMobileReplace($item->mobile);
            }

            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 充值
     * @param Request $request
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    function rechange(Request $request)
    {
        try {
            if ($request->financial_direction == 1) {
                $money = moneyYuan2Cent(abs($request->rechange_money));
                $isReal = Constants::FinanceIsReal_Yes;
                $param['in_type'] = Constants::FinanceInType_Manual;
                $about = $request->about ? "手工充值余额-" . $request->about : "手工充值余额";
            } else {
                $money = -moneyYuan2Cent(abs($request->rechange_money));
                $isReal = Constants::FinanceIsReal_No;
                $param['out_type'] = Constants::FinanceOutType_Manual;   //手动扣款;
                $about = $request->about ? "手工扣减余额-" . $request->about : "手工扣减余额";
                // 检查当前余额是否足够扣减
                $realBalance = FinanceHelper::getMemberRealBalance($request->member_id);
                if ($realBalance <= 0 || $realBalance < abs($money)) {
                    return makeApiResponseFail('余额不足扣减');
                }
            }
            $param['member_id'] = $request->member_id;
            $param['money'] = $money;
            $param['money_real'] = $money;
            $param['operator'] = Session::get('SiteAdmin')['id'];
            $param['type'] = Constants::FinanceType_Normal;
            $param['pay_type'] = Constants::PayType_Manual;
            $param['order_id'] = 'MANUAL_' . date("YmdHis");
            $param['tradeno'] = 'MANUAL_' . date("YmdHis");
            $param['is_real'] = $isReal;
            $param['operator'] = Session::get('SiteAdmin')['id'];
            $param['terminal_type'] = Constants::TerminalType_Manual;
            $param['about'] = $about;
            $param['status'] = Constants::FinanceStatus_Active;
            $param['active_at'] = date('Y-m-d H:i:s');
            $data = $this->finance->add($param);
            // 相关分销商升级
            $this->dispatch(new UpgradeDistributionLevelJob($request->member_id,['money' =>$money]));
            $this->dispatch(new UpgradeAgentLevelJob($request->member_id,['money' =>$money]));
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }

    }

    /**
     * 导出
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        try {
            $param = $request->all();
            $param['for_business'] = true;
            $param['status'] = Constants::PointStatus_Active;
            $param['order_by'] = 'active_at';
            $data = $this->finance->getList($param);
            $exportHeadings = [
                '出/入账时间',
                '流水号',
                'ID',
                '昵称',
                '姓名',
                '手机号',
                '类型',
                '金额',
                '出/入账',
                //'商户',
                '结算方式',
                '摘要'
            ];
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as $item) {
                    $payAccountText = Constants::getPayTypeText(intval($item->pay_type), '账户');
                    if (intval($item->pay_type) == Constants::PayType_Weixin && $item->auth_nickname) {
                        $payAccountText .= "：" . $item->auth_nickname;
                    } else {
                        $payAccountText .= "：" . $item->nickname;
                    }
                    // 代理加盟费需要修改摘要
                    if (($item->out_type == Constants::FinanceOutType_AgentInitial || $item->out_type == Constants::FinanceOutType_DealerInitial || $item->in_type == Constants::FinanceInType_DealerInitial || $item->in_type == Constants::FinanceInType_AgentInitial) && in_array($item->pay_type, Constants::getOfflinePayType())) {
                        switch ($item->pay_type) {
                            case $item->pay_type == Constants::PayType_WeixinQrcode:
                                $pay_type_text = '微信收款码';
                                break;
                            case $item->pay_type == Constants::PayType_AlipayQrcode:
                                $pay_type_text = '支付宝收款码';
                                break;
                            case $item->pay_type == Constants::PayType_AlipayAccount:
                                $pay_type_text = '支付宝账户';
                                break;
                            case $item->pay_type == Constants::PayType_Bank:
                                $pay_type_text = '银行账户';
                                break;
                        }
                        $item->about = $item->about . '-线下' . $pay_type_text;
                    }
                    $exportData[] = [
                        $item->active_at,
                        "\t" . $item->tradeno . "\t",
                        $item->member_id,
                        $item->nickname,
                        $item->name,
                        "\t" . $item->mobile . "\t",
                        FinanceHelper::getFinanceAdminInOutTypeText($item->in_type, $item->out_type, $item->type, $item->order_type),
                       abs(moneyCent2Yuan($item->money)),
                        Constants::getAccountTypeText(FinanceHelper::getFinanceAccountType($item->is_real, $item->money)),
                        // '', // 商户 TODO 后台财务导出显示商户
                        Constants::getPayTypeText(intval($item->pay_type), in_array($item->pay_type, [Constants::PayType_Manual, Constants::PayType_Commission, Constants::PayType_WeixinQrcode, Constants::PayType_AlipayQrcode, Constants::PayType_AlipayAccount, Constants::PayType_Bank, Constants::PayType_CloudStockGoods]) ? '结算' : ''),
                        $item->about
                    ];
                }
            }

            $exportObj = new Export(new Collection($exportData), 'ShouZhi-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

}