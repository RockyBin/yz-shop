<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

class EntityOutputEvent extends BaseEvent
{
    protected $outputItem;

    /**
     * EntityOutputEvent constructor.
     * @param string $outputItem 触发事件的OutputItem
     * @param object|string $object 执行事件的对象或类
     * @param string $method 执行事件的方法
     * @param mixed ...$parameters 事件触发时的传参
     */
    public function __construct(string $outputItem, $object, string $method, ...$parameters)
    {
        $this->outputItem = $outputItem;
        parent::__construct($object, $method, ...$parameters);
    }

    /**
     * 获取触发事件的FillItem
     * @return string
     */
    public function getOutputItem()
    {
        return $this->outputItem;
    }

    protected function makeEventKey(string $eventKey = '')
    {
        parent::makeEventKey($eventKey . $this->outputItem);
    }
}