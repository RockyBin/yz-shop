<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use App\Modules\ModuleShop\Libs\Agent\Agent;
use App\Modules\ModuleShop\Libs\Agent\AgentBaseSetting;
use App\Modules\ModuleShop\Libs\Agent\AgentLevel;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Distribution\DistributionLevel;
use App\Modules\ModuleShop\Libs\Distribution\DistributionSetting;
use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use App\Modules\ModuleShop\Libs\Member\MemberLabel;
use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use App\Modules\ModuleShop\Libs\Model\BrowseModel;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DistributionLevelModel;
use App\Modules\ModuleShop\Libs\Model\DistributorModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Wx\WxSubscribeSetting;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Session;
use YZ\Core\Common\VerifyCode;
use YZ\Core\Constants as CodeConstants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use YZ\Core\FileUpload\FileUpload;
use App\Modules\ModuleShop\Libs\Coupon\CouponItem;
use App\Modules\ModuleShop\Libs\Member\MemberLevel;
use App\Modules\ModuleShop\Libs\Product\ProductCollection;
use App\Modules\ModuleShop\Libs\Utils;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\SiteConfig\StoreConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\OrderConfig;
use App\Modules\ModuleShop\Libs\Point\Give\PointGiveForMemberInfo;
use App\Modules\ModuleShop\Libs\Browse\Browse;

/**
 * 会员中心
 * Class MemberInfoController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member
 */
class MemberInfoController extends BaseController
{
    private $privateColumns = ['password', 'pay_password']; // 这些字段不返回给前端
    private $sessionKey_Mobile = 'Member.Mobile'; // 验证手机的SessionKey

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 会员中心首页数据
     * @return array
     */
    public function index()
    {
        try {
            $member = new Member($this->memberId, $this->siteId);
            $memberData = $member->getInfo(true);
            $memberData->level_text = '';
            if ($member->getModel()->level) {
                $memberLevel = new MemberLevel($this->siteId);
                $memberLevelModel = $memberLevel->detail($member->getModel()->level);
                if ($memberLevelModel) {
                    $memberData->level_text = $memberLevelModel->name;
                }
            }
            // 根据设置显示相应的等级
            if($member->getModel()->is_distributor){
                $distributor = DistributorModel::where('member_id',$this->memberId)->first();
                if($distributor) {
                    $distriLevel = DistributionLevelModel::find($distributor->level);
                    if($distriLevel) $distriLevelText = $distriLevel->name;
                }
            }
            if($member->getModel()->agent_level > 0){
                $agentLevelText = Constants::getAgentLevelTextForFront($member->getModel()->agent_level, $member->getModel()->agent_level . "级代理");
            }
            /*if($member->getModel()->dealer_level > 0){
                $dealerLevel = DealerLevelModel::find($member->getModel()->dealer_level);
                if($dealerLevel) $dealerLevelText = $dealerLevel->name;
            }*/
            $config = Site::getCurrentSite()->getConfig()->getModel();
            if($config->member_center_show_level == 3) $memberData->level_text = $agentLevelText ? $agentLevelText : ($distriLevelText ? $distriLevelText :  $memberData->level_text);
            if($config->member_center_show_level == 2) $memberData->level_text = $agentLevelText ? $agentLevelText : $memberData->level_text;
            if($config->member_center_show_level == 1) $memberData->level_text = $distriLevelText ? $distriLevelText : $memberData->level_text;
            // 商品收藏
            $productCollection = new ProductCollection(null, $this->siteId);
            $memberData->product_collection = $productCollection->count($this->memberId);
            // 可用优惠券
            $couponItem = new CouponItem();
            $coupon_able = $couponItem->getMemberCouponItem([
                'member_id' => $this->memberId,
                'status' => 2,
                'start_time_end' => date('Y-m-d H:i:s'),
                'expiry_time_start' => date('Y-m-d H:i:s')
            ]);
            $memberData->coupon_able = $coupon_able ?: 0;
            //需要判断该用户是否被取消资格，用于前端跳转页面
            $distributor = new Distributor($this->memberId);
            $memberData->distributor_del = $distributor->isDel() ? 1 : 0;
            $memberData->distributor_status = $distributor->getModel()->status;
            // 浏览量
            $browseNum = (new Browse())->getList(['member_id' => $this->memberId, 'only_return_count' => 1]);
            $memberData->browse_num = intval($browseNum);
            $data = $this->convertOutputData($memberData->toArray());
            // 订单数统计
            $orderCountData = OrderModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->memberId)
                ->groupBy('status')
                ->selectRaw('status, count(1) as num')
                ->pluck('num', 'status')->all();
            $orderCountWaitComment = OrderModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->memberId)
                ->where('comment_status', Constants::OrderCommentStatus_CanComment)
                ->whereIn('status', [Constants::OrderStatus_OrderReceive, Constants::OrderStatus_OrderSuccess, Constants::OrderStatus_OrderFinished])
                ->count();
            $orderPayCount = OrderModel::query()
                ->where('site_id', $this->siteId)
                ->where('member_id', $this->memberId)
                ->whereIn('type_status',[Constants::OrderType_GroupBuyingStatus_Yes,0])
                ->whereIn('status', [Constants::OrderStatus_OrderPay])
                ->count();
            $data['order_count'] = [
                'no_pay' => intval($orderCountData[Constants::OrderStatus_NoPay]),
                'pay' => intval($orderPayCount),
                'send' => intval($orderCountData[Constants::OrderStatus_OrderSend]),
                'done' => intval($orderCountData[Constants::OrderStatus_OrderSuccess]) + intval($orderCountData[Constants::OrderStatus_OrderFinished]),
                'wait_comment' => intval($orderCountWaitComment),
            ];
            $data['aftersale_isopen'] = (new OrderConfig())->getInfo()['aftersale_isopen'];
            $data['product_comment_status'] = Site::getCurrentSite()->getConfig()->getProductCommentConfig()['product_comment_status'];

