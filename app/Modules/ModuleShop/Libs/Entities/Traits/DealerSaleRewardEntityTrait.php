<?php
namespace App\Modules\ModuleShop\Libs\Entities\Traits;

use App\Modules\ModuleShop\Libs\Entities\DealerLevelEntity;
use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityPropertyEventHandler;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use YZ\Core\Entities\Utils\EntityOutputEvent;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

trait DealerSaleRewardEntityTrait
{
    protected function presetPropertyEvent()
    {
        $this->addEntityPropertyEvent(new EntityPropertyEvent(self::ORDER_MONEY, EntityPropertyEventHandler::class, "handleMoneyFromRequest"));
        $this->addEntityPropertyEvent(new EntityPropertyEvent(self::REWARD_MONEY, EntityPropertyEventHandler::class, "handleMoneyFromRequest"));
    }

    protected function presetOutputEvent()
    {
        $this->addEntityOutputEvent(new EntityOutputEvent(self::ORDER_MONEY, EntityPropertyEventHandler::class, 'handleMoney'));
        $this->addEntityOutputEvent(new EntityOutputEvent(self::REWARD_MONEY, EntityPropertyEventHandler::class, 'handleMoney'));
    }

    protected function presetRelatedData()
    {
        $this->addEntityRelatedData(new EntityRelatedData(self::MEMBER_DEALER_LEVEL, DealerLevelModel::class, DealerLevelEntity::class, DealerLevelEntity::ID,
            [
                new PropertyMapping(DealerLevelEntity::NAME, self::MEMBER_DEALER_LEVEL . '_name'),
            ]));

        $this->addEntityRelatedData(new EntityRelatedData(self::MEMBER_DEALER_HIDE_LEVEL, DealerLevelModel::class, DealerLevelEntity::class, DealerLevelEntity::ID,
            [
                new PropertyMapping(DealerLevelEntity::NAME, self::MEMBER_DEALER_HIDE_LEVEL . '_name'),
            ]));
    }
}