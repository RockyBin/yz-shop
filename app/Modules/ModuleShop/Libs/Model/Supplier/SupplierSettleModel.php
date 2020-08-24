<?php

namespace App\Modules\ModuleShop\Libs\Model\Supplier;


use YZ\Core\Model\BaseModel;


class SupplierSettleModel extends BaseModel
{
    protected $table = 'tbl_supplier_settle';
    public $timestamps = true;

    protected $fillable = [
        'site_id',
        'status',
        'supplier_member_id',
        'order_id',
        'money',
        'freight',
        'after_sale_money',
        'after_sale_freight',
        'created_at',
        'updated_at'
    ];
}
