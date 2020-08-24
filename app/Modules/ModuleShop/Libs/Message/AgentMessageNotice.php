<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Libs\Message;

use YZ\Core\Constants as CodeConstants;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\AgentModel;
use YZ\Core\Logger\Log;
use YZ\Core\Model\FinanceModel;
use YZ\Core\Model\MemberModel;


class AgentMessageNotice extends AbstractMessageNotice
{
    public static function sendMessageAgentAgree(AgentModel $agentModel)
    {
        try {
            if (!$agentModel) return false;
            $member = new Member($agentModel->member_id);
            if (!$member->checkExist()) return false;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/agent/agent-center',
                'openId' => self::getMemberWxOpenId($agentModel->member_id),
                'mobile' => self::getMemberMobile($agentModel->member_id),
                'shop_name' => self::getShopName(),
                'member_nickname' => $member->getModel()->nickname,
                'change_type' => '成为' . trans("shop-front.diy_word.team_agent"),
                'wx_content_first' => '亲，恭喜您已成功成为' . trans("shop-front.diy_word.team_agent"),
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Agent_Agree, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentAgree:' . $ex->getMessage());
        }
    }


    /**
     * 申请代理被拒通知
     * @param AgentModel $agentModel
     * @return bool
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentReject(AgentModel $agentModel)
    {
        try {
            if (!$agentModel) return false;
            $member = new Member($agentModel->member_id);
            if (!$member->checkExist()) return false;
            // 数据结构
            $param = [
                'url' => '/shop/front/#/member/member-center',
                'openId' => self::getMemberWxOpenId($agentModel->member_id),
                'mobile' => self::getMemberMobile($agentModel->member_id),
                'apply_type' => trans("shop-front.diy_word.team_agent") . '审核',
                'reject_reason' => $agentModel->reject_reason,
                'wx_content_first' => '亲，非常抱歉您的' . trans("shop-front.diy_word.team_agent") . '申请未通过审核！',
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Agent_Reject, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentReject:' . $ex->getMessage());
        }
    }

    /**
     * 代理等级变动通知
     * @param MemberModel $memberModel
     * @param int $oldAgentLevel
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentLevelUpgrade(MemberModel $memberModel, $oldAgentLevel = 0)
    {
        try {
            if (!$memberModel) return;
            $agentLevel = intval($memberModel->agent_level);
            if ($oldAgentLevel == 0 || $agentLevel == 0 || $agentLevel >= $oldAgentLevel) return; // 比原来低级，则不通知
            // 数据结构
            $agentLevelName = Constants::getAgentLevelTextForFront($agentLevel);
            $param = [
                'url' => '/shop/front/#/agent/agent-center',
                'openId' => self::getMemberWxOpenId($memberModel->id),
                'mobile' => self::getMemberMobile($memberModel->id),
                'member_nickname' => $memberModel->nickname,
                'member_agent_level' => $agentLevelName,
                'change_type' => trans("shop-front.diy_word.team_agent") . '升级',
                'wx_content_first' => '亲，恭喜您的' . trans("shop-front.diy_word.team_agent") . '等级升至' . $agentLevelName,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Agent_LevelUpgrade, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentLevelUpgrade:' . $ex->getMessage());
        }
    }

    /**
     * 成员的代理等级变动通知
     * @param MemberModel $memberModel 通知的团队上级
     * @param MemberModel $subMemberModel 团队下级
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentSubMemberLevelUpgrade(MemberModel $memberModel, MemberModel $subMemberModel)
    {
        try {
            if (!$memberModel || !$subMemberModel) return;
            if (intval($memberModel->agent_level) == 0 || intval($subMemberModel->agent_level) == 0) return;
            // 数据结构
            $subMemberAgentLevelName = Constants::getAgentLevelTextForFront(intval($subMemberModel->agent_level));
            $wxContentFirst = '亲，恭喜您，您的' . trans("shop-front.diy_word.team_agent_member") . '已升级为' . $subMemberAgentLevelName . '！';
            $smsContent = '恭喜您，您的' . trans("shop-front.diy_word.team_agent_member") . $subMemberModel->nickname . '，升级为' . $subMemberAgentLevelName . '！';
            if (intval($memberModel->agent_level) == intval($subMemberModel->agent_level)) {
                // 平级
                $wxContentFirst = '亲，您的成员已升级为' . $subMemberAgentLevelName . '，与你平级啦！';
                $smsContent = '您的' . trans("shop-front.diy_word.team_agent_member") . $subMemberModel->nickname . '，升级为' . $subMemberAgentLevelName . '，与你平级啦！';
            } else if (intval($memberModel->agent_level) > intval($subMemberModel->agent_level)) {
                // 越级
                $wxContentFirst = '亲，您的成员已升级为' . $subMemberAgentLevelName . '，越级升级啦！';
                $smsContent = '您的' . trans("shop-front.diy_word.team_agent_member") . $subMemberModel->nickname . '，升级为' . $subMemberAgentLevelName . '，越级升级啦！';
            }
            $param = [
                'url' => '/shop/front/#/agent/agent-center',
                'openId' => self::getMemberWxOpenId($memberModel->id),
                'mobile' => self::getMemberMobile($memberModel->id),
                'member_nickname' => $subMemberModel->nickname,
                'change_type' => trans("shop-front.diy_word.team_agent_member") . '升级',
                'wx_content_first' => $wxContentFirst,
                'sms_content' => $smsContent,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_AgentSubMember_LevelUpgrade, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentSubMemberLevelUpgrade:' . $ex->getMessage());
        }
    }

    /**
     * 团队分红通知
     * @param FinanceModel $financeModel
     * @throws \Illuminate\Support\Facades\SuspiciousOperationException
     */
    public static function sendMessageAgentCommission(FinanceModel $financeModel)
    {
        try {
            if (!$financeModel || intval($financeModel->money) <= 0 || intval($financeModel->type) != CodeConstants::FinanceType_AgentCommission) return;
            if (intval($financeModel->status) == CodeConstants::FinanceStatus_Invalid) return;
            $money = moneyCent2Yuan($financeModel->money);
            $wxContentFirst = '';
            $smsContent = '';
            $source = '';
            $subType = intval($financeModel->sub_type);
            if ($subType == CodeConstants::FinanceSubType_AgentCommission_Order) {
                $source = '订单' . trans("shop-front.diy_word.agent_reward");
                if (intval($financeModel->status) == CodeConstants::FinanceStatus_Active) {
                    $wxContentFirst = '亲，恭喜您，您的团队完成了一笔订单了，新增收入如下：';
                    $smsContent = '恭喜您，您的团队完成了一笔订单，新增收入为' . $money.'元';
                } else {
                    $wxContentFirst = '亲，恭喜您，您的团队新增了一笔订单，预计收入如下：';
                    $smsContent = '恭喜您，您的团队新增了一笔订单，预计收入为' . $money.'元';
                }
            } else if ($subType == CodeConstants::FinanceSubType_AgentCommission_SaleReward) {
                $source = '订单' . trans("shop-front.diy_word.team_agent_sale_reward");
                if (intval($financeModel->status) == CodeConstants::FinanceStatus_Active) {
                    $wxContentFirst = '亲，恭喜您，成功获得到了一笔订单' . trans("shop-front.diy_word.team_agent_sale_reward") . '，新增收入如下：';
                    $smsContent = '恭喜您，新增一笔订单' . trans("shop-front.diy_word.team_agent_sale_reward") . '，新增收入为' . $money.'元';
                } else {
                    $wxContentFirst = '亲，恭喜您，新增一笔订单' . trans("shop-front.diy_word.team_agent_sale_reward") . '，预计收入如下：';
                    $smsContent = '恭喜您，获得到了一笔订单' . trans("shop-front.diy_word.team_agent_sale_reward") . '，预计收入为' . $money.'元';
                }
            } else if ($subType == CodeConstants::FinanceSubType_AgentCommission_Recommend) {
                $source = trans("shop-front.diy_word.team_agent_recommend_reward");
                $wxContentFirst = '亲，恭喜您，您已成功推荐了一个' . trans("shop-front.diy_word.team_agent") . '，获得一笔' . trans("shop-front.diy_word.team_agent_recommend_reward") . '奖金！';
                $smsContent = '恭喜您，您已成功推荐了一个' . trans("shop-front.diy_word.team_agent") . '，获得一笔' . trans("shop-front.diy_word.team_agent_recommend_reward") . '奖金，收入为' . $money.'元';
            } else if ($subType == CodeConstants::FinanceSubType_AgentCommission_Performance) {
                $source = trans("shop-front.diy_word.team_agent_performance_reward");
                $period = '';
                // PERFORMANCE_REWARD_1_2019_2
                $financeOrderId = $financeModel->order_id;
                if ($financeOrderId) {
                    $periodParam = explode('_', substr($financeOrderId, 19), 3);
                    if ($periodParam[0] == 2) {
                        $period = $periodParam[1] . '年';
                    } else if ($periodParam[0] == 1) {
                        $period = $periodParam[1] . '年第' . $periodParam[2] . '季度';
                    } else {
                        $period = $periodParam[1] . '年' . $periodParam[2] . '月';
                    }
                }
                $wxContentFirst = '亲，恭喜您，完成了' . $period . '的目标，获得到了一笔' . trans("shop-front.diy_word.team_agent_performance_reward") . '奖金！';
                $smsContent = '恭喜您，完成了' . $period . '的目标，获得到了一笔' . trans("shop-front.diy_word.team_agent_performance_reward") . '奖金，收入为¥' . $money;
            } else {
                return;
            }
            $param = [
                'url' => '/shop/front/#/agent/agent-reward',
                'openId' => self::getMemberWxOpenId($financeModel->member_id),
                'mobile' => self::getMemberMobile($financeModel->member_id),
                'money' => $money,
                'source' => $source,
                'time' => intval($financeModel->status) == CodeConstants::FinanceStatus_Active ? $financeModel->active_at : $financeModel->created_at,
                'wx_content_first' => $wxContentFirst,
                'sms_content' => $smsContent,
            ];
            // 发送消息
            self::sendMessage(CodeConstants::MessageType_Agent_Commission, $param);
        } catch (\Exception $ex) {
            Log::writeLog('message', 'sendMessageAgentCommission:' . $ex->getMessage());
        }
    }

}