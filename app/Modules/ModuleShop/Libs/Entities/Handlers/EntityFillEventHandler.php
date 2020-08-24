<?php
/**
 * Created by Sound.
 */
namespace App\Modules\ModuleShop\Libs\Entities\Handlers;

use YZ\Core\Entities\BaseEntity;

class EntityFillEventHandler
{
    static public function handleJsonEncode(BaseEntity $entity, array &$fillData, string $propertyName)
    {
        $fillData[$propertyName] = json_encode($fillData[$propertyName]);
    }
}