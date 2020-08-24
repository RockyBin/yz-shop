<?php
/**
 * Created by Wenke.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Admin\Finance;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use App\Modules\ModuleShop\Libs\VerifyLog\VerifyLog;
use Illuminate\Http\Request;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Site\Site;
use YZ\Core\Constants as CoreConstants;
use YZ\Core\Common\Export;
use App\Modules\ModuleShop\Http\Controllers\Admin\BaseAdminController;
use App\Modules\ModuleShop\Libs\Finance\Balance;
use Illuminate\Support\Collection;

class RechargeController extends BaseAdminController
{
    private $siteId = 0;

    /**
     * 初始化
     * MemberController constructor.
     */
    public function __construct()
    {
        $this->siteId = Site::getCurrentSite()->getSiteId();
    }


    public function getList(Request $request)
    {
        $params = $request->toArray();
        $params['member_id'] = 0;
        $params['type'] = Constants::VerifyLogType_BalanceVerify;
        $list = VerifyLog::getList($params);

        return makeApiResponse(200, '', $list);
    }

    public function getInfo(Request $request)
    {
        $VerifyLogModel = VerifyLogModel::query()
            ->where('tbl_verify_log.site_id', Site::getCurrentSite()->getSiteId())
            ->where('tbl_verify_log.id', $request->log_id)
            ->first();
        if (!$VerifyLogModel) {
            return makeApiResponseFail('无此审核记录');
        }
        $data = VerifyLog::getInfo(Constants::VerifyLogType_BalanceVerify, $VerifyLogModel, 0);
        return makeApiResponse(200, '', $data);
    }


    public function verify(Request $request)
    {
        try {
            $params = $request->toArray();
            VerifyLog::Log(Constants::VerifyLogType_BalanceVerify, $params);
            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function getBalanceList(Request $request)
    {
        try {
            $params = $request->toArray();
            $list = FinanceHelper::getFinanceBalanceList($params);
            return makeApiResponse(200, '', $list);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 导出余额列表
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportBalanceList(Request $request)
    {
        try {
            $params = $request->toArray();
            $data = FinanceHelper::getFinanceBalanceList($params);

            $exportHeadings = [
                '会员ID',
                '会员昵称',
                '会员姓名',
                '会员手机号',
                '可用余额',
                '累计充值',
                '已提现',
                '提现中'
            ];
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as &$item) {
                    $exportData[] = [
                        $item->id,
                        $item->nickname,
                        $item->name,
                        "\t" . $item->mobile . "\t",
                        $item->available_balance,
                        $item->cumulative_recharge ?: '0',
                        $item->withdrawal_done ?: '0',
                        $item->withdrawal_ing ?: '0',
                    ];
                }
            }
            $exportObj = new Export(new Collection($exportData), 'Yue-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }


    /**
     * 导出余额审核列表
     * @param Request $request
     * @return array|\Maatwebsite\Excel\BinaryFileResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportVerifyBalanceList(Request $request)
    {
        try {
            $params = $request->toArray();
            $params['member_id'] = 0;
            $params['type'] = Constants::VerifyLogType_BalanceVerify;
            $data = VerifyLog::getList($params);

            $exportHeadings = [
                '会员ID',
                '会员昵称',
                '会员姓名',
                '会员手机号',
                '充值金额',
                '赠送金额',
                '充值支付方式',
                '充值时间',
                '状态'
            ];
            $exportData = [];
            if ($data['list']) {
                foreach ($data['list'] as &$item) {
                    $info = $item['info'];
                    $exportData[] = [
                        $item->from_member_id,
                        $item->nickname,
                        $item->name,
                        "\t" . $item->mobile . "\t",
                        $info->money,
                        $info->recharge_bonus ? $info->recharge_bonus['bonus'] : 0,
                        CoreConstants::getPayTypeTextTwo($info->pay_type),
                        $item->created_at,
                        $item->status == 0 ? '待审核' : ($item->status == 1 ? "审核通过" : "拒绝审核")
                    ];
                }
            }
            $exportObj = new Export(new Collection($exportData), 'Yueshenhe-' . date("YmdHis") . '.xlsx', $exportHeadings);
            return $exportObj->export();

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}