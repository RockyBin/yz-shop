<?php
namespace App\Modules\ModuleShop\Libs\Entities\Traits;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Entities\DealerLevelEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerOrderRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerPerformanceRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerRecommendRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\DealerSaleRewardEntity;
use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityFillEventHandler;
use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityOutputEventHandler;
use App\Modules\ModuleShop\Libs\Entities\Handlers\EntityPropertyEventHandler;
use App\Modules\ModuleShop\Libs\Entities\MemberEntity;
use App\Modules\ModuleShop\Libs\Model\DealerLevelModel;
use App\Modules\ModuleShop\Libs\Model\DealerOrderRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerPerformanceRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerRecommendRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerRewardModel;
use App\Modules\ModuleShop\Libs\Model\DealerSaleRewardModel;
use Exception;
use YZ\Core\Entities\Utils\EntityFillEvent;
use YZ\Core\Entities\Utils\EntityOutputEvent;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\PropertyMapping;
use YZ\Core\Model\MemberModel;

trait DealerRewardEntityTrait
{
    /**
     * 属性事件预设方法
     */
    protected function presetPropertyEvent()
    {
        $this->addEntityPropertyEvent(new EntityPropertyEvent(self::REWARD_MONEY, EntityPropertyEventHandler::class, 'handleMoneyFromRequest'));

        $this->addEntityPropertyEvent(new EntityPropertyEvent(self::STATUS, self::class, 'handleSetStatusText'));
        $this->addEntityPropertyEvent(new EntityPropertyEvent(self::TYPE, self::class, 'handleSetTypeText'));
        $this->addEntityPropertyEvent(new EntityPropertyEvent(self::PAY_MEMBER_ID, self::class, 'handleSetPayMember'));
    }

    /**
     * 输出事件预设方法
     */
    protected function presetOutputEvent()
    {
        $this->addEntityOutputEvent(new EntityOutputEvent(DealerOrderRewardEntity::ORDER_MONEY, EntityOutputEventHandler::class, 'handleMoney'));
        $this->addEntityOutputEvent(new EntityOutputEvent(self::REWARD_MONEY, EntityOutputEventHandler::class, 'handleMoney'));
    }

    /**
     * 关联数据预设方法
     * @throws Exception
     */
    protected function presetRelatedData()
    {
        self::setPublicRelatedData($this);
    }

    /**
     * 填充事件预设方法
     */
    protected function presetFillEvent()
    {
        $this->addEntityFillEvent(new EntityFillEvent(self::ABOUT, EntityFillEventHandler::class, "handleJsonEncode"));
    }

    /**
     * 公共的关联数据预设方法
     * @param DealerRewardEntity $entity
     * @throws Exception
     */
    static public function setPublicRelatedData(DealerRewardEntity $entity)
    {
        $entity->addEntityRelatedData(new EntityRelatedData(self::MEMBER_ID, MemberModel::class, MemberEntity::class,MemberEntity::ID,
            [
                new PropertyMapping(MemberEntity::NAME, 'member_'.MemberEntity::NAME),
                new PropertyMapping(MemberEntity::NICKNAME, 'member_'.MemberEntity::NICKNAME),
                new PropertyMapping(MemberEntity::MOBILE, 'member_'.MemberEntity::MOBILE)
            ]));

        $entity->addEntityRelatedData(new EntityRelatedData(self::PAY_MEMBER_ID, MemberModel::class, MemberEntity::class,MemberEntity::ID,
            [
                new PropertyMapping(MemberEntity::NAME, 'pay_member_'.MemberEntity::NAME),
                new PropertyMapping(MemberEntity::NICKNAME, 'pay_member_'.MemberEntity::NICKNAME),
                new PropertyMapping(MemberEntity::MOBILE, 'pay_member_'.MemberEntity::MOBILE)
            ]));
    }