            // 分销开关
            $distributionSetting = new DistributionSetting();
            $data['distribution_config_level'] = $distributionSetting->getSettingModel()->level;
            // 代理开关
            $agentSetting = new AgentBaseSetting();
            $data['agent_config_level'] = $agentSetting->getSettingModel()->level;
            // 邀请码
            $data['invite_code'] = $this->memberId;
            $config = Site::getCurrentSite()->getConfig()->getModel();
            $data['retail_status'] = $config->retail_status;
            $data['subscribe'] = (new WxSubscribeSetting())->getSubscribeInfo();
            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 个人信息
     * @return array
     */
    public function getInfo()
    {
        try {
            $member = new Member($this->memberId, $this->siteId);
            $wxAuthData = $member->getAuthInfo(CodeConstants::MemberAuthType_WxOficialAccount);
            if ($wxAuthData) {
                $member->getModel()->bind_wx = true;
                $member->getModel()->bind_wx_nickname = $wxAuthData->nickname;
            } else {
                $member->getModel()->bind_wx = false;
                $member->getModel()->bind_wx_nickname = '';
            }
            // 会员等级
            $member->getModel()->level_text = '';
            if ($member->getModel()->level) {
                $memberLevel = new MemberLevel($this->siteId);
                $memberLevelModel = $memberLevel->detail($member->getModel()->level);
                if ($memberLevelModel) {
                    $member->getModel()->level_text = $memberLevelModel->name;
                }
            }
            // 处理数据
            $data = $this->convertOutputData($member->getModel()->toArray());
            // 客服电话
            $storeConfig = new StoreConfig();
            $storeConfigData = $storeConfig->getInfo();
            $data['store_config'] = [
                'custom_mobile' => $storeConfigData['data']['custom_mobile']
            ];

            return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $data);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改基本信息
     * @param Request $request
     * @return array
     */
    public function edit(Request $request)
    {
        try {
            $param = [];
            if ($request->has('sex')) {
                $param['sex'] = intval($request->sex);
            }
            if ($request->has('age')) {
                $param['age'] = intval($request->age);
            }
            if ($request->has('birthday')) {
                $param['birthday'] = $request->birthday;
            }
            if ($request->has('prov')) {
                $param['prov'] = $request->prov;
            }
            if ($request->has('city')) {
                $param['city'] = $request->city;
            }
            if ($request->has('area')) {
                $param['area'] = $request->area;
            }
            if ($request->has('nickname') && $request->nickname) {
                $param['nickname'] = $request->nickname;
            }
            if ($request->hasFile('headurl_data')) {
                $upload_filename = time();
                $upload_filepath = Site::getSiteComdataDir('', true) . '/member/';
                $upload_handle = new FileUpload($request->file('headurl_data'), $upload_filepath, $upload_filename);
                $upload_handle->reduceImageSize(200);
                $param['headurl'] = '/member/' . $upload_handle->getFullFileName();
            }

            $member = new Member($this->memberId, $this->siteId);
            $result = $member->edit($param);
            if ($result['code'] == 200) {
                $member = new Member($this->memberId, $this->siteId);
                if ($this->checkInfoIsFull($member->getModel())) {
                    // 完善信息送积分
                    $pointGive = new PointGiveForMemberInfo($member->getModel());
                    $pointGive->addPoint();
                }
                $result['data']['headurl'] = $member->getModel()->headurl;
                return makeApiResponseSuccess(trans("shop-front.common.action_ok"), $result['data']);
            } else {
                return makeApiResponse($result['code'], $result['msg'], $result['data']);
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 验证旧手机
     * @param Request $request
     * @return array
     */
    public function mobileCheck(Request $request)
    {
        try {
            // 清理Session
            Session::remove($this->sessionKey_Mobile);
            // 检查手机号码
            $member = new Member($this->memberId, Site::getCurrentSite()->getSiteId());
            $mobile = $member->getModel()->mobile;
            if (empty($mobile)) {
                return makeApiResponseFail(trans("shop-front.member.mobile_set_first"));
            }
            // 检查验证码
            $code = $request->code;
            if (empty($code)) {
                return makeApiResponseFail(trans("shop-front.common.data_miss"));
            }
            // 验证
            $verifyCodeResult = VerifyCode::checkSmsCode($mobile, $code);
            if (intval($verifyCodeResult['code']) != 200) {
                return makeApiResponse($verifyCodeResult['code'], $verifyCodeResult['msg'], $verifyCodeResult['data']);
            } else {
                Session::put($this->sessionKey_Mobile, $mobile);
                // 清理掉时间验证
                VerifyCode::clearSmsCodeLastTime();
                return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 获取当前会员的手机号
     * @return array
     */
    public function getMobile()
    {
        try {
            $member = new Member($this->memberId, Site::getCurrentSite()->getSiteId());
            // 手机存在，则要通过之前的验证
            $mobile = $member->getModel()->mobile;
            if ($mobile) {
                return makeApiResponseSuccess('ok', ['mobile' => $mobile]);
            } else {
                return makeApiResponseFail(trans("shop-front.member.mobile_set_first"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改手机
     * @param Request $request
     * @return array
     */
    public function mobileChange(Request $request)
    {
        try {
            $member = new Member($this->memberId, Site::getCurrentSite()->getSiteId());
            // 手机存在，则要通过之前的验证
            $originMobile = $member->getModel()->mobile;
            if ($originMobile) {
                if (Session::get($this->sessionKey_Mobile) != $originMobile) {
                    return makeApiResponseFail(trans("shop-front.member.mobile_check_first"));
                }
            }

            // 检查验证码
            $code = $request->code;
            $mobile = trim($request->mobile);
            if (empty($code) || empty($mobile)) {
                return makeApiResponseFail(trans("shop-front.common.data_miss"));
            }
            // 如果手机号相同 不需要重新绑定
            if ($mobile == $originMobile) {
                return makeApiResponseFail(trans("shop-front.member.mobile_repeat_enter_new_mobile"));
            }
            // 如果手机号码已存在，不能改绑
            $mobileExist = MemberModel::query()->where('site_id', Site::getCurrentSite()->getSiteId())->where('mobile', $mobile)->count();
            if ($mobileExist) {
                return makeApiResponseFail(trans("shop-front.member.mobile_exist_check"));
            }
            // 验证
            $verifyCodeResult = VerifyCode::checkSmsCode($mobile, $code);
            if (intval($verifyCodeResult['code']) != 200) {
                return makeApiResponse($verifyCodeResult['code'], $verifyCodeResult['msg'], $verifyCodeResult['data']);
            } else {
                // 清理Session
                Session::remove($this->sessionKey_Mobile);
                // 修改手机
                $member->edit(['mobile' => $mobile]);
                return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
            }
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改登录密码
     * @param Request $request
     * @return array
     */
    public function passwordChange(Request $request)
    {
        try {
            $member = new Member($this->memberId, Site::getCurrentSite()->getSiteId());
            // 手机存在，则要通过之前的验证
            $mobile = $member->getModel()->mobile;
            if (empty($mobile)) {
                return makeApiResponseFail(trans("shop-front.member.mobile_set_first"));
            }

            // 验证验证码
            $code = $request->input('code');
            if (!$code) {
                return makeApiResponseFail(trans("shop-front.common.verify_code_fail"), ['code_error' => true]);
            }
            $verifyCodeResult = VerifyCode::checkSmsCode($mobile, $code);
            if (intval($verifyCodeResult['code']) != 200) {
                $returnData = $verifyCodeResult['data'];
                $returnData['code_error'] = true;
                return makeApiResponse($verifyCodeResult['code'], $verifyCodeResult['msg'], $returnData);
            }

            $password = trim($request->password);
            $passwordConfirm = trim($request->password_confirm);
            if (empty($password) || $password != $passwordConfirm) {
                return makeApiResponseFail(trans("shop-front.member.password_diff"));
            }

            // 验证密码强度
            if (!Utils::checkPasswordStrength($password)) {
                return makeApiResponseFail(trans("shop-front.member.password_strength"));
            }

            // 修改密码
            $result = $member->edit([
                'password' => $password
            ]);

            if ($result['code'] == 200) {
                return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
            } else {
                return makeApiResponse($result['code'], $result['msg'], $result['data']);
            }

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 修改支付密码
     * @param Request $request
     * @return array
     */
    public function payPasswordChange(Request $request)
    {
        try {
            $member = new Member($this->memberId, Site::getCurrentSite()->getSiteId());
            // 手机存在，则要通过之前的验证
            $mobile = $member->getModel()->mobile;

            if (empty($mobile)) {
                return makeApiResponseFail(trans("shop-front.member.mobile_set_first"));
            }

            // 验证验证码
            $code = $request->input('code');
            if (!$code) {
                return makeApiResponseFail(trans("shop-front.common.verify_code_fail"), ['code_error' => true]);
            }
            $verifyCodeResult = VerifyCode::checkSmsCode($mobile, $code);
            if (intval($verifyCodeResult['code']) != 200) {
                $returnData = $verifyCodeResult['data'];
                $returnData['code_error'] = true;
                return makeApiResponse($verifyCodeResult['code'], $verifyCodeResult['msg'], $returnData);
            }

            $password = trim($request->password);
            $passwordConfirm = trim($request->password_confirm);
            if (empty($password) || $password != $passwordConfirm) {
                return makeApiResponseFail(trans("shop-front.member.password_diff"));
            }

            // 验证密码强度
            if (!Utils::checkPayPasswordStrength($password)) {
                return makeApiResponseFail(trans("shop-front.member.pay_password_strength"));
            }

            // 修改密码
            $result = $member->edit([
                'pay_password' => $password
            ]);

            if ($result['code'] == 200) {
                return makeApiResponseSuccess(trans("shop-front.common.action_ok"));
            } else {
                return makeApiResponse($result['code'], $result['msg'], $result['data']);
            }

        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 数据输出转换
     * @param $item
     * @return mixed
     */
    public function convertOutputData($item)
    {
        // 清楚私密数据
        foreach ($this->privateColumns as $privateColumn) {
            unset($item[$privateColumn]);
        }

        if ($item['buy_money']) {
            $item['buy_money'] = moneyCent2Yuan(intval($item['buy_money']));
        }

        if ($item['deal_money']) {
            $item['deal_money'] = moneyCent2Yuan(intval($item['deal_money']));
        }

        if ($item['balance']) {
            $item['balance'] = moneyCent2Yuan(intval($item['balance']));
        }

        if ($item['balance_blocked']) {
            $item['balance_blocked'] = moneyCent2Yuan(intval($item['balance_blocked']));
        }

        if ($item['balance_history']) {
            $item['balance_history'] = moneyCent2Yuan(intval($item['balance_history']));
        }

        return $item;
    }

    /**
     * 检查信息是否完整
     * @param $memberModel
     * @return bool
     */
    private function checkInfoIsFull($memberModel)
    {
        if (!$memberModel) return false;
        if ($memberModel->mobile && $memberModel->nickname && $memberModel->headurl && $memberModel->birthday) {
            return true;
        } else {
            return false;
        }
    }

}