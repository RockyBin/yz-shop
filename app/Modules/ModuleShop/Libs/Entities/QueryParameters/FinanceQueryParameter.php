<?php
namespace App\Modules\ModuleShop\Libs\Entities\QueryParameters;

use App\Modules\ModuleShop\Libs\Entities\FinanceEntity;
use YZ\Core\Entities\IQueryParameter;
use YZ\Core\Entities\QueryParameterTrait;
use YZ\Core\Entities\Utils\EntityExecutionOptions;

class FinanceQueryParameter extends FinanceEntity implements IQueryParameter
{
    use QueryParameterTrait;

    public function __construct($map = null)
    {
        parent::__construct($map, EntityExecutionOptions::createNotWorkingInstance());
    }
}