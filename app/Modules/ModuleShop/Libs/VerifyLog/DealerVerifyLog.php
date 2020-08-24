<?php
/**
 * 操作记录抽象类
 * Created by wenke.
 */

namespace App\Modules\ModuleShop\Libs\VerifyLog;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerApplySetting;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Message\DealerMessageNotice;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use App\Modules\ModuleShop\Libs\Model\VerifyLogModel;

class DealerVerifyLog extends AbstractVerifyLog
{

    static function save(int $type, $DealerVerifymodel)
    {
        try {
            // 公司审核不需要记录
            if ($DealerVerifymodel->parent_review_member == 0 && $DealerVerifymodel->parent_review_status != Constants::DealerStatus_WaitReview && !$DealerVerifymodel->review_member_id) {
                return false;
            }
            //明天试试新增
            $params['site_id'] = self::getSiteId();
            $params['type'] = $type;
            if ($DealerVerifymodel->review_member_id) {

                if ($DealerVerifymodel->review_member_id == $DealerVerifymodel->invite_review_member) $inviteParams['status'] = $DealerVerifymodel->invite_review_status;
                if ($DealerVerifymodel->review_member_id == $DealerVerifymodel->parent_review_member) $parentParams['status'] = $DealerVerifymodel->parent_review_status;

                // 如果推荐人的审核是拒绝的,那么同步要把上级的审核记录也要变成拒绝的
                if ($DealerVerifymodel->review_member_id == $DealerVerifymodel->invite_review_member && $DealerVerifymodel->invite_review_status == -1) {
                    $parentParams['status'] = -1;
                }

                if ($DealerVerifymodel->invite_log_id) {
                    $VerifyLogModel = VerifyLogModel::query()
                        ->where('site_id', self::getSiteId())
                        ->where('id', $DealerVerifymodel->invite_log_id)
                        ->first();
                    $info = json_decode($VerifyLogModel->info, true);

                    $info['invite_review_status'] = $DealerVerifymodel->invite_review_status;

                    $info['invite_review_reject_reason'] = $DealerVerifymodel->invite_review_reject_reason;
                    $info['invite_review_passed_at'] = $DealerVerifymodel->invite_review_passed_at;
                    $info['parent_review_status'] = $DealerVerifymodel->parent_review_status;

                    $info['parent_review_reject_reason'] = $DealerVerifymodel->parent_review_reject_reason;
                    $info['parent_review_passed_at'] = $DealerVerifymodel->parent_review_passed_at;
                    $info['reject_reason'] = $DealerVerifymodel->reject_reason;
                    $info['status'] = $DealerVerifymodel->status;
                    $info['passed_at'] = $DealerVerifymodel->passed_at;
                    $inviteParams['info'] = json_encode($info);

                    $inviteParams['id'] = $VerifyLogModel->id;
                    if ($DealerVerifymodel->invite_review_status == Constants::DealerStatus_Active) {
                        //如果推荐人通过了,应该给上级经销商通知
                        $parentVerifyLogModel = VerifyLogModel::query()
                            ->where('site_id', self::getSiteId())
                            ->where('id', $DealerVerifymodel->parent_log_id)
                            ->first();
                        if ($parentVerifyLogModel && $DealerVerifymodel->invite_review_member != $DealerVerifymodel->parent_review_member) DealerMessageNotice::sendMessageDealerVerify($parentVerifyLogModel);
                    }
                    self::saveAct($inviteParams);
                }
                if ($DealerVerifymodel->parent_log_id) {
                    $VerifyLogModel = VerifyLogModel::query()
                        ->where('site_id', self::getSiteId())
                        ->where('id', $DealerVerifymodel->parent_log_id)
                        ->first();
                    $parentParams['id'] = $VerifyLogModel->id;
                    $info = json_decode($VerifyLogModel->info, true);

                    $info['invite_review_status'] = $DealerVerifymodel->invite_review_status;

                    $info['invite_review_reject_reason'] = $DealerVerifymodel->invite_review_reject_reason;
                    $info['invite_review_passed_at'] = $DealerVerifymodel->invite_review_passed_at;
                    $info['parent_review_status'] = $DealerVerifymodel->parent_review_status;

                    $info['parent_review_reject_reason'] = $DealerVerifymodel->parent_review_reject_reason;
                    $info['parent_review_passed_at'] = $DealerVerifymodel->parent_review_passed_at;
                    $info['reject_reason'] = $DealerVerifymodel->reject_reason;
                    $info['status'] = $DealerVerifymodel->status;
                    $info['passed_at'] = $DealerVerifymodel->passed_at;
                    $parentParams['info'] = json_encode($info);

                    self::saveAct($parentParams);
                }

            } else {
                if ($DealerVerifymodel->invite_review_member != 0 || $DealerVerifymodel->parent_review_member != 0) {
                    $memberIds = [];
                    if ($DealerVerifymodel->invite_review_member != 0) {
                        $VerifyLogInviteModel = VerifyLogModel::query()
                            ->where('site_id', self::getSiteId())
                            ->where('foreign_id', $DealerVerifymodel->member_id)
                            ->where('member_id', $DealerVerifymodel->invite_review_member)
                            ->where('status', 0)
                            ->where('type', $type)
                            ->first();
                        if (!$VerifyLogInviteModel) {
                            array_push($memberIds, $DealerVerifymodel->invite_review_member);
                        }
                    }
                    if ($DealerVerifymodel->parent_review_member != 0 && $DealerVerifymodel->invite_review_member != $DealerVerifymodel->parent_review_member) {
                        $VerifyLogParentModel = VerifyLogModel::query()
                            ->where('site_id', self::getSiteId())
                            ->where('foreign_id', $DealerVerifymodel->member_id)
                            ->where('member_id', $DealerVerifymodel->parent_review_member)
                            ->where('status', 0)
                            ->where('type', $type)
                            ->first();
                        if (!$VerifyLogParentModel) {
                            array_push($memberIds, $DealerVerifymodel->parent_review_member);
                        }
                    }
                }
                $logIdArr = [];
                foreach ($memberIds as $items) {
                    $member = new Member($DealerVerifymodel->member_id);
                    $memberModel = $member->getModel(false);
                    $params['member_id'] = $items;
                    $params['status'] = Constants::DealerStatus_WaitReview;
                    $dealer_apply_level = (new DealerLevel())->getLevelInfo($DealerVerifymodel->dealer_apply_level);
                    $params['info'] = json_encode(['dealer_apply_level' => $DealerVerifymodel->dealer_apply_level,
                        'dealer_apply_level_name' => $dealer_apply_level->name,
                        'nickname' => $memberModel->nickname,
                        'headurl' => $memberModel->headurl,
                        'member_id' => $DealerVerifymodel->member_id,
                        'created_at' => $DealerVerifymodel->created_at,
                        'verify_process' => $DealerVerifymodel->verify_process,
                        'invite_review_status' => $DealerVerifymodel->invite_review_status,
                        'invite_review_member' => $DealerVerifymodel->invite_review_member,
                        'invite_review_reject_reason' => $DealerVerifymodel->invite_review_reject_reason,
                        'invite_review_passed_at' => $DealerVerifymodel->invite_review_passed_at,
                        'parent_review_status' => $DealerVerifymodel->parent_review_status,
                        'parent_review_member' => $DealerVerifymodel->parent_review_member,
                        'parent_review_reject_reason' => $DealerVerifymodel->parent_review_reject_reason,
                        'parent_review_passed_at' => $DealerVerifymodel->parent_review_passed_at,
                        'passed_at' => $DealerVerifymodel->passed_at,
                        'reject_reason' => $DealerVerifymodel->reject_reason,
                        'status' => $DealerVerifymodel->status
                    ]);
                    $params['foreign_id'] = $DealerVerifymodel->member_id;
                    $params['from_member_id'] = $memberModel->id;
                    $logId = self::saveAct($params);
                    if ($items == $DealerVerifymodel->invite_review_member) $logIdArr['invite_log_id'] = $logId;
                    if ($items == $DealerVerifymodel->parent_review_member) $logIdArr['parent_log_id'] = $logId;
                }
                return $logIdArr;
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }


    static function getInfo($VerifyLogModel, $memberId)
    {
        $info = json_decode($VerifyLogModel['info'], true);
        //
        if (($info['parent_review_member'] == $memberId && $info['parent_review_member'] != $info['invite_review_member'] && $info['invite_review_status'] == Constants::DealerStatus_Active && $info['parent_review_status'] == Constants::DealerStatus_WaitReview)
            || ($info['invite_review_status'] == Constants::DealerStatus_WaitReview && $info['invite_review_member'] == $memberId)
            || ($info['invite_review_member'] == 0 && $info['parent_review_status'] == Constants::DealerStatus_WaitReview && $info['parent_review_member'] != 0)
        ) {
            $VerifyLogModel->review_status = true;
        } else {
            $VerifyLogModel->review_status = false;
        }
        $info['parent_nickname'] = '';
        if ($info['parent_review_member']) {
            $parent_review_member = (new Member($info['parent_review_member']))->getModel();
            $info['parent_nickname'] = $parent_review_member->nickname;
        }
        $info['invite_nickname'] = '';
        if ($info['invite_review_member']) {
            $parent_review_member = (new Member($info['invite_review_member']))->getModel();
            $info['invite_nickname'] = $parent_review_member->nickname;
        }

        $foreign_member = (new Member($VerifyLogModel->foreign_id))->getModel();
        $info['nickname'] = $foreign_member->nickname;
        $info['headurl'] = $foreign_member->headurl;

        $VerifyLogModel->info = $info;
        return $VerifyLogModel;
    }
}