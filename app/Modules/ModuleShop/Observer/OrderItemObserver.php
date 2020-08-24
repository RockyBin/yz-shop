<?php

namespace App\Modules\ModuleShop\Observer;
use App\Modules\ModuleShop\Libs\Model\OrderItemDiscountModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;

class OrderItemObserver {
    public function creating(OrderItemModel $model)
    {
        //同步订单商品的实付单价和实付金额
        $model = OrderHelper::syncOrderItemPrice($model, new OrderItemDiscountModel());
        return true;
    }

    public function updating(OrderItemModel $model)
    {
        //同步订单商品的实付单价和实付金额
        $discountModel = OrderItemDiscountModel::query()->where('item_id',$model->id)->first();
        if(!$discountModel) $discountModel = new OrderItemDiscountModel();
        $model = OrderHelper::syncOrderItemPrice($model, $discountModel);
        return true;
    }
}
