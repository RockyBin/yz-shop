<?php
/**
 * 会员地址接口
 * User: liyaohui
 */

namespace App\Modules\ModuleShop\Http\Controllers\Front\Member;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseMemberController as BaseController;
use App\Modules\ModuleShop\Libs\Member\MemberAddress;
use App\Modules\ModuleShop\Libs\Order\OrderFront;
use Illuminate\Http\Request;

class AddressController extends BaseController
{
    /**
     * 地址列表
     * @return array
     */
    public function getAddressList()
    {
        try {
            $list = (new MemberAddress($this->memberId))->getAddressList();
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 获取创建订单时需要的地址列表
     * @param Request $request
     * @return array
     */
    public function getCreateOrderAddressList(Request $request)
    {
        try {
            $productList = $request->input('product_ids', '');
            $list['list'] = (new MemberAddress($this->memberId))->getAddressList();
            if ($productList) {
                $list['no_available'] = OrderFront::getNoAvailableAddressIds($productList, $list['list']);
            }
            return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $list);
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 编辑地址
     * @param Request $request
     * @return array
     */
    public function editAddress(Request $request)
    {
        try {
            $address = $request->input('address', '');
            $addressModel = new MemberAddress($this->memberId);
            $save = $addressModel->editAddress($address);
            if ($save) {
                return makeApiResponseSuccess(trans('shop-front.common.action_ok'), $save->toArray());
            } else {
                return makeApiResponse(500, trans('shop-front.shop.save_fail'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }

    /**
     * 删除地址
     * @param Request $request
     * @return array
     */
    public function deleteAddress(Request $request)
    {
        try {
            $id = $request->input('id', '');
            $address = new MemberAddress($this->memberId);
            $result = $address->deleteAddress($id);
            if ($result) {
                return makeApiResponseSuccess(trans('shop-front.common.action_ok'));
            } else {
                return makeApiResponse(500, trans('shop-front.common.action_fail'));
            }
        } catch (\Exception $e) {
            return makeApiResponseError($e);
        }
    }
}