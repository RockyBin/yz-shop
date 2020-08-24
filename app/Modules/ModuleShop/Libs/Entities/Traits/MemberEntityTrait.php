<?php
namespace App\Modules\ModuleShop\Libs\Entities\Traits;

use App\Modules\ModuleShop\Libs\Entities\DealerLevelEntity;
use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityPropertyEventHandler;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;

trait MemberEntityTrait
{
    protected function presetRelatedData()
    {
        $this->addEntityRelatedData(new EntityRelatedData(self::DEALER_LEVEL, DealerLevelModel::class, DealerLevelEntity::class, DealerLevelEntity::ID,
            [
                new PropertyMapping(DealerLevelEntity::NAME, self::DEALER_LEVEL . '_name'),
            ]));

        $this->addEntityRelatedData(new EntityRelatedData(self::DEALER_HIDE_LEVEL, DealerLevelModel::class, DealerLevelEntity::class, DealerLevelEntity::ID,
            [
                new PropertyMapping(DealerLevelEntity::NAME, self::DEALER_HIDE_LEVEL . '_name'),
            ]));
    }

    /**
     * @return DealerLevelEntity|null
     */
    public function getDealerLevelEntity()
    {
        return $this->getRelatedEntity(new EntityRelatedData(self::DEALER_LEVEL, DealerLevelModel::class, DealerLevelEntity::class, DealerLevelEntity::ID));
    }

    /**
     * @return DealerLevelEntity|null
     */
    public function getDealerHideLevelEntity()
    {
        return $this->getRelatedEntity(new EntityRelatedData(self::DEALER_HIDE_LEVEL, DealerLevelModel::class, DealerLevelEntity::class, DealerLevelEntity::ID));
    }
}