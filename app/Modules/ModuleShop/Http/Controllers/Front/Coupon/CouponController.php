<?php

namespace App\Modules\ModuleShop\Http\Controllers\Front\Coupon;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController;
use App\Modules\ModuleShop\Libs\Coupon\Coupon;
use Illuminate\Http\Request;
use YZ\Core\Member\Auth;

class CouponController extends BaseFrontController
{
    private $coupon = null;

    public function __construct()
    {
        $this->coupon = new Coupon();
    }

    /**
     * 获取某产品可以使用的优惠券列表
     * @param Request $request
     * @return array
     */
    public function getProductCoupon(Request $request)
    {
        try {
            if (!$request->product_id) {
                return makeApiResponse(520, '数据异常：ID不能不空');
            }
            //状态必须为生效
            $param = ['product_id' => $request->product_id, 'status' => 1, 'member_id' => Auth::hasLogin(), 'receivie_status' => 1];
            $data = $this->coupon->couponProduct($param);
            return makeApiResponseSuccess('成功', [
                'list' => $data,
            ]);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 列出优惠圈for领券中心
     * @param Request $request
     */
    public function getCouponList(Request $request)
    {
        try {
            $param = $request->toArray();
            $param['member_id'] = Auth::hasLogin();
            $param['status'] = 1;
            $param['count_member_canuse'] = 1;
            $param['expiry_time'] = date('Y-m-d H:i:s');
            //$param['having'] = 'amount > have_received or amount_type = 0 or member_canuse > 0';
            $data = $this->coupon->getList($param);
            $list = $data['list']->toArray();

            return makeApiResponseSuccess('成功', [
                'total' => intval($data['total']),
                'page_size' => intval($data['page_size']),
                'current' => intval($data['current']),
                'last_page' => intval($data['last_page']),
                'list' => $list,
            ]);
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}