<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Entities\Utils;

class BaseEvent
{
    /**
     * @var object|string
     */
    protected $object;
    /**
     * @var string|null
     */
    protected $method;
    /**
     * @var string
     */
    protected $eventKey;
    /**
     * @var array
     */
    protected $parameters = []; // 事件触发时的传参

    /**
     * BaseEvent constructor.
     * @param object|string $object 执行事件的对象或类
     * @param string|null $method 执行事件的方法
     * @param mixed ...$parameters 事件触发时的传参
     */
    public function __construct($object, string $method, ...$parameters)
    {
        $this->object = $object;
        $this->method = $method;
        $this->parameters = $parameters;
        $this->makeEventKey();
    }

    /**
     * 生成事件标识
     * @param string $eventKey
     */
    protected function makeEventKey(string $eventKey = '')
    {
        if (is_object($this->object)) {
            $this->eventKey = hash('md4', $eventKey . spl_object_hash($this->object) . $this->method);
        } else {
            $this->eventKey = hash('md4', $eventKey . $this->object . $this->method);
        }
    }

    /**
     * 设置事件触发时的传参
     * @param mixed $parameters 事件触发时的传参
     */
    public function setParameters(...$parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * 获取事件触发时的传参
     * @return array 事件触发时的传参
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * 获取事件回调的对象
     * @return object|string
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * 获取事件回调的方法
     * @return string|null
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * 获取事件标识
     * @return string
     */
    public function getEventKey()
    {
        return $this->eventKey;
    }
}