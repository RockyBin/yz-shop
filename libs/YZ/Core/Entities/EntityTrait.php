<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities;

use Exception;
use YZ\Core\Entities\Utils\EntityExecutionActions;
use YZ\Core\Entities\Utils\EntityExecutionOptions;
use YZ\Core\Entities\Utils\EntityExecutionPresets;
use YZ\Core\Entities\Utils\EntityFillEvent;
use YZ\Core\Entities\Utils\EntityOutputEvent;
use YZ\Core\Entities\Utils\EntityPropertyEvent;
use YZ\Core\Entities\Utils\EntityRelatedData;
use YZ\Core\Entities\Utils\FillEventPresetEvent;
use YZ\Core\Entities\Utils\OutputEventPresetEvent;
use YZ\Core\Entities\Utils\PropertyEventPresetEvent;
use YZ\Core\Entities\Utils\RelatedDataPresetEvent;

trait EntityTrait
{
    // Entity执行动作-------Start
    /**
     * @var EntityExecutionActions $entityExecutionActions
     */
    private $entityExecutionActions;

    /**
     * 获取Entity的Entity执行动作
     * @return EntityExecutionActions Entity执行动作
     */
    public function getEntityExecutionActions(): EntityExecutionActions
    {
        return $this->entityExecutionActions;
    }

    /**
     * 设置Entity的Entity执行动作
     * @param EntityExecutionActions $entityExecutionActions Entity执行动作
     */
    public function setEntityExecutionActions(EntityExecutionActions $entityExecutionActions)
    {
        if (is_null($entityExecutionActions)) {
            $this->entityExecutionActions = new EntityExecutionOptions();
        } else {
            $this->entityExecutionActions = $entityExecutionActions;
        }
    }

    // Entity执行动作中各属性Get/Set-------Start
    // $entityPropertyEvents Entity属性事件链
    /**
     * 获取Entity属性事件链
     * @return EntityPropertyEvent[] Entity属性事件链
     */
    public function getEntityPropertyEvents()
    {
        return $this->entityExecutionActions->getEntityPropertyEvents();
    }

    /**
     * 设置Entity属性事件链
     * @param EntityPropertyEvent[]|null $entityPropertyEvents Entity属性事件链
     */
    public function setEntityPropertyEvens(array $entityPropertyEvents = null)
    {
        $this->entityExecutionActions->setEntityPropertyEvents($entityPropertyEvents);
    }

    // $entityRelatedDataList Entity关联数据链
    /**
     * 获取Entity关联数据链
     * @return EntityRelatedData[] Entity关联数据链
     */
    public function getEntityRelatedDataList()
    {
        return $this->entityExecutionActions->getEntityRelatedDataList();
    }

    /**
     * 设置Entity属性事件链
     * @param EntityRelatedData[]|null $entityRelatedDataList Entity关联数据链
     */
    public function setEntityRelatedDataList(array $entityRelatedDataList = null)
    {
        $this->entityExecutionActions->setEntityRelatedDataList($entityRelatedDataList);
    }

    // $entityFillEvents Entity填充事件链
    /**
     * 获取Entity填充事件链
     * @return EntityFillEvent[] Entity填充事件链
     */
    public function getEntityFillEvents()
    {
        return $this->entityExecutionActions->getEntityFillEvents();
    }

    /**
     * 设置Entity填充事件链
     * @param EntityFillEvent[]|null $entityFillEvents Entity填充事件链
     */
    public function setEntityFillEvens(array $entityFillEvents)
    {
        $this->entityExecutionActions->setEntityFillEvents($entityFillEvents);
    }

    // $entityOutputEvents Entity输出事件链
    /**
     * 获取Entity输出事件链
     * @return EntityOutputEvent[] Entity输出事件链
     */
    public function getEntityOutputEvents()
    {
        return $this->entityExecutionActions->getEntityOutputEvents();
    }

    /**
     * 设置Entity输出事件链
     * @param EntityOutputEvent[]|null $entityOutputEvents Entity填充事件链
     */
    public function setEntityOutputEvens(array $entityOutputEvents)
    {
        $this->entityExecutionActions->setEntityOutputEvents($entityOutputEvents);
    }
    // Entity执行动作中各属性Get/Set-------End
    /**
     * 新增一个Entity属性事件到Entity属性事件链中
     * @param EntityPropertyEvent $entityPropertyEvent 新增的Entity属性事件
     * @throws Exception
     */
    public function addEntityPropertyEvent(EntityPropertyEvent $entityPropertyEvent)
    {
        $this->entityExecutionActions->addEntityPropertyEvent($entityPropertyEvent);
    }

