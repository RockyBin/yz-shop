<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member\Center;


use App\Modules\ModuleShop\Libs\Coupon\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Coupon\CouponItem;
use YZ\Core\Site\Site;

class MemberCouponController extends BaseController
{
    protected $siteId = 0;

    public function __construct()
    {
        parent::__construct();

        $this->siteId = Site::getCurrentSite()->getSiteId();
    }

    /**
     * @param array $params 参数
     * @return $expression
     */
    public function getCoupon(Request $request)
    {
        $params=$request->toArray();
        $params['member_id'] = $this->memberId;
        $params['front']=true;
        $coupon_item = new CouponItem();
        $data = $coupon_item->getList($params);
        foreach ($data['list'] as &$item) {
            $coupon_item->convertOutputData($item);
        }
        return makeApiResponseSuccess('ok', $data['list']);
    }

    /**
     * 领取优惠券
     * @param Request $request
     * @return array
     *
     */
    public function receivedCoupon(Request $request)
    {
        try {
            if (!$request->id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            $params['member_id'] = $this->memberId;
            $params['coupon_id'] = $request->id;
            $coupon = new Coupon();
            $data = $coupon->receivedCoupon($params);
            return makeApiResponse($data['code'], $data['msg']);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

}