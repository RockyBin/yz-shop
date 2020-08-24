<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Distribution\Become;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\DistributorModel;

class BecomeDistributorHelper
{
    /**
     * 提交成为分销商申请（用于会员主动提交申请）
     * @param $memberModal
     * @param $terminalType
     * @param array $extendData
     * @return array
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function applyBecomeDistributor($memberModal, $terminalType, $extendData = [])
    {
        $distributionSetting = new DistributionSetting();
        if (is_numeric($memberModal)) {
            $memberModal = new Member($memberModal);
        }
        $handle = self::getBecomeDistributor($memberModal, $distributionSetting);
        $handle->setExtendData($extendData);
        $handle->setTerminalType($terminalType);

        // 申请
        $applyResult = $handle->apply();
        // 如果申请成功，尝试自动审核
        if ($applyResult) {
            $handle->autoCheck();
        }
        $distributorStatus = Constants::DistributorStatus_Null;
        $memberModel = $handle->getMemberModel();
        if ($memberModel) {
            $distributorModel = DistributorModel::query()
                ->where('site_id', $memberModel->site_id)
                ->where('member_id', $memberModel->id)
                ->first();
            if ($distributorModel) {
                $distributorStatus = intval($distributorModel->status);
            }
        }
        $data = array_merge($handle->getExtendData(), [
            'condition_type' => $handle->getConditionType(),
            'distributor_status' => $distributorStatus,
        ]);
        return makeServiceResult($applyResult ? 200 : 400, $handle->getErrorMsg(), $data);
    }

    /**
     * 检测申请的时候是否有被拒绝过
     * @param $memberModal
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function checkApply($memberModel)
    {
        $member = $memberModel->getModel();
        if ($member) {
            $distributorModel = DistributorModel::query()
                ->where('site_id', $member->site_id)
                ->where('member_id', $member->id)
                ->first();
            if ($distributorModel) {
                $distributorStatus = intval($distributorModel->status);
                $reviewType = (new DistributionSetting())::getCurrentSiteSetting()->review_type;
                if ($distributorStatus == Constants::DistributorStatus_RejectReview && $reviewType == 0) {
                    $data['distributor_status'] = $distributorStatus;
                    $data['reject_reason'] = $distributorModel->reject_reason;
                    return $data;
                }
            }
        }
        return false;
    }

    /**
     * 订单类的申请
     * @param $memberModal
     * @param $terminalType
     * @param $periodFlag
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function applyBecomeDistributorForOrder($memberModal, $terminalType, $periodFlag)
    {
        $distributionSetting = new DistributionSetting();
        $handle = self::getBecomeDistributor($memberModal, $distributionSetting);
        if ($handle->getPeriodFlag() == $periodFlag && $handle->getConditionType() == Constants::DistributionCondition_BuyProduct) {
            $handle->setTerminalType($terminalType);
            // 申请
            $applyResult = $handle->apply();
            // 如果申请成功，尝试自动审核
            if ($applyResult) {
                $handle->autoCheck();
            }
        }
    }

    /**
     * 根据配置查找成为分销商的类
     * @param $memberModal
     * @param null $distributionSetting
     * @return BecomeDistributorNoCondition
     */
    private static function getBecomeDistributor($memberModal, $distributionSetting = null)
    {
        if (is_numeric($memberModal)) {
            $memberModal = new Member($memberModal);
        }
        if (!$distributionSetting) {
            $distributionSetting = new DistributionSetting();
        }
        $setting = $distributionSetting->getSettingModel();
        if ($setting->condition == Constants::DistributionCondition_None) {
            return new BecomeDistributorNoCondition($memberModal, $distributionSetting);
        } else if ($setting->condition == Constants::DistributionCondition_Apply) {
            return new BecomeDistributorFormApply($memberModal, $distributionSetting);
        } else if ($setting->condition == Constants::DistributionCondition_BuyTimes) {
            return new BecomeDistributorBuyTimes($memberModal, $distributionSetting);
        } else if ($setting->condition == Constants::DistributionCondition_BuyMoney) {
            return new BecomeDistributorBuyMoney($memberModal, $distributionSetting);
        } else if ($setting->condition == Constants::DistributionCondition_BuyProduct) {
            return new BecomeDistributorBuyProduct($memberModal, $distributionSetting);
        }else if($setting->condition == Constants::DistributionCondition_DirectlyMember){
            return new BecomeDistributorDirectlyMember($memberModal, $distributionSetting);
        } else {
            return new BecomeDistributorError($memberModal, $distributionSetting);
        }
    }
}