    /**
     * 从Entity属性事件链中移除单个Entity属性事件
     * @param EntityPropertyEvent $entityPropertyEvent 移除的Entity属性事件
     */
    public function removeEntityPropertyEvent(EntityPropertyEvent $entityPropertyEvent)
    {
        $this->entityExecutionActions->removeEntityPropertyEvent($entityPropertyEvent);
    }

    /**
     * 新增一个Entity关联数据到Entity关联数据链中
     * @param EntityRelatedData $entityRelatedData 新增的Entity关联数据
     * @throws Exception
     */
    public function addEntityRelatedData(EntityRelatedData $entityRelatedData)
    {
        $this->entityExecutionActions->addEntityRelatedData($entityRelatedData);
    }

    /**
     * 从Entity关联数据链中移除单个Entity关联数据
     * @param EntityRelatedData $entityRelatedData 移除的Entity关联数据
     */
    public function removeEntityRelatedData(EntityRelatedData $entityRelatedData)
    {
        $this->entityExecutionActions->removeEntityRelatedData($entityRelatedData);
    }

    /**
     * 新增一个Entity填充事件到Entity填充事件链中
     * @param EntityFillEvent $entityFillEvent 新增的Entity填充事件
     * @throws Exception
     */
    public function addEntityFillEvent(EntityFillEvent $entityFillEvent)
    {
        $this->entityExecutionActions->addEntityFillEvent($entityFillEvent);
    }

    /**
     * 从Entity填充事件链中移除单个Entity填充事件
     * @param EntityFillEvent $entityFillEvent 移除的Entity填充事件
     */
    public function removeEntityFillEvent(EntityFillEvent $entityFillEvent)
    {
        $this->entityExecutionActions->removeEntityFillEvent($entityFillEvent);
    }

    /**
     * 新增一个Entity输出事件到Entity输出事件链中
     * @param EntityOutputEvent $entityOutputEvent 新增的Entity输出事件
     * @throws Exception
     */
    public function addEntityOutputEvent(EntityOutputEvent $entityOutputEvent)
    {
        $this->entityExecutionActions->addEntityOutputEvent($entityOutputEvent);
    }

    /**
     * 从Entity输出事件链中移除单个Entity输出事件
     * @param EntityOutputEvent $entityOutputEvent 移除的Entity输出事件
     */
    public function removeEntityOutputEvent(EntityOutputEvent $entityOutputEvent)
    {
        $this->entityExecutionActions->removeEntityOutputEvent($entityOutputEvent);
    }
    // Entity执行动作-------End

    // Entity执行选项-------Start
    /**
     * @var EntityExecutionOptions
     */
    private $entityExecutionOptions;

    /**
     * 获取Entity的Entity执行选项
     * @return EntityExecutionOptions Entity执行选项
     */
    public function getEntityExecutionOptions(): EntityExecutionOptions
    {
        return $this->entityExecutionOptions;
    }

    /**
     * 设置Entity的Entity执行选项
     * @param EntityExecutionOptions $entityExecutionOptions Entity执行选项
     */
    public function setEntityExecutionOptions(EntityExecutionOptions $entityExecutionOptions)
    {
        if (is_null($entityExecutionOptions)) {
            $this->entityExecutionOptions = new EntityExecutionOptions();
        } else {
            $this->entityExecutionOptions = $entityExecutionOptions;
        }
    }

    // Entity执行选项中各属性Get/Set-------Start
    // $relatedCount 关联数据层级计数
    /**
     * 获取Entity的关联数据层级计数
     * @return int 关联数据层级计数
     */
    public function getRelatedCount(): int
    {
        return $this->entityExecutionOptions->relatedCount;
    }

    /**
     * 设置Entity的关联数据层级计数
     * @param int $relatedCount 关联数据层级计数
     */
    public function setRelatedCount(int $relatedCount)
    {
        $this->entityExecutionOptions->relatedCount = $relatedCount < -1 ? -1 : $relatedCount;
    }

    // $useHiddenProperty 使用隐藏属性开关
    /**
     * 获取Entity的使用隐藏属性开关
     * @return bool 使用隐藏属性开关
     */
    public function getUseHiddenProperty(): bool
    {
        return $this->entityExecutionOptions->useHiddenProperty;
    }

