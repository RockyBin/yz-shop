<?php
namespace YZ\Core\Common;

use SwooleTW\Http\Coroutine\Context;

class DataCache
{
    public static function getData($key){
        if(isSwoole()){
            $c = Context::getData('_cache_'.$key);
            return $c;
        }else{
            return $GLOBALS[$key];
        }
    }

    public static function setData($key,$data){
        if(isSwoole()){
            Context::setData('_cache_'.$key,$data);
        }else{
            return $GLOBALS[$key] = $data;
        }
    }

    public static function has($key){
        if(isSwoole()){
            return array_search($key, Context::getDataKeys()) !== false;
        }else{
            return array_key_exists($key, $GLOBALS);
        }
    }

    public static function remove($key){
        if(isSwoole()){
            Context::removeData($key);
        }else{
            unset($GLOBALS[$key]);
        }
    }

    public static function getKeys(){
        if(isSwoole()){
            return Context::getDataKeys();
        }else{
            return array_keys($GLOBALS);
        }
    }
}