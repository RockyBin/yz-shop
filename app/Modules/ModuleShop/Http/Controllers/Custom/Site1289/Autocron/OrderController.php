<?php
namespace App\Modules\ModuleShop\Http\Controllers\Custom\Site1289\Autocron;

use App\Http\Controllers\Controller;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * 虚拟商品订单自动发货
     */
    public function autoDelivery(){
        $orderList = OrderModel::query()->where(['site_id' => getCurrentSiteId(),'status' => Constants::OrderStatus_OrderPay,'delivery_status' => Constants::OrderDeliveryStatus_No])->get();
        foreach ($orderList as $item){
            //只是全部都是虚拟商品，才做自动发货
            if(intval($item->virtual_flag) === 1){
                $order = Order::find($item);
                $order->deliver([
                    'logistics_company' => 0,
                    'logistics_name' => '虚拟商品物流',
                    'logistics_no' => '请凭以上订单号到当地门店提货哟^_^'
                ]);
            }
        }
    }
}