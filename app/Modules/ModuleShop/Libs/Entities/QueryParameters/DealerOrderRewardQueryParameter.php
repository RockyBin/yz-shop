<?php
namespace App\Modules\ModuleShop\Libs\Entities\QueryParameters;

class DealerOrderRewardQueryParameter extends DealerRewardQueryParameter
{
    public $level;
    public $level_type;
    public $status;
    public $payer;

    const LEVEL = 'level';
    const LEVEL_TYPE = 'level_type';
    const STATUS = 'status';
    const PAYER = 'payer';
}