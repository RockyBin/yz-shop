<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

use Exception;

class EntityExecutionActions
{
    private $entityPropertyEvents = []; // Entity属性事件链
    private $entityRelatedDataList = []; // Entity关联数据链
    private $entityFillEvents = []; // Entity填充事件链
    private $entityOutputEvents = []; // Entity输出事件链

    /**
     * EntityExecutionActions constructor.
     * @param array|null $entityPropertyEvents Entity属性事件链
     * @param array|null $entityRelatedDataList Entity关联数据链
     * @param array|null $entityFillEvents Entity填充事件链
     * @param array|null $entityOutputEvents Entity输出事件链
     */
    public function __construct(array $entityPropertyEvents = null, array $entityRelatedDataList = null, array $entityFillEvents = null, array $entityOutputEvents = null)
    {
        if(!is_null($entityPropertyEvents)) $this->entityPropertyEvents = $entityPropertyEvents;
        if(!is_null($entityRelatedDataList)) $this->entityRelatedDataList = $entityRelatedDataList;
        if(!is_null($entityFillEvents))  $this->entityFillEvents = $entityFillEvents;
        if(!is_null($entityOutputEvents))  $this->entityOutputEvents = $entityOutputEvents;
    }

    /**
     * 获取Entity属性事件链
     * @return EntityPropertyEvent[]
     */
    public function getEntityPropertyEvents()
    {
        return $this->entityPropertyEvents;
    }

    /**
     * 设置Entity属性事件链
     * @param EntityPropertyEvent[]|null $entityPropertyEvents
     */
    public function setEntityPropertyEvents(array $entityPropertyEvents = null)
    {
        $this->entityPropertyEvents = is_null($entityPropertyEvents) ? [] : $entityPropertyEvents;
    }

    /**
     * 获取Entity关联数据链
     * @return EntityRelatedData[]
     */
    public function getEntityRelatedDataList()
    {
        return $this->entityRelatedDataList;
    }

    /**
     * 设置Entity关联数据链
     * @param EntityRelatedData[]|null $entityRelatedDataList
     */
    public function setEntityRelatedDataList(array $entityRelatedDataList = null)
    {
        $this->entityRelatedDataList = is_null($entityRelatedDataList) ? [] : $entityRelatedDataList;
    }

    /**
     * 获取Entity填充事件链
     * @return EntityFillEvent[]
     */
    public function getEntityFillEvents()
    {
        return $this->entityFillEvents;
    }

    /**
     * 设置Entity填充事件链
     * @param EntityFillEvent[]|null $entityFillEvents
     */
    public function setEntityFillEvents(array $entityFillEvents = null)
    {
        $this->entityFillEvents = is_null($entityFillEvents) ? [] : $entityFillEvents;
    }

    /**
     * 获取Entity输出事件链
     * @return EntityOutputEvent[]
     */
    public function getEntityOutputEvents()
    {
        return $this->entityOutputEvents;
    }

    /**
     * 设置Entity输出事件链
     * @param EntityOutputEvent[]|null $entityOutputEvents
     */
    public function setEntityOutputEvents(array $entityOutputEvents = null)
    {
        $this->$entityOutputEvents = is_null($entityOutputEvents) ? [] : $entityOutputEvents;
    }

    /**
     * 新增一个Entity属性事件到Entity属性事件链中
     * @param EntityPropertyEvent $entityPropertyEvent 新增的Entity属性事件
     * @throws Exception
     */
    public function addEntityPropertyEvent(EntityPropertyEvent $entityPropertyEvent)
    {
        if (!array_key_exists($entityPropertyEvent->getEventKey(), $this->entityPropertyEvents)) {
            $this->entityPropertyEvents[$entityPropertyEvent->getEventKey()] = $entityPropertyEvent;
        } else {
            throw new Exception("[EntityPropertyEvent]Entity属性事件链中已存在相同Key的Entity属性事件。[{$entityPropertyEvent->getPropertyName()} - {$entityPropertyEvent->getObject()} - {$entityPropertyEvent->getMethod()}]");
        }
    }

