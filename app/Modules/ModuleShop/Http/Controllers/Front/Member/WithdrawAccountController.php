<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use YZ\Core\Member\Auth;
use YZ\Core\Common\VerifyCode;
use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Member\MemberWithdrawAccount;
use App\Modules\ModuleShop\Libs\SiteConfig\WithdrawConfig;

class WithdrawAccountController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getInfo(){
        $WithdrawAccount = new MemberWithdrawAccount($this->memberId);
        $data=$WithdrawAccount->getInfo();
        $WithdrawConfig= new WithdrawConfig();
        $data['withdraw_config']=$WithdrawConfig->getInfo();
        return makeApiResponseSuccess("ok", $data);
    }
    public function edit(Request $request){
        try{
            if(!$request->is_delete){
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
            }
            $WithdrawAccount = new MemberWithdrawAccount($this->memberId);
            if($request->bank_account){
                if(!is_numeric($request->bank_account)){
                    return makeApiResponse(500,'银行账户必须全是数值' );
                }
            }
            $param=$request->toArray();
            $WithdrawAccount->edit($param);
            return makeApiResponseSuccess("ok");
        }catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }

    }
}