<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

class EntityFillEvent extends BaseEvent
{
    protected $fillItem;

    /**
     * EntityFillEvent constructor.
     * @param string $fillItem 触发事件的FillItem
     * @param object|string $object 执行事件的对象或类
     * @param string $method 执行事件的方法
     * @param mixed ...$parameters 事件触发时的传参
     */
    public function __construct(string $fillItem, $object, string $method, ...$parameters)
    {
        $this->fillItem = $fillItem;
        parent::__construct($object, $method, ...$parameters);
    }

    /**
     * 获取触发事件的FillItem
     * @return string
     */
    public function getFillItem()
    {
        return $this->fillItem;
    }

    protected function makeEventKey(string $eventKey = '')
    {
        parent::makeEventKey($eventKey . $this->fillItem);
    }
}