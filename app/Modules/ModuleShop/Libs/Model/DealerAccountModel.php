<?php

namespace App\Modules\ModuleShop\Libs\Model;

/**
 * 经销商收款帐号模块
 * Class DealerAccountModel
 * @package App\Modules\Model
 */
class DealerAccountModel extends \YZ\Core\Model\BaseModel
{
    protected $table = 'tbl_dealer_account';
    protected $fillable = [
        'site_id',
        'member_id',
        'type',
        'account',
        'account_name',
        'bank'
    ];
}