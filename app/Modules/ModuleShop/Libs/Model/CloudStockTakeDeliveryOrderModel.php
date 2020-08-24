<?php
/**
 * 云仓提货订单
 * User: liyaohui
 * Date: 2019/8/26
 * Time: 14:47
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class CloudStockTakeDeliveryOrderModel extends BaseModel
{
    protected $table = 'tbl_cloudstock_take_delivery_order';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = ["address_id","country","prov","city","area","receiver_address","receiver_name","receiver_tel","remark","logistics_id","delivery_status","remark_inside","product_num","pay_type","pay_at"];

    public function item()
    {
        return $this->hasMany(CloudStockTakeDeliveryOrderItemModel::class, 'order_id');
    }
}