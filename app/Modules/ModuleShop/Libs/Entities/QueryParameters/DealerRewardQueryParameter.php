<?php
namespace App\Modules\ModuleShop\Libs\Entities\QueryParameters;

use App\Modules\ModuleShop\Libs\Entities\DealerRewardEntity;
use YZ\Core\Entities\IQueryParameter;
use YZ\Core\Entities\QueryParameterTrait;
use YZ\Core\Entities\Utils\EntityExecutionOptions;

class DealerRewardQueryParameter extends DealerRewardEntity implements IQueryParameter
{
    use QueryParameterTrait;

    public function __construct($map = null)
    {
        parent::__construct($map, EntityExecutionOptions::createNotWorkingInstance());
    }
}