    /**
     * 从Entity属性事件链中移除单个Entity属性事件
     * @param EntityPropertyEvent $entityPropertyEvent 移除的Entity属性事件
     */
    public function removeEntityPropertyEvent(EntityPropertyEvent $entityPropertyEvent)
    {
        if(array_key_exists($entityPropertyEvent->getEventKey(), $this->entityPropertyEvents)) {
            unset($this->entityPropertyEvents[$entityPropertyEvent->getEventKey()]);
        }
    }

    /**
     * 新增一个Entity关联数据到Entity关联数据链中
     * @param EntityRelatedData $entityRelatedData 新增的Entity关联数据
     * @throws Exception
     */
    public function addEntityRelatedData(EntityRelatedData $entityRelatedData)
    {
        if (!array_key_exists($entityRelatedData->getRelatedDataKey(), $this->entityRelatedDataList)) {
            $this->entityRelatedDataList[$entityRelatedData->getRelatedDataKey()] = $entityRelatedData;
        } else {
            throw new Exception('[EntityRelatedData]Entity关联数据链中已存在相同Key的Entity关联数据。');
        }
    }

    /**
     * 从Entity关联数据链中移除单个Entity关联数据
     * @param EntityRelatedData $entityRelatedData 移除的Entity关联数据
     */
    public function removeEntityRelatedData(EntityRelatedData $entityRelatedData)
    {
        if(array_key_exists($entityRelatedData->getRelatedDataKey(), $this->entityRelatedDataList)) {
            unset($this->entityRelatedDataList[$entityRelatedData->getRelatedDataKey()]);
        }
    }

    /**
     * 新增一个Entity填充事件到Entity填充事件链中
     * @param EntityFillEvent $entityFillEvent 新增的Entity填充事件
     * @throws Exception
     */
    public function addEntityFillEvent(EntityFillEvent $entityFillEvent)
    {
        if(!array_key_exists($entityFillEvent->getEventKey(), $this->entityFillEvents)) {
            $this->entityFillEvents[$entityFillEvent->getEventKey()] = $entityFillEvent;
        } else {
            throw new Exception("[EntityFillEvent]Entity填充事件链中已存在相同Key的Entity填充事件。[{$entityFillEvent->getFillItem()} - {$entityFillEvent->getObject()} - {$entityFillEvent->getMethod()}]");
        }
    }

    /**
     * 从Entity填充事件链中移除单个Entity填充事件
     * @param EntityFillEvent $entityFillEvent 移除的Entity填充事件
     */
    public function removeEntityFillEvent(EntityFillEvent $entityFillEvent)
    {
        if(array_key_exists($entityFillEvent->getEventKey(), $this->entityFillEvents)) {
            unset($this->entityFillEvents[$entityFillEvent->getEventKey()]);
        }
    }

    /**
     * 新增一个Entity输出事件到Entity输出事件链中
     * @param EntityOutputEvent $entityOutputEvent 新增的Entity输出事件
     * @throws Exception
     */
    public function addEntityOutputEvent(EntityOutputEvent $entityOutputEvent)
    {
        if (!array_key_exists($entityOutputEvent->getEventKey(), $this->entityOutputEvents)) {
            $this->entityOutputEvents[$entityOutputEvent->getEventKey()] = $entityOutputEvent;
        } else {
            throw new Exception("[EntityOutputEvent]Entity输出事件链中已存在相同Key的Entity填充事件。[{$entityOutputEvent->getOutputItem()} - {$entityOutputEvent->getObject()} - {$entityOutputEvent->getMethod()}]");
        }
    }

    /**
     * 从Entity输出事件链中移除单个Entity输出事件
     * @param EntityOutputEvent $entityOutputEvent 移除的Entity输出事件
     */
    public function removeEntityOutputEvent(EntityOutputEvent $entityOutputEvent)
    {
        if(array_key_exists($entityOutputEvent->getEventKey(), $this->entityOutputEvents)) {
            unset($this->entityOutputEvents[$entityOutputEvent->getEventKey()]);
        }
    }
}