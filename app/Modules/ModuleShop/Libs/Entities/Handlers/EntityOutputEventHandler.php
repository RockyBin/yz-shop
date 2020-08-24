<?php
/**
 * Created by Sound.
 */
namespace App\Modules\ModuleShop\Libs\Entities\Handlers;

use YZ\Core\Entities\BaseEntity;

class EntityOutputEventHandler
{
    static public function handleMoney(BaseEntity $entity, array &$outputData, string $outputItem)
    {
        $outputData[$outputItem] = moneyCent2Yuan($outputData[$outputItem]);
    }
}