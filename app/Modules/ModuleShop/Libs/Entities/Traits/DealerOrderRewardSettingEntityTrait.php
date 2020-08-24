<?php
namespace App\Modules\ModuleShop\Libs\Entities\Traits;

use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityFillEventHandler;
use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityPropertyEventHandler;
use YZ\Core\Entities\Utils\EntityFillEvent;
use YZ\Core\Entities\Utils\EntityPropertyEvent;

Trait DealerOrderRewardSettingEntityTrait
{
    protected function presetPropertyEvent()
    {
        $this->addEntityPropertyEvent(new EntityPropertyEvent(self::REWARD_RULE, EntityPropertyEventHandler::class, 'handleJsonDecode'));
    }

    protected function presetFillEvent()
    {
        $this->addEntityFillEvent(new EntityFillEvent(self::REWARD_RULE, EntityFillEventHandler::class, 'handleJsonEncode'));
    }
}