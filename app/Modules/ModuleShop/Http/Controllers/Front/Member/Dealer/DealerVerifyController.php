<?php
/**
 * 经销商申请相关接口
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Dealer;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\CloudStock\AdminPurchaseOrder;
use App\Modules\ModuleShop\Libs\CloudStock\FrontPurchaseOrder;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\Dealer;
use App\Modules\ModuleShop\Libs\Dealer\DealerApplySetting;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use App\Modules\ModuleShop\Libs\VerifyLog\DealerVerifyLog;
use App\Modules\ModuleShop\Libs\VerifyLog\VerifyLog;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Member\Member;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use Illuminate\Http\Request;


class DealerVerifyController extends BaseController
{
    public function getList(Request $request)
    {
        $params = $request->toArray();
        $params['member_id'] = $this->memberId;
        $data = VerifyLog::getList($params);
        return makeApiResponse(200, '', $data);
    }

    public function getFromMemberList(Request $request)
    {
        $params = $request->toArray();
        $params['from_member_id'] = $this->memberId;
        $data = VerifyLog::getList($params);
        return makeApiResponse(200, '', $data);
    }

    public function verify(Request $request)
    {
        try {
            $params = $request->toArray();
            $type = $request->type;
            //当前审核人的会员ID
            $params['review_member_id'] = $this->memberId;
            //当前被审核会员ID
            if ($type == Constants::VerifyLogType_DealerVerify) {
                $dealer = new Dealer();
                $params['log_id'] = $params['log_id'];
                $logModel = VerifyLogModel::query()->where('site_id', $this->siteId)->where('id', $params['log_id'])->first();
                $params['member_id'] = $logModel->foreign_id;
                if (!$logModel) {
                    $params['member_id'] = $params['from_member_id'];
                }
                $dealer->frontVerifyDealer($params);
            } else if ($type == Constants::VerifyLogType_CloudStockPurchaseOrderFinanceVerify) {
                $orderId = $params['id'];
                $review_status = $params['status']; // 审核状态
                $paymentStatus = $params['status'] == 1 ? Constants::CloudStockPurchaseOrderPaymentStatus_Yes : Constants::CloudStockPurchaseOrderPaymentStatus_Refuse; // 是否确认收到了货款
                $remark = $params['reject_reason'] ?: '';
                (new FrontPurchaseOrder())->financeReview($orderId, $review_status, $paymentStatus, trim($remark), $params['review_member_id']);
            } else if ($type == Constants::VerifyLogType_BalanceVerify) {
                VerifyLog::Log($type, $params);

            }

            return makeApiResponse(200, 'ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }

    }

    public function getInfo(Request $request)
    {
        $dealer = new Dealer();
        //被审人的ID
        $params['log_id'] = $request->log_id;
        $params['dealer_id'] = $request->dealer_id;
        // $data = $dealer->getInfo($params);
        $VerifyLogModel = VerifyLogModel::query()
            ->where('tbl_verify_log.site_id', Site::getCurrentSite()->getSiteId())
            ->where('tbl_verify_log.id', $params['log_id'])
            ->first();
        if (!$VerifyLogModel) {
            return makeApiResponseFail('无此审核记录');
        }
        // 审核人ID
        $member_id = $this->memberId;
        $LogInfo = VerifyLog::getInfo($VerifyLogModel->type, $VerifyLogModel, $member_id);
        $data = $LogInfo;
        $data['review_status'] = $LogInfo->review_status;
        return makeApiResponse(200, '', $data);
    }

    public function getFromMemberInfo(Request $request)
    {
        $params['log_id'] = $request->log_id;
        $VerifyLogModel = VerifyLogModel::query()
            ->where('tbl_verify_log.site_id', Site::getCurrentSite()->getSiteId())
            ->where('tbl_verify_log.id', $params['log_id'])
            ->first();
        if (!$VerifyLogModel) {
            return makeApiResponseFail('无此审核记录');
        }
        // 审核人ID
        $LogInfo = VerifyLog::getInfo($VerifyLogModel->type, $VerifyLogModel, $VerifyLogModel->member_id);
        $data = $LogInfo;
        $data['review_status'] = $LogInfo->review_status;
        return makeApiResponse(200, '', $data);
    }
}