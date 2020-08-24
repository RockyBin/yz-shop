<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/2
 * Time: 16:41
 */

namespace YZ\Core\Events;

use YZ\Core\Logger\Log;

/**
 * 事件管理器
 * Class Event
 * @package YZ\Core\Events
 */
class Event
{
    /**
     * 执行事件的单个处理程序，处理程序由外部应用系统指定
     * @param $eventName
     * @param $callBack
     * @params 可选的事件处理程序参数，可以有N个，和具体的事件处理程序的定义有关，这里只负责传递过去，当参数是对象时，它是一个引用，在事件内改变参数值时，在外部会反映出来
     * @throws \Exception
     */
    public static function fireEvent($eventName,$callBack){
        $args = array_slice(func_get_args(), 2);
        try {
            if (is_string($callBack)) {
                if(substr($callBack,0,1) == '\\'){ //以 \ 开头，认为是类名
                    Log::writelog('event', "fireEvent $eventName => $callBack");
                    $classInfo = explode('@',$callBack);
                    return call_user_func_array(array(new $classInfo[0](), $classInfo[1] ? $classInfo[1] : "handle"), $args);
                } elseif (preg_match('/^https?:/i', $callBack)) {
                    throw new \Exception('未实现');
                }
            } elseif ($callBack instanceof \Closure) {
                Log::writelog('event', "fireEvent $eventName => ".var_export($callBack,true));
                return call_user_func_array($callBack, $args);
            }
        }catch (\Exception $ex){
            $errmsg = "fireEvent: $eventName, Error: ".$ex->getMessage();
            $errmsg .= ", callback: ".var_export($callBack,true);
            $errmsg .= ", Args: ".var_export($args,true);
            $errmsg .= ", Error: ".$ex->getMessage().", Trace: ".$ex->getTraceAsString();
            //throw new \Exception($errmsg);
            Log::writelog('event_error', $errmsg);
        }
    }

    /**
     * 执行事件的N个处理程序，处理程序数组由外部应用系统指定
     * @param $eventName
     * @param array $callBacks
     * @params 可选的事件处理程序参数，可以有N个，和具体的事件处理程序的定义有关，这里只负责传递过去，当参数是对象时，它是一个引用，在事件内改变参数值时，在外部会反映出来
     * @throws \Exception
     */
    public static function fireEvents($eventName,$callBacks = array()){
        $args = array_slice(func_get_args(),2);
        foreach ($callBacks as $cb){
            call_user_func_array(array('\YZ\Core\Events\Event', "fireEvent"), array_merge([$eventName,$cb],$args));
        }
    }
}