    /**
     * 设置Entity的使用隐藏属性开关
     * @param bool $useHiddenProperty 使用隐藏属性开关
     */
    public function setUseHiddenProperty(bool $useHiddenProperty)
    {
        $this->entityExecutionOptions->useHiddenProperty = $useHiddenProperty;
    }

    // $usePropertyEvent 使用属性事件开关
    /**
     * 获取Entity的使用属性事件开关
     * @return bool 使用属性事件开关
     */
    public function getUsePropertyEvent(): bool
    {
        return $this->entityExecutionOptions->usePropertyEvent;
    }

    /**
     * 设置Entity的使用属性事件开关
     * @param bool $usePropertyEvent 使用属性事件开关
     */
    public function setUsePropertyEvent(bool $usePropertyEvent)
    {
        $this->entityExecutionOptions->usePropertyEvent = $usePropertyEvent;
    }

    // $isLoadPresetPropertyEvent 加载预设属性事件开关
    /**
     * 获取Entity的加载预设属性事件开关
     * @return bool 加载预设属性事件开关
     */
    public function getIsLoadPresetPropertyEvent(): bool
    {
        return $this->entityExecutionOptions->isLoadPresetPropertyEvent;
    }

    /**
     * 设置Entity的加载预设属性事件开关
     * @param bool $isLoadPresetPropertyEvent 加载预设属性事件开关
     */
    public function setIsLoadPresetPropertyEvent(bool $isLoadPresetPropertyEvent)
    {
        $this->entityExecutionOptions->isLoadPresetPropertyEvent = $isLoadPresetPropertyEvent;
    }

    // $useRelatedData 使用关联数据开关
    /**
     * 获取Entity的使用关联数据开关
     * @return bool 使用关联数据开关
     */
    public function getUseRelatedData(): bool
    {
        return $this->entityExecutionOptions->useRelatedData;
    }

    /**
     * 设置Entity的使用关联数据开关
     * @param bool $useRelatedData 使用关联数据开关
     */
    public function setUseRelatedData(bool $useRelatedData)
    {
        $this->entityExecutionOptions->useRelatedData = $useRelatedData;
    }

    // $isLoadPresetRelatedData 加载预设关联数据开关
    /**
     * 获取Entity的加载预设关联数据开关
     * @return bool 加载预设关联数据开关
     */
    public function getIsLoadPresetRelatedData(): bool
    {
        return $this->entityExecutionOptions->isLoadPresetRelatedData;
    }

    /**
     * 设置Entity的加载预设关联数据开关
     * @param bool $isLoadPresetRelatedData 加载预设关联数据开关
     */
    public function setIsLoadPresetRelatedData(bool $isLoadPresetRelatedData)
    {
        $this->entityExecutionOptions->isLoadPresetRelatedData = $isLoadPresetRelatedData;
    }

    // $useFillEvent 使用填充事件开关
    /**
     * 获取Entity的使用填充事件开关
     * @return bool
     */
    public function getUseFillEvent(): bool
    {
        return $this->entityExecutionOptions->useFillEvent;
    }

    /**
     * 获取Entity的使用填充事件开关
     * @param bool $useFillEvent 使用填充事件开关
     */
    public function setUseFillEvent(bool $useFillEvent)
    {
        $this->entityExecutionOptions->useFillEvent = $useFillEvent;
    }

    // $isLoadPresetFillEvent 加载预设填充事件开关
    /**
     * 获取Entity的加载预设填充事件开关
     * @return bool 加载预设填充事件开关
     */
    public function getIsLoadPresetFillEvent(): bool
    {
        return $this->entityExecutionOptions->isLoadPresetFillEvent;
    }

    /**
     * 设置Entity的加载预设填充事件开关
     * @param bool $isLoadPresetFillEvent 加载预设填充事件开关
     */
    public function setIsLoadPresetFillEvent(bool $isLoadPresetFillEvent)
    {
        $this->entityExecutionOptions->isLoadPresetFillEvent = $isLoadPresetFillEvent;
    }

    // $useOutputEvent 使用填充事件开关
    /**
     * 获取Entity的使用输出事件开关
     * @return bool
     */
    public function getUseOutputEvent(): bool
    {
        return $this->entityExecutionOptions->useOutputEvent;
    }

    /**
     * 获取Entity的使用填充事件开关
     * @param bool $useOutputEvent 使用填充事件开关
     */
    public function setUseOutputEvent(bool $useOutputEvent)
    {
        $this->entityExecutionOptions->useOutputEvent = $useOutputEvent;
    }

