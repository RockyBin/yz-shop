<?php
namespace App\Modules\ModuleShop\Libs\Entities\Traits;

use App\Modules\ModuleShop\Libs\Entities\DealerLevelEntity;
use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityOutputEventHandler;
use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityPropertyEventHandler;
use App\Modules\ModuleShop\Libs\Entities\MemberEntity;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use YZ\Core\Entities\Utils\EntityOutputEvent;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;
use YZ\Core\Model\MemberModel;

trait CloudstockPurchaseOrderEntityTrait
{
    protected function presetPropertyEvent()
    {
        $this->addEntityPropertyEvent(new EntityPropertyEvent(self::TOTAL_MONEY, EntityPropertyEventHandler::class, 'handleMoneyFromRequest'));
    }

    protected function presetOutputEvent()
    {
        $this->addEntityOutputEvent(new EntityOutputEvent(self::TOTAL_MONEY, EntityOutputEventHandler::class, 'handleMoney'));
    }

    protected function presetRelatedData()
    {
        $this->addEntityRelatedData(new EntityRelatedData(self::MEMBER_ID, MemberModel::class, MemberEntity::class, MemberEntity::ID));
    }

    /**
     * @return MemberEntity|null
     */
    public function getMemberEntity()
    {
        return $this->getRelatedEntity(new EntityRelatedData(self::MEMBER_ID, MemberModel::class, MemberEntity::class, MemberEntity::ID));
    }
}