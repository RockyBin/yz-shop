<?php

namespace App\Modules\ModuleShop\Observer;
use App\Modules\ModuleShop\Libs\Model\OrderItemDiscountModel;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;

class OrderItemDiscountObserver {
    public function created(OrderItemDiscountModel $model)
    {
        //同步订单商品的实付单价和实付金额
        $itemModel = OrderItemModel::find($model->item_id);
        $itemModel = OrderHelper::syncOrderItemPrice($itemModel, $model);
        $itemModel->removeObservableEvents('updating');
        $itemModel->save();
    }
}
