<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 提现设置模块
 * Class WithdrawConfigModel
 * @package App\Modules\Model
 */
class CloudStockWithdrawConfigModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_cloudstock_withdraw_config';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'online_type',
        'offline_type',
        'platform_type',
        'min_money',
        'max_money',
        'poundage_rate',
        'withdraw_workday'
    ];

}