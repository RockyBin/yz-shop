<?php

namespace App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\Withdraw;

use App\Modules\ModuleShop\Http\Controllers\SupplierPlatform\BaseSupplierPlatformController as BaseController;
use App\Modules\ModuleShop\Libs\Finance\Withdraw\WithdrawFactory;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Member\MemberWithdrawAccount;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformCount;
use App\Modules\ModuleShop\Libs\SupplierPlatform\SupplierPlatformWithdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Events\AfterSheet;
use YZ\Core\Common\Export;
use YZ\Core\Constants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Site\Site;

class SupplierPlatformWithdrawController extends BaseController
{
    /**
     * 提现管理列表
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        try {
            $param = $request->all();
            $withdraw = new SupplierPlatformWithdraw($this->siteId,$this->memberId);
            $data = $withdraw->getList($param);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 提现统计信息
     * @param Request $request
     * @return array
     */
    public function getCountInfo(Request $request)
    {
        try {
            $count = new SupplierPlatformCount($this->siteId,$this->memberId);
            $data = $count->getCountInfo(['count_withdraw' => 1]);
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取提现页面用到的信息
     * @param Request $request
     */
    public function getInfo(Request $request)
    {
        try {
            $type = 12;
            $withdraw = WithdrawFactory::createInstance($type);
            $balance = $withdraw->getAvailableBalance($type, $this->memberId);
            $WithdrawAccount = new MemberWithdrawAccount($this->memberId);
            $accountInfo = $WithdrawAccount->getInfo();
            $member = new Member($this->memberId, $this->siteId);
            //用户是否有为wx的open_id或者支付宝的open_id
            $memberInfo = [
                'wx_openid' => $member->getWxOpenId() ? true : false,
                'alipay_openid' => $member->getAlipayUserId() ? true : false,
                'pay_password_status' => $member->payPasswordIsNull() ? 0 : 1
            ];
            $config = $withdraw->getConfig();
            $config['min_money'] = moneyCent2Yuan($config['min_money']);
            $config['max_money'] = moneyCent2Yuan($config['max_money']);
            $data = ['config' => $config,'accountInfo' => $accountInfo,'member_info' => $memberInfo, 'balance' => moneyCent2Yuan($balance)];
            try {
                $withdraw->checkWithdrawDate();
            }catch(\Exception $ex){
                return makeApiResponse(405, 'fail',$data);
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'),$data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 处理提现动作
     * request money 提现金额
     * request pay_type 提现到哪里
     */
    public function add(Request $request)
    {
        try {
            // 数据基础检测
            $type = 12;
            $payType = intval($request->pay_type);
            $money = moneyYuan2Cent($request->money);
            /*$member = new Member($this->memberId);
            if ($member->payPasswordIsNull()) {
                return makeApiResponse(402, trans('shop-front.shop.pay_password_error'));
            }
            // 验证支付密码
            if (!$member->payPasswordCheck($request->paypassword)) {
                return makeApiResponseFail(trans('shop-front.shop.pay_password_error'));
            }*/
            $withDrawInstance = WithdrawFactory::createInstance($type);
            $withDrawInstance->withdraw($type, $payType, $money, $this->memberId);
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'));
        } catch (\Exception $e) {
            DB::rollBack();
            return makeApiResponseError($e);
        }
    }

    /**
     * 提现管理导出
     * @param Request $request
     * @return array
     */
    public function export(Request $request)
    {
        try {
            $withdraw = new SupplierPlatformWithdraw($this->siteId,$this->memberId);
            $data = $withdraw->getList($request->all());
            $exportHeadings = [
                '提现单号',
                '提现时间',
                '提现金额',
                '到账金额',
                '手续费',
                '提到至',
                '提现状态',
                '提现帐户类型',
                '提现帐户名',
                '提现帐户',
            ];
            $exportData = [];
            $rowCount = count($data['list']);
            if ($data['list']) {
                foreach ($data['list'] as $item) {
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
                    $snapshot = $item->snapshot;
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
                        $item->tradeno,
                        $item->created_at,
                        $item->money,
                        $item->money_real,
                        $item->money_fee,
                        $withdrawFrom,
                        $item->status_text,
                        $bank,
                        $accountName,
                        $account,
                    ];
                }
            }
            $exportObj = new Export(new Collection($exportData), 'Huokuan-' . date("YmdHis") . '.xlsx', $exportHeadings);
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
}