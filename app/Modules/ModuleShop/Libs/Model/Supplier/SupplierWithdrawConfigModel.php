<?php

namespace App\Modules\ModuleShop\Libs\Model\Supplier;

/**
 * 提现设置模块
 * Class WithdrawConfigModel
 * @package App\Modules\Model
 */
class SupplierWithdrawConfigModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_supplier_withdraw_config';
    protected $primaryKey = 'site_id';
    protected $fillable = [
        'offline_type',
        'max_money',
        'min_money',
        'poundage_rate',
        'withdraw_workday',
        'withdraw_date'
    ];

}