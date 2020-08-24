<?php

namespace App\Modules\ModuleShop\Libs\Message;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;
use YZ\Core\Constants as CodeConstants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;

class DealerMessageNotice extends AbstractMessageNotice
{
    /**
     * 成为经销商通知
     * @param DealerModel $dealerModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageDealerAgree(DealerModel $dealerModel)
    {
        try {
            if (!$dealerModel) return false;
            $member = new Member($dealerModel->member_id);
            if (!$member->checkExist()) return false;
            $dealer = DealerLevelModel::query()
                ->where('id', $member->getModel()->dealer_level)
                ->first();
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/member-center',
                'openId' => self::getMemberWxOpenId($dealerModel->member_id),
                'mobile' => self::getMemberMobile($dealerModel->member_id),
                'shop_name' => self::getShopName(),
                'member_nickname' => $member->getModel()->nickname,
                'change_type' => '成为' . trans("shop-front.diy_word.team_dealer"),
                'wx_content_first' => '亲，恭喜您已成功成为' . trans("shop-front.diy_word.team_dealer") . '!当前等级为' . $dealer->name,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Dealer_Agree, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentAgree:' . $ex->getMessage());
        }
    }


    /**
     * 申请经销商被拒通知
     * @param AgentModel $agentModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageDealerReject(DealerModel $dealerModel)
    {
        try {
            if (!$dealerModel) return false;
            $member = new Member($dealerModel->member_id);
            if (!$member->checkExist()) return false;
            // 数据结构
            $reject_reason = $dealerModel->invite_review_reject_reason ? $dealerModel->invite_review_reject_reason : ($dealerModel->parent_review_reject_reason ? $dealerModel->parent_review_reject_reason : $dealerModel->reject_reason);
            $param = [
                'url' => '/shop/front/#/member/member-center',
                'openId' => self::getMemberWxOpenId($dealerModel->member_id),
                'mobile' => self::getMemberMobile($dealerModel->member_id),
                'apply_type' => trans("shop-front.diy_word.team_dealer") . '审核',
                'reject_reason' => $reject_reason,
                'wx_content_first' => '亲，非常抱歉您的' . trans("shop-front.diy_word.team_dealer") . '申请未通过审核！',
                'sms_content' => '亲，非常抱歉您的' . trans("shop-front.diy_word.team_dealer") . '申请未通过审核！',
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Dealer_Reject, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageDealerReject:' . $ex->getMessage());
        }
    }

    /**
     * 经销商等级变动通知
     * @param MemberModel $memberModel
     * @param int $oldDealerLevel
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageDealerLevelUpgrade(MemberModel $memberModel, $oldDealerLevel = 0)
    {
        try {
            if (!$memberModel) return;
            $dealerLevel = intval($memberModel->dealer_level);
            if ($oldDealerLevel == 0 || $dealerLevel == 0 || $dealerLevel >= $oldDealerLevel) return; // 比原来低级，则不通知
            // 数据结构
            $dealerLevelName = DealerLevelModel::find($dealerLevel)->name;
            $param = [
                'url' => '/shop/front/#/cloudstock/cloud-center',
                'openId' => self::getMemberWxOpenId($memberModel->id),
                'mobile' => self::getMemberMobile($memberModel->id),
                'member_nickname' => $memberModel->nickname,
                'dealer_level_name' => $dealerLevelName,
                'change_type' => trans("shop-front.diy_word.team_dealer") . '升级',
                'wx_content_first' => '亲，恭喜您的' . trans("shop-front.diy_word.team_dealer") . '等级升至' . $dealerLevelName,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Dealer_LevelUpgrade, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageDealerLevelUpgrade:' . $ex->getMessage());
        }
    }

    /**
     * 成员的经销商等级变动通知
     * @param MemberModel $memberModel 通知的经销商上级
     * @param MemberModel $subMemberModel 经销下级
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageDealerSubMemberLevelUpgrade(MemberModel $memberModel, MemberModel $subMemberModel)
    {
        try {
            if (!$memberModel || !$subMemberModel) return;
            if (intval($memberModel->dealer_level) == 0 || intval($subMemberModel->dealer_level) == 0) return;
            // 数据结构
            $subMemberDealerLevelName = DealerLevelModel::find($subMemberModel->dealer_level)->name;
            $wxContentFirst = '亲，恭喜您，您的' . trans("shop-front.diy_word.team_dealer_member") . '已升级为' . $subMemberDealerLevelName . '！';
            $smsContent = '恭喜您，您的' . trans("shop-front.diy_word.team_dealer_member") . $subMemberModel->nickname . '，升级为' . $subMemberDealerLevelName . '！';
            if (intval($memberModel->dealer_level) == intval($subMemberModel->dealer_level)) {
                // 平级
                $wxContentFirst = '亲，您的成员已升级为' . $subMemberDealerLevelName . '，与你平级啦！';
                $smsContent = '您的' . trans("shop-front.diy_word.team_dealer_member") . $subMemberModel->nickname . '，升级为' . $subMemberDealerLevelName . '，与你平级啦！';
            } else if (intval($memberModel->dealer_level) > intval($subMemberModel->dealer_level)) {
                // 越级
                $wxContentFirst = '亲，您的成员已升级为' . $subMemberDealerLevelName . '，越级升级啦！';
                $smsContent = '您的' . trans("shop-front.diy_word.team_dealer_member") . $subMemberModel->nickname . '，升级为' . $subMemberDealerLevelName . '，越级升级啦！';
            }
            $param = [
                'url' => '/shop/front/#/cloudstock/cloud-center',
                'openId' => self::getMemberWxOpenId($memberModel->id),
                'mobile' => self::getMemberMobile($memberModel->id),
                'member_nickname' => $subMemberModel->nickname,
                'change_type' => trans("shop-front.diy_word.team_dealer_member") . '升级',
                'wx_content_first' => $wxContentFirst,
                'sms_content' => $smsContent,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_AgentSubMember_LevelUpgrade, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageDealerSubMemberLevelUpgrade:' . $ex->getMessage());
        }
    }


    /**
     * 审核信息发送
     * @param DealerModel $verifyLogModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageDealerVerify(VerifyLogModel $verifyLogModel)
    {
        try {
            if (!$verifyLogModel) return false;
            $member = new Member($verifyLogModel->foreign_id);
            if (!$member->checkExist()) return false;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/dealer/dealer-verify',
                'openId' => self::getMemberWxOpenId($verifyLogModel->member_id),
                'mobile' => self::getMemberMobile($verifyLogModel->member_id),
                'member_nickname' => $member->getModel()->nickname,
                'verify_type' => $verifyLogModel->type == Constants::VerifyLogType_CloudStockPurchaseOrderFinanceVerify ? '云仓订单货款审核' : '经销商审核'
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Dealer_Verify, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageDealerReject:' . $ex->getMessage());
        }
    }
}