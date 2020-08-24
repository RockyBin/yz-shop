<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController;
use App\Modules\ModuleShop\Libs\Finance\Withdraw\WithdrawConditionHelper;
use App\Modules\ModuleShop\Libs\Finance\Withdraw\WithdrawFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use YZ\Core\Constants;
use YZ\Core\Finance\FinanceHelper;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Member\Member as CoreMember;
use App\Modules\ModuleShop\Libs\SiteConfig\WithdrawConfig;
use App\Modules\ModuleShop\Libs\SiteConfig\PayConfig;
use App\Modules\ModuleShop\Libs\Member\MemberWithdrawAccount;

/**
 * 提现 Controller
 * Class WithdrawController
 * @package App\Modules\ModuleShop\Http\Controllers\Front\Member
 */
class WithdrawController extends BaseMemberController
{
    protected $siteId = 0;


    public function __construct()
    {
        parent::__construct();
        $this->siteId = Site::getCurrentSite()->getSiteId();

    }

    /**
     * 提现的类型，
     * 支付宝，微信
     * @return array
     */
    public function getInfo(Request $request)
    {
        try {
            $type = intval($request->get('type'));
            $withdraw = WithdrawFactory::createInstance($type);
            $balance = $withdraw->getAvailableBalance($type, $this->memberId);
            $WithdrawAccount = new MemberWithdrawAccount($this->memberId);
            $accountInfo = $WithdrawAccount->getInfo();
            $member = new Member($this->memberId, $this->siteId);
            //用户是否有为wx的open_id或者支付宝的open_id
            $memberInfo = [
                'wx_openid' => $member->getWxOpenId() ? true : false,
                'alipay_openid' => $member->getAlipayUserId() ? true : false,
                'pay_password_status' => $member->payPasswordIsNull() ? 0 : 1
            ];
            $config = $withdraw->getConfig();
            $config['min_money'] = moneyCent2Yuan($config['min_money']);
            $config['max_money'] = moneyCent2Yuan($config['max_money']);
            $data = ['config' => $config,'accountInfo' => $accountInfo,'member_info' => $memberInfo, 'balance' => moneyCent2Yuan($balance)];
            try {
                $withdraw->checkWithdrawDate();
            }catch(\Exception $ex){
                return makeApiResponse(405, 'fail',$data);
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'),$data);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 提现插入财务记录
     * request money 提现金额
     * request type 从哪里提现：0=余额，9=佣金
     * request pay_type 提现到哪里：2=微信，3=支付宝，99=余额
     */
    public function addWithdrawFinance(Request $request)
    {
        try {
            // 数据基础检测
            $type = intval($request->type);
            $payType = intval($request->pay_type);
            $money = moneyYuan2Cent($request->money);
            $member = new Member($this->memberId);
            if ($member->payPasswordIsNull()) {
                return makeApiResponse(402, trans('shop-front.shop.pay_password_error'));
            }
            // 验证支付密码
            if (!$member->payPasswordCheck($request->paypassword)) {
                return makeApiResponseFail(trans('shop-front.shop.pay_password_error'));
            }
            $withDrawInstance = WithdrawFactory::createInstance($type);
            $withDrawInstance->withdraw($type, $payType, $money, $this->memberId);
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'));
        } catch (\Exception $e) {
            DB::rollBack();
            return makeApiResponseError($e);
        }
    }
}