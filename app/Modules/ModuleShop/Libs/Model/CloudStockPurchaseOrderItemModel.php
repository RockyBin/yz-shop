<?php
/**
 * 云仓进货订单关联商品
 * User: liyaohui
 * Date: 2019/8/23
 * Time: 14:09
 */

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;

class CloudStockPurchaseOrderItemModel extends BaseModel
{
    protected $table = 'tbl_cloudstock_purchase_order_item';

    public function order()
    {
        return $this->belongsTo(CloudStockPurchaseOrderModel::class, 'order_id');
    }
}