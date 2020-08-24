<?php
/**
 * 代理加盟设置接口
 * User: liyaohui
 * Date: 2019/7/01
 * Time: 11:09
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Agent;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Agent\AgentApplySetting;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\Agent\AgentOrderCommisionConfig;
use App\Modules\ModuleShop\Libs\Agent\AgentOtherRewardSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceRewardSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentRecommendRewardSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentSaleRewardSetting;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\SiteConfig\StoreConfig;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\Agent\Agent;
use App\Modules\ModuleShop\Libs\Model\AgentModel;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use YZ\Core\Constants as YZConstants;
use YZ\Core\Model\MemberModel;
use YZ\Core\Payment\Payment;
use YZ\Core\Site\Site;

class AgentController extends BaseController
{
    /**
     * 申请时上传的文件
     * @param Request $request
     * @return array
     */
    public function applyAgentFile(Request $request)
    {
        try {
            $memberId = $this->memberId;
            // 检测会员是否存在 是否申请过代理
            $agent = new Agent();
            $existAgent = $agent->becomeAgentBefore(['member_id' => $memberId], true);
            if ($existAgent && $existAgent->status != Constants::AgentStatus_RejectReview && $existAgent->status != Constants::AgentStatus_Applying) {
                return makeApiResponse(400, '您已申请过代理');
            }
            $file = [];
            if ($request->hasFile('idcard_file_data')) {
                $file['idcard_file_data'] = $agent->uploadFile($request->file('idcard_file_data'), $memberId, 'idcard');
            }
            if ($request->hasFile('business_license_file_data')) {
                $file['business_license_file_data'] = $agent->uploadFile($request->file('business_license_file_data'), $memberId, 'business_license');
            }
            if ($request->hasFile('initial_pay_certificate')) {
                $initialPayCertificate = $request->file('initial_pay_certificate');
                foreach ($initialPayCertificate as $item) {
                    $file['initial_pay_certificate'][] = $agent->uploadFile($item, $memberId, 'initial_pay_certificate');
                }
                $file['initial_pay_certificate'] = implode(',', $file['initial_pay_certificate']);
            }
            return makeApiResponseSuccess('ok', ['file_url' => $file]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 保存表单数据
     * @param Request $request
     * @return array|bool
     */
    public function applyAgentForm(Request $request)
    {
        try {
            $memberId = $this->memberId;
            $agent = new Agent();
            $params = $request->all();
            $params['member_id'] = $memberId;
            $save = $agent->applyAgent($params);
            if ($save === true) {
                $agentModel = AgentModel::query()->where('member_id', $this->memberId)->first();
                if (!$agentModel->initial_pay_status && $agentModel->status == Constants::AgentStatus_Applying) { //需要余额或线上支付的情况
                    if ($params['initial_pay_type'] == YZConstants::PayType_Balance) {
                        return (new Agent())->payFee($memberId, $params['initial_pay_type'], $params['pay_password'], 1);
                    }
                    if (in_array($params['initial_pay_type'], YZConstants::getOnlinePayType())) {
                        $callback = "\\" . static::class . "@payApplyCallBack";
                        $orderId = 'AgentInitial' . date('YmdHis');
                        $res = Payment::doPay($orderId, $this->memberId, $agentModel->initial_money, $callback, $params['initial_pay_type'], 0, 6);
                        if (getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxApp) $res['backurl'] = '#/agent/agent-apply-viewing'; //小程序专用，用来标记在小程序端支付成功后，应该跳转到哪里
                        return makeApiResponse(302, 'ok', [
                            'orderid' => $orderId,
                            'memberid' => $this->memberId,
                            'result' => $res,
                            'callback' => $callback,
                        ]);
                    }
                } else {
                    return makeApiResponseSuccess('ok');
                }
            } else {
                return $save;
            }

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 支付加盟费等后的回调
     * @param $info
     * @return array|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function payApplyCallBack($info)
    {
        try {
            // 支付宝return过来的 跳到支付结果页
            if (\Illuminate\Support\Facades\Request::isMethod('get') && (strpos(\Illuminate\Support\Facades\Request::path(), '/alipayreturn/') !== false)) {
                $alipay = Payment::getAlipayInstance();
                $res = $alipay->checkReturn();
                if ($res->success) {
                    (new Agent())->payFee($info['member_id'], $info['pay_type'], $info, 1);
                    $url = '/shop/front/#/agent/agent-apply-viewing';
                    return redirect($url);
                }
            }
            // 通联return过来的 跳到支付结果页
            if (\Illuminate\Support\Facades\Request::isMethod('get') && (strpos(\Illuminate\Support\Facades\Request::path(), '/tlpayreturn/') !== false)) {
                $tlpay = Payment::getTLPayInstance();
                $res = $tlpay->checkReturn();
                if ($res->success) {
                    (new Agent())->payFee($info['member_id'], $info['pay_type'], $info, 1);
                    $url = '/shop/front/#/agent/agent-apply-viewing';
                    return redirect($url);
                }
            }
            (new Agent())->payFee($info['member_id'], $info['pay_type'], $info, 1);
            return $info;
        } catch (\Exception $ex) {
            Log::writeLog("purchase-pay-callback-error", "error = " . var_export($ex->getMessage(), true));
            return makeApiResponseError($ex);
        }
    }

    /**
     * 检测代理加盟费等是否已经支付
     *
     * @param Request $request
     * @return void
     */
    public function checkPay(Request $request)
    {
        $agent = AgentModel::query()->where('member_id', $this->memberId)->first();
        return makeApiResponseSuccess('ok', [
            'initial_pay_status' => intval($agent->initial_pay_status)
        ]);
    }

    /**
     * 获取申请表单
     * @param Request $request
     * @return array
     */
    public function getAgentApplyForm(Request $request)
    {
        try {
            // 申请的等级 不能超过后台设置的等级
            $agentBaseSetting = new AgentBaseSetting();
            $agentSetting = $agentBaseSetting->getSettingModel();
            $level = $agentSetting->level;
            if (!$level) {
                return makeServiceResultFail("代理功能未开启", ['agent_setting' => false]);
            }
            $data = [];
            $agentApplySetting = new AgentApplySetting();
            $data['apply_agent_setting'] = $agentApplySetting->getApplyStatus();
            // 是否申请过 或 已经是代理
            $agent = (new Agent())->checkAgentExist($this->memberId);
            if ($agent) {
                $data['status'] = $agent->status;
                $data['reject_reason'] = $agent->reject_reason;
            }
            if (!$agent || $agent->status == Constants::AgentStatus_RejectReview || $agent->status == Constants::AgentStatus_Applying) {
                $data['form'] = $agentApplySetting->getApplyForm();
                // 可申请代理等级
                $applyLevel = $agentApplySetting->getCanApplyLevel();
                $applyLevel = json_decode($applyLevel, true);
                $enabledLevel = [];
                // 和开启的代理等级比对
                if ($applyLevel) {
                    foreach ($applyLevel as $key => $item) {
                        if ($item <= $level && $item >= 1) {
                            // 去掉大于代理等级的
                            $enabledLevel[] = $item;
                        }
                    }
                }
                // 如果没有符合条件的代理等级
                if (count($enabledLevel) > 0) {
                    $data['level'] = $enabledLevel;
                } else {
                    return makeServiceResultFail("未开启代理加盟申请等级");
                }

                //获取相关的加盟费的信息
                $data['initial'] = $agentBaseSetting->getInitialInfo();
                //获取相关代理的相关支付配置
                $data['pay_config'] = $this->getPayConfig();
                //获取邀请人信息，从授权邀请进入此页面时需要
                if ($request->get('invite')) {
                    $inviterInfo = MemberModel::query()->select('nickname', 'headurl')->where('id', $request->get('invite'))->first();
                    if ($inviterInfo && $inviterInfo->headurl && !preg_match('@^https?://@i', $inviterInfo->headurl)) {
                        $inviterInfo->headurl = Site::getSiteComdataDir() . $inviterInfo->headurl;
                    }
                    $inviteLevel = $request->get('inviteLevel');
                    $inviterInfo->invite_level = Constants::getAgentLevelTextForFront($inviteLevel, $inviteLevel . "级代理");
                    $data['inviter_info'] = $inviterInfo;
                }
            }
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取代理申请时的支付配置，因为代理申请目前只能通过线下支付，所以只读取线下支付的配置即可
     * @return array
     */
    public function getPayConfig()
    {
        $payConfig = (new payConfig(4))->getInfo();
        $c = Finance::getPayConfig(4);
        $payConfig['types'] = $c['types'];
        return $payConfig;
    }

    /**
     * 获取授权邀请时使用的配置信息
     *
     * @return void
     */
    public function getInviteConfig()
    {
        // 申请的等级 不能超过后台设置的等级
        $agentBaseSetting = new AgentBaseSetting();
        $agentSetting = $agentBaseSetting->getSettingModel();
        $level = $agentSetting->level;
        if (!$level) {
            return makeServiceResultFail("代理功能未开启");
        }
        // 当前会员的代理等级
        $mModel = MemberModel::find($this->memberId);
        // 可申请代理等级
        $agentApplySetting = new AgentApplySetting();
        $applyLevel = $agentApplySetting->getCanApplyLevel();
        $applyLevel = json_decode($applyLevel, true);
        $sameLevels = [];
        $upLevels = [];
        $subLevels = [];
        // 和开启的代理等级比对
        if ($applyLevel) {
            foreach ($applyLevel as $item) {
                if ($item <= $level && $item >= 1) {
                    // 去掉大于代理等级的
                    //$enabledLevel[] = $item;
                    if ($item == $mModel->agent_level) $sameLevels[] = ['level' => $item, 'name' => Constants::getAgentLevelTextForFront($item, $item . "级代理")];
                    elseif ($item < $mModel->agent_level) $upLevels[] = ['level' => $item, 'name' => Constants::getAgentLevelTextForFront($item, $item . "级代理")];
                    else $subLevels[] = ['level' => $item, 'name' => Constants::getAgentLevelTextForFront($item, $item . "级代理")];
                }
            }
        }
        // 如果没有符合条件的代理等级
        if (count($sameLevels) > 0 || count($upLevels) > 0 || count($subLevels) > 0) {
            $data['same_levels'] = $sameLevels;
            $data['sub_levels'] = $subLevels;
            $data['up_levels'] = $upLevels;
            return makeApiResponseSuccess('ok', $data);
        } else {
            return makeServiceResultFail("未开启代理加盟申请等级");
        }
    }

    /**
     * 代理代理概况
     * @param Request $request
     * @return array
     */
    public function getAgentTeamInfo(Request $request)
    {
        try {
            $memberId = $this->memberId;
            $member = new Member($memberId);
            if (!$member->checkExist()) {
                return makeServiceResultFail("不是会员");
            }
            $memberModel = $member->getModel();
            $agentor = new Agentor($memberId);
            if (!$agentor->checkExist()) {
                return makeServiceResultFail("未申请代理");
            }
            $data = $agentor->getCountData([
                'team' => true,
                'team_contain_self' => true,
                'order_reward' => true,
                'sale_reward' => true,
                'recommend_reward' => true,
                'performance_reward' => true,
                'agent_new_other_reward' => true,
            ], true);
            $data['base_setting'] = AgentBaseSetting::getCurrentSiteSettingFormat();
            $data['other_reward'] = AgentOtherRewardSetting::getCurrentSiteSetting(Constants::AgentOtherRewardType_Grateful);
            $data['sale_reward_setting'] = AgentSaleRewardSetting::getCurrentSiteSetting();
            unset($data['sale_reward_setting']['commision']);
            $data['recommend_reward_setting'] = AgentRecommendRewardSetting::getCurrentSiteSetting();
            unset($data['recommend_reward_setting']['commision']);
            $data['performance_reward_setting'] = AgentPerformanceRewardSetting::getCurrentSiteSetting();
            $data['member'] = [
                'id' => $memberModel->id,
                'name' => $memberModel->name,
                'nickname' => $memberModel->nickname,
                'headurl' => $memberModel->headurl,
                'mobile' => $memberModel->mobile,
                'agent_level' => $memberModel->agent_level,
                'agent_level_text' => Constants::getAgentLevelTextForFront(intval($memberModel->agent_level)),
            ];
            $agent_parent = (new Member($memberModel->agent_parent_id))->getModel();
            if ($agent_parent) {
                $parent_info = [
                    'id' => $agent_parent->id,
                    'name' => $agent_parent->name,
                    'nickname' => $agent_parent->nickname,
                    'headurl' => $agent_parent->headurl,
                    'mobile' => $agent_parent->mobile,
                ];
            } else {
                $store = (new StoreConfig())->getInfo()['data'];
                $parent_info = [
                    'name' => '公司',
                    'nickname' => '公司',
                    'mobile' => $store->custom_mobile,
                ];
            }
            $data['parent_info'] = $parent_info;
            return makeApiResponseSuccess('ok', $data);

        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}



