<?php

namespace App\Modules\ModuleShop\Libs\Model;


use YZ\Core\Model\BaseModel;


class SmallShopProductModel extends BaseModel
{
    protected $table = 'tbl_small_shop_product';
    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'shop_id',
        'product_id',
        'show_status',
        'created_at'
    ];
}

