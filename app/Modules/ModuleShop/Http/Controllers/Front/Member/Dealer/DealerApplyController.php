<?php
/**
 * 经销商申请相关接口
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Dealer;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Dealer\Dealer;
use App\Modules\ModuleShop\Libs\Dealer\DealerAccount;
use App\Modules\ModuleShop\Libs\Dealer\DealerApplySetting;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\Agent\AgentOrderCommisionConfig;
use App\Modules\ModuleShop\Libs\Agent\AgentPerformanceRewardSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentRecommendRewardSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentSaleRewardSetting;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerBaseSetting;
use App\Modules\ModuleShop\Libs\Dealer\DealerHelper;
use App\Modules\ModuleShop\Libs\Dealer\DealerLevel;
use App\Modules\ModuleShop\Libs\Finance\Finance;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\DealerModel;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Libs\Agent\Agent;
use App\Modules\ModuleShop\Libs\Model\AgentModel;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use YZ\Core\Constants as YZConstants;
use YZ\Core\Model\MemberModel;
use YZ\Core\Payment\Payment;
use YZ\Core\Site\Site;

class DealerApplyController extends BaseController
{
    /**
     * 申请时上传的文件
     * @param Request $request
     * @return array
     */
    public function applyDealerFile(Request $request)
    {
        try {
            $memberId = $this->memberId;
            // 检测会员是否存在 是否申请过代理
            $dealer = new Dealer();
            $existAgent = $dealer->becomeDealerBefore(array_merge(['member_id' => $memberId], $request->all()), true);
            if ($existAgent && $existAgent->status != Constants::DealerStatus_RejectReview && $existAgent->status != Constants::DealerStatus_Applying) {
                return makeApiResponse(400, '您已申请过经销商');
            }
            $file = [];
            if ($request->hasFile('idcard_file_data')) {
                $file['idcard_file_data'] = $dealer->uploadFile($request->file('idcard_file_data'), $memberId, 'idcard');
            }
            if ($request->hasFile('business_license_file_data')) {
                $file['business_license_file_data'] = $dealer->uploadFile($request->file('business_license_file_data'), $memberId, 'business_license');
            }
            if ($request->hasFile('initial_pay_certificate')) {
                $initialPayCertificate = $request->file('initial_pay_certificate');
                foreach ($initialPayCertificate as $item) {
                    $file['initial_pay_certificate'][] = $dealer->uploadFile($item, $memberId, 'initial_pay_certificate');
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
    public function applyDealerForm(Request $request)
    {
        try {
            $memberId = $this->memberId;
            $agent = new Dealer();
            $params = $request->all();
            $params['member_id'] = $memberId;
            $save = $agent->applyDealer($params);
            if ($save === true) {
                $agentModel = DealerModel::query()->where('member_id', $this->memberId)->first();
                if (!$agentModel->initial_pay_status && $agentModel->status == Constants::DealerStatus_Applying) { //需要余额或线上支付的情况
                    if ($params['initial_pay_type'] == YZConstants::PayType_Balance) {
                        return (new Dealer())->payFee($memberId, $params['initial_pay_type'], $params['pay_password'], 1);
                    }
                    if (in_array($params['initial_pay_type'], YZConstants::getOnlinePayType())) {
                        $callback = "\\" . static::class . "@payApplyCallBack";
                        $orderId = 'DealerInitial' . date('YmdHis');
                        $res = Payment::doPay($orderId, $this->memberId, $agentModel->initial_money, $callback, $params['initial_pay_type'], 0, 7);
                        if (getCurrentTerminal() == \YZ\Core\Constants::TerminalType_WxApp) $res['backurl'] = '#/dealer/dealer-apply-result'; //小程序专用，用来标记在小程序端支付成功后，应该跳转到哪里
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
                    (new Dealer())->payFee($info['member_id'], $info['pay_type'], $info, 1);
                    $url = '/shop/front/#/dealer/dealer-apply-viewing';
                    return redirect($url);
                }
            }
            // 通联return过来的 跳到支付结果页
            if (\Illuminate\Support\Facades\Request::isMethod('get') && (strpos(\Illuminate\Support\Facades\Request::path(), '/tlpayreturn/') !== false)) {
                $tlpay = Payment::getTLPayInstance();
                $res = $tlpay->checkReturn();
                if ($res->success) {
                    (new Dealer())->payFee($info['member_id'], $info['pay_type'], $info, 1);
                    $url = '/shop/front/#/dealer/dealer-apply-viewing';
                    return redirect($url);
                }
            }
            (new Dealer())->payFee($info['member_id'], $info['pay_type'], $info, 1);
            return $info;
        } catch (\Exception $ex) {
            Log::writeLog("dealer-pay-callback-error", "error = " . var_export($ex->getMessage(), true));
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
        $dealer = DealerModel::query()->where('member_id', $this->memberId)->first();
        return makeApiResponseSuccess('ok', [
            'initial_pay_status' => intval($dealer->initial_pay_status)
        ]);
    }

    /**
     * 获取申请表单
     * @param Request $request
     * @return array
     */
    public function getDealerApplyForm(Request $request)
    {
        try {
            $data = [];
            $baseSetting = new DealerBaseSetting();
            $dealerApplySetting = new DealerApplySetting();
            $dealerApplySettingInfo = $dealerApplySetting->getInfo();
            $data['apply_dealer_setting'] = $dealerApplySetting->getApplyStatus();
            // 是否申请过 或 已经是代理
            $dealer = (new Dealer())->checkDealerExist($this->memberId);
            if ($dealerApplySettingInfo['can_apply'] == 0 && (!$request->get('invite') || !$request->get('inviteLevel'))) {
                return makeApiResponse(405, '商家暂时关闭了申请功能，如有疑问，请联系客服');
            }
            if ($request->get('invite') && $dealerApplySettingInfo['can_invite'] == 0) {
                return makeApiResponse(405, '商家暂时关闭了申请功能，如有疑问，请联系客服');
            }
            if ($dealer) {
                $data['status'] = $dealer->status;
                $data['reject_reason'] = $dealer->reject_reason;
            }
            if (!$dealer || $dealer->status == Constants::DealerStatus_RejectReview || $dealer->status == Constants::DealerStatus_Applying) {
                $data['form'] = $dealerApplySetting->getApplyForm();
                $data['initial_money_target'] = $baseSetting->getSettingModel()->initial_money_target; //加盟费谁收
                // 可申请代理等级
                if (intval($request->apply_type) === '1' || $request->get('invite') || $request->get('inviteLevel')) $enabledLevel = $dealerApplySetting->getCanInviteLevel();
                else $enabledLevel = $dealerApplySetting->getCanApplyLevel();
                // 如果没有符合条件的代理等级
                if (count($enabledLevel) > 0) {
                    $data['level'] = $enabledLevel;
                } else {
                    return makeServiceResultFail("未设置经销商加盟申请等级");
                }
                //获取相关经销商的相关支付配置
                $data['pay_config'] = $this->getPayConfig();
                //获取邀请人信息，从授权邀请进入此页面时需要
                if ($request->get('invite')) {
                    $inviterInfo = MemberModel::query()->select('nickname', 'headurl')->where('id', $request->get('invite'))->first();
                    if ($inviterInfo && $inviterInfo->headurl && !preg_match('@^https?://@i', $inviterInfo->headurl)) {
                        $inviterInfo->headurl = Site::getSiteComdataDir() . $inviterInfo->headurl;
                    }
                    $inviteLevel = $request->get('inviteLevel');
                    $inviterInfo->invite_level = Constants::getAgentLevelTextForFront($inviteLevel, $inviteLevel . "级代理");
                    $inviterInfo->invite_level = $enabledLevel->where('id', '=', $inviteLevel)->first()->name;
                    $data['inviter_info'] = $inviterInfo;
                }
            }
            return makeApiResponseSuccess('ok', $data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取申请时上家的加盟费收款方式
     * @param Request $request
     * @return array
     */
    public function getParentPayConfig(Request $request)
    {
        try {
            $level = $request->level;
            $config = DealerAccount::getParentPayConfigForApply($this->memberId, $level);
            if (count($config['types'])) {
                return makeApiResponseSuccess('ok', ['pay_config' => $config]);
            } else {
                return makeServiceResultFail("上级经销商未设置收款方式，请联系上级经销商");
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取经销商申请时的支付配置
     * @return array
     */
    public function getPayConfig()
    {
        $payConfig = (new payConfig(4))->getInfo();
        $c = Finance::getPayConfig(5);
        $payConfig['types'] = $c['types'];
        return $payConfig;
    }

    /**
     * 获取授权邀请时使用的配置信息
     * @return array
     */
    public function getInviteConfig()
    {
        try {
            $dealApplySetting = new DealerApplySetting();
            if (!$dealApplySetting->canInvite()) {
                return makeApiResponse(501, '商城已关闭经销商授权邀请功能，如有疑问，请联系客服');
            }
            // 当前会员的代理等级
            $mModel = MemberModel::find($this->memberId);
            $levels = DealerLevel::getLevelList(['status' => 1, 'order_by' => ['weight', 'desc']], true);
            $weight = $levels->where('id', $mModel->dealer_level)->first()->weight;
            // 可申请代理等级
            $applyLevel = $dealApplySetting->getCanInviteLevel();
            $sameLevels = [];
            $upLevels = [];
            $subLevels = [];
            // 和开启的代理等级比对
            if ($applyLevel) {
                foreach ($applyLevel as $item) {
                    if ($item->id == $mModel->dealer_level) $sameLevels[] = ['level' => $item->id, 'name' => $item->name];
                    elseif ($weight < $item->weight) $upLevels[] = ['level' => $item->id, 'name' => $item->name];
                    else $subLevels[] = ['level' => $item->id, 'name' => $item->name];
                }
            }
            $can_invite_setting = ($dealApplySetting->getInfo())['can_invite_setting'];
            $can_invite_setting = $can_invite_setting ? json_decode($can_invite_setting, true) : $can_invite_setting;
            // 如果没有符合条件的代理等级
            if ((count($sameLevels) > 0 || count($upLevels) > 0 || count($subLevels) > 0) && ($can_invite_setting['same_levels'] == 1 || $can_invite_setting['sub_levels'] == 1 || $can_invite_setting['up_levels'] == 1)) {
                if ($can_invite_setting['same_levels']) $data['same_levels'] = $sameLevels;
                if ($can_invite_setting['sub_levels']) $data['sub_levels'] = $subLevels;
                if ($can_invite_setting['up_levels']) $data['up_levels'] = $upLevels;
                return makeApiResponseSuccess('ok', $data);
            } else {
                return makeApiResponse(502, "商家暂时还没有设置可邀请的等级");
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}