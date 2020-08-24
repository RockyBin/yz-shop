<?php
/**
 * Created by Sound.
 */
namespace App\Modules\ModuleShop\Libs\Entities\Handlers;

use YZ\Core\Entities\BaseEntity;

class EntityPropertyEventHandler
{
    static public function handleMoneyFromRequest(BaseEntity $entity, string $propertyName)
    {
        if ($entity->isRequest()) {
            $entity->$propertyName = moneyYuan2Cent($entity->$propertyName);
        }
    }

    static public function handleJsonDecode(BaseEntity $entity, string $propertyName)
    {
        if (!is_null($entity->getModel())) {
            $entity->$propertyName = json_Decode($entity->$propertyName);
        }
    }

}