<?php

namespace App\Modules\ModuleShop\Libs\Model;

use YZ\Core\Model\BaseModel;

/**
 * 订单产品数据库模型
 */
class OrderItemModel extends BaseModel {

    protected $table = 'tbl_order_item';
    protected $primaryKey = 'id';

    public function order()
    {
        return $this->belongsTo('App\Modules\ModuleShop\Libs\Model\OrderModel','order_id');
    }

    public function afterSaleItem()
    {
        return $this->hasMany(AfterSaleItemModel::class, 'order_item_id');
    }
}