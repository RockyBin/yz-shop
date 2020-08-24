<?php

namespace App\Modules\ModuleShop\Libs\Model;

class LogisticsModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_logistics';
    protected $fillable = [
        'site_id',
        'logistics_company',
        'logistics_name',
        'logistics_no',
        'member_id',
        'order_id',
        'created_at',
        'updated_at',
        'type'
    ];
}