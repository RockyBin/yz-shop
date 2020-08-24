<?php
/**
 * Created by Sound.
 */
namespace YZ\Core\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use YZ\Core\Services\Exceptions\TransactionException;

class BaseService
{
    protected $serviceClass;
    protected $transactions = [];

    /**
     * BaseService constructor.
     * @throws ReflectionException
     */
    public function __construct()
    {
        $this->serviceClass = self::getClassInfo();
        $this->setTransactions();
    }

    /**
     * 根据注解绑定需要执行事务的方法
     */
    private function setTransactions()
    {
        $methods = $this->serviceClass->getMethods();
        foreach ($methods as $method) {
            if (strpos($method->getDocComment(), '@transaction')) {
                $this->transactions[$method->getName()] = $method;
            }
        }
    }

    /**
     * 调用方法
     * @param $methodName
     * @param $parameters
     * @return mixed
     * @throws ReflectionException
     * @throws TransactionException
     */
    public function invokeMethod($methodName, $parameters)
    {
        if (array_key_exists($methodName, $this->transactions)) {
            return $this->runTransaction($this->transactions[$methodName], $parameters);
        } else {
            return $this->serviceClass->getMethod($methodName)->invoke($this, ...$parameters);
        }
    }

    /**
     * 执行事务
     * @param ReflectionMethod $method
     * @param $parameters
     * @return mixed
     * @throws TransactionException
     * @throws Exception
     */
    protected function runTransaction(ReflectionMethod $method, $parameters)
    {
        DB::beginTransaction();
        try {
            $result = $method->invoke($this, ...$parameters);
            DB::commit();
            return $result;
        } catch (TransactionException $e) {
            DB::rollBack();
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @return ReflectionClass
     * @throws ReflectionException
     */
    static private function getClassInfo(): ReflectionClass
    {
        return new ReflectionClass(get_called_class());
    }

    /**
     * 创建Service实例
     * @return static
     */
    static public function createInstance()
    {
        $instance = resolve(get_called_class());
        ServiceProxy::bing($instance);
        return $instance;
    }
}