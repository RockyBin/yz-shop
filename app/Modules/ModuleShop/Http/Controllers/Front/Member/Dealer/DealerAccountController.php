<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Dealer;

use App\Modules\ModuleShop\Libs\Dealer\DealerAccount;
use Illuminate\Http\Request;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use YZ\Core\Common\VerifyCode;
use App\Modules\ModuleShop\Libs\Member\Member;
use YZ\Core\Site\Site;

class DealerAccountController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getList(){
        $account = new DealerAccount($this->memberId);
        $list = $account->getList();
        return makeApiResponseSuccess("ok",  $list);
    }

    public function delete(Request $request){
        try{
            $account = new DealerAccount($this->memberId);
            $account->delete($request->id);
            return makeApiResponseSuccess("ok");
        }catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    public function edit(Request $request){
        try{
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
            $account = new DealerAccount($this->memberId);
            if($request->account && intval($request->type) === \YZ\Core\Constants::PayType_Bank){
                if(!is_numeric($request->account)){
                    return makeApiResponse(500,'银行账户必须全是数字' );
                }
            }
            $param = $request->toArray();
            $account->edit($param);
            return makeApiResponseSuccess("ok");
        }catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}