    /**
     * 关联数据预设事件处理方法
     * @param DealerRewardEntity $entity
     * @param int $type
     * @throws Exception
     */
    static public function handlePresetRelatedData(DealerRewardEntity $entity, int $type)
    {
        self::setPublicRelatedData($entity);

        switch ($type) {
            case Constants::DealerRewardType_Performance:
                $entity->addEntityRelatedData(new EntityRelatedData(self::ID, DealerPerformanceRewardModel::class, DealerPerformanceRewardEntity::class, DealerPerformanceRewardEntity::REWARD_ID, ['*'], [DealerPerformanceRewardEntity::ID]));
                break;
            case Constants::DealerRewardType_Recommend:
                $entity->addEntityRelatedData(new EntityRelatedData(self::ID, DealerRecommendRewardModel::class, DealerRecommendRewardEntity::class, DealerRecommendRewardEntity::REWARD_ID, ['*'], [DealerRecommendRewardEntity::ID]));
                break;
            case Constants::DealerRewardType_Sale:
                $entity->addEntityRelatedData(new EntityRelatedData(self::ID, DealerSaleRewardModel::class, DealerSaleRewardEntity::class, DealerSaleRewardEntity::REWARD_ID, ['*'], [DealerSaleRewardEntity::ID]));
                break;
            case Constants::DealerRewardType_Order:
                $entity->addEntityRelatedData(new EntityRelatedData(self::ID, DealerOrderRewardModel::class, DealerOrderRewardEntity::class, DealerOrderRewardEntity::REWARD_ID, ['*'], [DealerOrderRewardEntity::ID]));
                break;
        }
    }

    /**
     * status属性事件处理方法
     * @param DealerRewardEntity $entity
     * @param string $propertyName
     */
    static public function handleSetStatusText(DealerRewardEntity $entity, string $propertyName)
    {
        switch($entity->status) {
            case 0:
                $entity->status_text = '待兑换';
                break;
            case 1:
                $entity->status_text = '待审核';
                break;
            case 2:
                $entity->status_text = '已发放';
                break;
            case 3:
                $entity->status_text = '已拒绝';
                break;
            default:
                $entity->status_text = '未知';
                break;
        }
    }

    /**
     * type属性事件处理方法
     * @param DealerRewardEntity $entity
     * @param string $propertyName
     */
    static public function handleSetTypeText(DealerRewardEntity $entity, string $propertyName)
    {
        switch($entity->type) {
            case 1:
                $entity->type_text = '业绩奖';
                break;
            case 2:
                $entity->type_text = '推荐奖';
                break;
            case 3:
                $entity->type_text = '销售奖';
                break;
            case 4:
                $entity->type_text = '订货返现奖';
                break;
            default:
                $entity->type_text = '未知';
                break;
        }
    }

    /**
     * pay_member属性事件处理方法
     * @param DealerRewardEntity $entity
     * @param string $propertyName
     */
    static public function handleSetPayMember(DealerRewardEntity $entity, string $propertyName)
    {
        if($entity->pay_member_id === 0)
        {
            $entity->pay_member_name = '公司支付';
            $entity->pay_member_nickname = '公司支付';
            $entity->pay_member_mobile = null;
        }
    }

    public function setTypeText()
    {
        self::handleSetTypeText($this, self::TYPE);
    }

    public function setStatusText()
    {
        self::handleSetStatusText($this, self::STATUS);
    }

    /**
     * @return MemberEntity|null
     */
    public function getMemberEntity()
    {
        return $this->getRelatedEntity(new EntityRelatedData(self::MEMBER_ID, MemberModel::class, MemberEntity::class,MemberEntity::ID));
    }

    /**
     * @return MemberEntity|null
     */
    public function getPayMemberEntity()
    {
        return $this->getRelatedEntity(new EntityRelatedData(self::PAY_MEMBER_ID, MemberModel::class, MemberEntity::class,MemberEntity::ID));
    }

    /**
     * @return DealerOrderRewardEntity|null
     */
    public function getDealerRewardOrderEntity()
    {
        return $this->getRelatedEntity(new EntityRelatedData(self::ID, DealerOrderRewardModel::class, DealerOrderRewardEntity::class, DealerOrderRewardEntity::REWARD_ID));
    }
}