<?php

namespace App\Modules\ModuleShop\Libs\Model\Supplier;

use YZ\Core\Model\BaseModel;

class SupplierSettleItemModel extends BaseModel
{
    protected $table = 'tbl_supplier_settle_item';
    public $timestamps = true;

    protected $fillable = [
        'site_id',
        'supplier_member_id',
        'order_id',
        'item_id',
        'money',
        'after_sale_num',
        'after_sale_money',
        'created_at',
        'updated_at'
    ];
}
