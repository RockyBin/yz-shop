<?php
/**
 * User: liyaohui
 * Date: 2019/8/26
 * Time: 15:21
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class CloudStockTakeDeliveryOrderItemModel extends BaseModel
{
    protected $table = 'tbl_cloudstock_take_delivery_order_item';

    public function order()
    {
        return $this->belongsTo(CloudStockTakeDeliveryOrderModel::class, 'order_id');
    }
}