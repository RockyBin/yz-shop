<?php

namespace App\Modules\ModuleShop\Libs\Message;

use App\Modules\ModuleShop\Libs\AreaAgent\AreaAgentConstants;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentApplyModel;
use App\Modules\ModuleShop\Libs\Model\AreaAgent\AreaAgentModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use YZ\Core\Constants as CodeConstants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use YZ\Core\Logger\Log;
use YZ\Core\Model\DistrictModel;
use YZ\Core\Model\MemberModel;

class AreaAgentMessageNotice extends AbstractMessageNotice
{
    /**
     * 成为区域代理通知
     * @param AreaAgentModel $areaAgentModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAreaAgentAgree(AreaAgentModel $areaAgentModel)
    {
        try {
            if (!$areaAgentModel) return false;
            $member = new Member($areaAgentModel->member_id);
            if (!$member->checkExist()) return false;
            $areaAgent = AreaAgentModel::query()
                ->where('id', $areaAgentModel->id)
                ->first();
            // 数据结构
            $areaType = AreaAgentConstants::getAreaTypeText($areaAgent->area_type);
            switch (true) {
                case $areaAgent->area_type == AreaAgentConstants::AreaAgentLevel_Province :
                    $districtId = $areaAgent->prov;
                    break;
                case $areaAgent->area_type == AreaAgentConstants::AreaAgentLevel_City :
                    $districtId = $areaAgent->city;
                    break;
                case $areaAgent->area_type == AreaAgentConstants::AreaAgentLevel_District :
                    $districtId = $areaAgent->district;
                    break;
            }
            $district = DistrictModel::query()->where('id', $districtId)->pluck('name')->first();
            $param = [
                'url' => '/shop/front/#/areaagent/areaagent-center',
                'openId' => self::getMemberWxOpenId($areaAgentModel->member_id),
                'mobile' => self::getMemberMobile($areaAgentModel->member_id),
                'shop_name' => self::getShopName(),
                'member_nickname' => $member->getModel()->nickname,
                'change_type' => '成为区域代理',
                'wx_content_first' => '亲，恭喜您已成为区域代理！代理区域为<' . $areaType . '：' . $district . '>'
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Area_Agent_Agree, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAreaAgentAgree:' . $ex->getMessage());
        }
    }


    /**
     * 申请区域代理被拒通知
     * @param AreaAgentApplyModel $areaAgentApplyModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAreaAgentReject(AreaAgentApplyModel $areaAgentApplyModel)
    {
        try {
            if (!$areaAgentApplyModel) return false;
            $member = new Member($areaAgentApplyModel->member_id);
            if (!$member->checkExist()) return false;
            // 数据结构
            $reject_reason = $areaAgentApplyModel->reject_reason;
            $param = [
                'url' => '/shop/front/#/areaagent/areaagent-apply',
                'openId' => self::getMemberWxOpenId($areaAgentApplyModel->member_id),
                'mobile' => self::getMemberMobile($areaAgentApplyModel->member_id),
                'apply_type' => '区域代理审核',
                'reject_reason' => $reject_reason,
                'wx_content_first' => '亲，非常抱歉您的区域代理申请未通过审核！',
                'sms_content' => '亲，非常抱歉您的区域代理申请未通过审核！',
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Area_Agent_Reject, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAreaAgentReject:' . $ex->getMessage());
        }
    }


}