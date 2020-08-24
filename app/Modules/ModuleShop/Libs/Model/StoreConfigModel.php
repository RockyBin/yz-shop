<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 商户设置模块
 * Class StoreConfigModel
 * @package App\Modules\Model
 */
class StoreConfigModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_store_config';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'id',
        'store_id',
        'store_name',
        'company_name',
        'store_address',
        'store_contacts',
        'store_mobile',
        'refunds_contacts',
        'refunds_mobile',
        'refunds_address',
        'custom_mobile'
    ];

}