<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 订单设置模块
 * Class OrderConfigModel
 * @package App\Modules\Model
 */
class OrderConfigModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_order_config';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'nopay_close_day',
        'nopay_close_hour',
        'nopay_close_minute',
        'ordersend_success_day',
        'ordersend_close_day',
        'nopay_notice_minute',
        'aftersale_isopen'
    ];
}