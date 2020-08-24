<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 提现设置模块
 * Class WithdrawConfigModel
 * @package App\Modules\Model
 */
class WithdrawConfigModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_withdraw_config';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'balance_type',
        'commission_type',
        'min_money',
        'poundage_rate',
        'withdraw_workday',
        'withdraw_date'
    ];

}