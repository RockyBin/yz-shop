<?php
namespace YZ\Core\Common;

use Exception;
use SwooleTW\Http\Coroutine\Context;

/**
 * 这个类是用来对 $_SERVER 的信息做得封装，以便在正常环境和swoole环境下可以拿到正确的 $_SERVER 相关的信息
 * Class ServerInfo
 * @package YZ\Core\Common
 */
class ServerInfo
{
    public static function get($key){
        try{
            $data = static::getAll();
            return $data[$key];
        }catch(Exception $exception){
            \Log::error($exception->getMessage());
        }
    }

    public static function getAll(){
        if(isSwoole()){
            return Context::getData('_SERVER'); //swoole context 的 _SERVER 在 ResetSuperGlobalVars 里定义了
        }else{
            return $_SERVER;
        }
    }
}