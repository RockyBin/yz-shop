<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Services;

class ServiceProxy
{
    /**
     * @var BaseService
     */
    private $serviceMandator;

    private function __construct(BaseService $serviceMandator)
    {
        $this->serviceMandator = $serviceMandator;
    }

    static public function bing(BaseService &$serviceMandator)
    {
        $serviceMandator = new self($serviceMandator);
    }

    /**
     * @param BaseService[] $bingObjects
     */
    static public function bings(array $bingObjects)
    {
        foreach ($bingObjects as &$bingObject) {
            self::bing($bingObject);
        }
    }

    public function __call($name, $arguments)
    {
        return $this->serviceMandator->invokeMethod($name, $arguments);
    }

    public function __set($name, $value)
    {
        if (!is_object($this->serviceMandator)) {
            return false;
        }
        $this->serviceMandator->$name = $value;
    }

    public function __get($name)
    {
        if (!is_object($this->serviceMandator)) {
            return false;
        }
        return $this->serviceMandator->$name;
    }
}