    // $isLoadPresetOutputEvent 加载预设输出事件开关
    /**
     * 获取Entity的加载预设输出事件开关
     * @return bool 加载预设输出事件开关
     */
    public function getIsLoadPresetOutputEvent(): bool
    {
        return $this->entityExecutionOptions->isLoadPresetOutputEvent;
    }

    /**
     * 设置Entity的加载预设输出事件开关
     * @param bool $isLoadPresetOutputEvent 加载预设输出事件开关
     */
    public function setIsLoadPresetOutputEvent(bool $isLoadPresetOutputEvent)
    {
        $this->entityExecutionOptions->isLoadPresetOutputEvent = $isLoadPresetOutputEvent;
    }
    // Entity执行选项中各属性Get/Set-------End
    // Entity执行选项-------End

    // Entity执行预设-------Start
    /**
     * @var EntityExecutionPresets
     */
    private $entityExecutionPresets;

    /**
     * 获取Entity的Entity执行预设
     * @return EntityExecutionPresets Entity执行预设
     */
    public function getEntityExecutionPresets(): EntityExecutionPresets
    {
        return $this->entityExecutionPresets;
    }

    /**
     * 设置Entity的Entity执行预设
     * @param EntityExecutionPresets $entityExecutionPresets Entity执行预设
     */
    public function setEntityExecutionPresets(EntityExecutionPresets $entityExecutionPresets)
    {
        if (is_null($entityExecutionPresets)) {
            $this->entityExecutionPresets = new EntityExecutionPresets();
        } else {
            $this->entityExecutionPresets = $entityExecutionPresets;
        }
    }

    // Entity执行预设中各属性Get/Set-------Start
    // $propertyEventPresetEvent 属性事件的预设事件
    /**
     * 获取Entity的属性事件的预设事件
     * @return PropertyEventPresetEvent|null
     */
    public function getPropertyEventPresetEvent()
    {
        return $this->entityExecutionPresets->propertyEventPresetEvent;
    }

    /**
     * 设置Entity的属性事件的预设事件
     * @param PropertyEventPresetEvent|null $propertyEventPresetEvent
     */
    public function setPropertyEventPresetEvent(PropertyEventPresetEvent $propertyEventPresetEvent = null)
    {
        $this->entityExecutionPresets->propertyEventPresetEvent = $propertyEventPresetEvent;
    }

    // $relatedDataPresetEvent 关联数据的预设事件
    /**
     * 获取Entity的关联数据的预设事件
     * @return RelatedDataPresetEvent|null
     */
    public function getRelatedDataPresetEvent()
    {
        return $this->entityExecutionPresets->relatedDataPresetEvent;
    }

    /**
     * 设置Entity的关联数据的预设事件
     * @param RelatedDataPresetEvent|null $relatedDataPresetEvent
     */
    public function setRelatedDataPresetEvent(RelatedDataPresetEvent $relatedDataPresetEvent = null)
    {
        $this->entityExecutionPresets->relatedDataPresetEvent = $relatedDataPresetEvent;
    }

    // $fillEventPresetEvent 填充事件的预设事件
    /**
     * 获取Entity的填充事件的预设事件
     * @return FillEventPresetEvent|null
     */
    public function getFillEventPresetEvent()
    {
        return $this->entityExecutionPresets->fillEventPresetEvent;
    }

    /**
     * 设置Entity的填充事件的预设事件
     * @param FillEventPresetEvent|null $fillEventPresetEvent 填充事件的预设事件
     */
    public function setFillEventPresetEvent(FillEventPresetEvent $fillEventPresetEvent = null)
    {
        $this->entityExecutionPresets->fillEventPresetEvent = $fillEventPresetEvent;
    }

    // $outputEventPresetEvent 输出事件的预设事件
    /**
     * 获取Entity的输出事件的预设事件
     * @return OutputEventPresetEvent|null
     */
    public function getOutputEventPresetEvent()
    {
        return $this->entityExecutionPresets->outputEventPresetEvent;
    }

    /**
     * 设置Entity的输出事件的预设事件
     * @param OutputEventPresetEvent|null $outputEventPresetEvent 填充事件的预设事件
     */
    public function setOutputEventPresetEvent(OutputEventPresetEvent $outputEventPresetEvent = null)
    {
        $this->entityExecutionPresets->outputEventPresetEvent = $outputEventPresetEvent;
    }
    // Entity执行预设中各属性Get/Set-------End
    // Entity执行预设-------End
}