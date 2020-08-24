<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Http\Controllers\Custom\Zhiying\Front;

use App\Modules\ModuleShop\Http\Controllers\Front\BaseFrontController as BaseController;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use YZ\Core\Model\MemberModel;

/**
 * 证件
 * Class MemberCertController
 * @package App\Modules\ModuleShop\Http\Controllers\Custom\Site363\Front
 */
class CreateShopController extends BaseController
{
    private $md5Key = "MdOok@slw90amkl";

    /**
     * 跳转到智应官网开通商城
     * @param Request $request
     * @return array
     */
    public function create(Request $request)
    {
        try {
            $orderId = $request->get('order_id');
            $order = OrderModel::query()->where('site_id',$this->siteId)->where('id',$orderId)->first();
            if(!$order){
                throw new \Exception('订单不存在');
            }
            $items = OrderItemModel::query()->where('order_id',$orderId)->get();
            $member = MemberModel::find($order->member_id);
            $data = json_encode(['orderInfo' => $order->toArray(),'productInfo' => $items->toArray(),'memberInfo' => $member->toArray()]);
            $checksum = md5($this->md5Key.$data);
            $form = "<form method='POST' name='createform' action='http://www.zywapp.com/api/create'>";
            $form .= "<input type='hidden' name='data' value='".base64_encode($data)."'>";
            $form .= "<input type='hidden' name='checksum' value='".$checksum."'>";
			$form .= "</form>";
			$form .= "<script>document.forms['createform'].submit();</script>";
			return makeApiResponseSuccess('ok',['form' => $form]); 
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 在智应开通产品后，更新订单状态
     * @param Request $request
     */
    public function updateOrderStatus(Request $request){
        try {
            $orderId = $request->get('order_id');
            $order = OrderModel::query()->where('site_id',$this->siteId)->where('id',$orderId)->first();
            if(!$order){
                throw new \Exception('订单不存在');
            }
            $status = $request->get('status');
            $checksum = md5($this->md5Key.$orderId.$status);
            if($request->get('checksum') != $checksum){
                throw new \Exception('安全验证错误');
            }
            $order->status = $status;
            $order->save();
            if($status == Constants::OrderStatus_OrderSend) {
                OrderItemModel::query()->where(['order_id' => $orderId,'site_id' => $this->siteId])->update(['delivery_status' => 1]);
            }
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }

    /**
     * 在智应开通产品后，更新订单发货状态
     * @param Request $request
     */
    public function updateOrderDeliveryStatus(Request $request){
        try {
            $orderId = $request->get('order_id');
            $order = OrderModel::query()->where('site_id',$this->siteId)->where('id',$orderId)->first();
            if(!$order){
                throw new \Exception('订单不存在');
            }
            $status = $request->get('status');
            $checksum = md5($this->md5Key.$orderId.$status);
            if($request->get('checksum') != $checksum){
                throw new \Exception('安全验证错误');
            }
            $order->delivery_status = $status;
            $order->save();
            return makeApiResponseSuccess('ok');
        } catch (\Exception $ex) {
            return makeApiResponseError($ex);
        }
    }
}