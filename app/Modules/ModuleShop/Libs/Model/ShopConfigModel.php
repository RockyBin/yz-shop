<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 商城设置模块
 * Class ShopConfigModel
 * @package App\Modules\Model
 */
class ShopConfigModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_shop_config';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'name',
        'logo',
        'register_isshow',
        'register_protocol',
        'industry_id',
        'industry_name',
        'describe',
        'product_sku_num'
    ];

}