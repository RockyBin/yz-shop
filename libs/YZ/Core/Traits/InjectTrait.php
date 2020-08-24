<?php
namespace YZ\Core\Traits;

use ReflectionClass;
use ReflectionException;
use YZ\Core\Services\BaseService;
use YZ\Core\Services\ServiceProxy;

trait InjectTrait
{
    /**
     * 注入方法，将创建好的对象实例注入到类中同名的属性
     * @param array $injectObjects 需要注入的对象的实例
     * @throws ReflectionException
     */
    public function inject(array $injectObjects)
    {
        $classInfo = new ReflectionClass(get_called_class());
        $parameters = $classInfo->getMethod('__construct')->getParameters();
        foreach ($parameters as $parameter) {
            if ($classInfo->hasProperty($parameter->getName()) && array_key_exists($parameter->getName(), $injectObjects)) {
                $injectObject = $injectObjects[$parameter->getName()];
                if ($injectObject instanceof BaseService) ServiceProxy::bing($injectObject);
                $property = $classInfo->getProperty($parameter->getName());
                $property->setAccessible(true);
                $property->setValue($this, $injectObject);
            }
        }
    }
}