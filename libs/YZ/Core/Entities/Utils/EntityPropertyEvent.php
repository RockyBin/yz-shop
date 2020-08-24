<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

class EntityPropertyEvent extends BaseEvent
{
    protected $propertyName;

    /**
     * EntityPropertyEvent constructor.
     * @param string $propertyName 触发事件的属性名称
     * @param object|string $object 执行事件的对象或类
     * @param string $method 执行事件的方法
     * @param mixed ...$parameters 事件触发时的传参
     */
    public function __construct(string $propertyName, $object, string $method, ...$parameters)
    {
        $this->propertyName = $propertyName;
        parent::__construct($object, $method, ...$parameters);
    }

    /**
     * 获取触发事件的属性名称
     * @return string
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * 生成属性事件标识
     * @param string $eventKey
     */
    protected function makeEventKey(string $eventKey = '')
    {
        parent::makeEventKey($eventKey . $this->propertyName);
    }
}