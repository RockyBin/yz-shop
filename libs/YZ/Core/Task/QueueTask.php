<?php
namespace YZ\Core\Task;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Bus\PendingDispatch;

trait QueueTask
{
    /**
     * 用来对相同组中的任务进行并发控制，这通常是用在需要保证相同组的任务不能并发执行的场景下
     * @param $group 任务组名称
     * @param $taskArgs 任务的参数
     * @return bool
     */
    public static function checkHandle($group,$taskArgs)
    {
        if($group){
            echo "checklimit $group :".static::checkLimit($group)."\r\n";
            
            if(!static::checkLimit($group)){
                $job = (new static(...$taskArgs))->delay(Carbon::now()->addSeconds(3));
                new PendingDispatch($job);
                //Redis::setex('TaskNum:'.$group, 60 ,static::getTaskNum($group));
                echo static::class."(".var_export($taskArgs,true)."),group $group is running, delay\r\n";
                return false;
            }
            Redis::setex('TaskNum:'.$group, 60, static::getTaskNum($group) + 1); //暂时认为任务必须在60秒内完全执行完，所以设置 redis 的过期时间为 60，否则一个地方不注意会导致有些任务再也无法下达
            echo static::class."(".var_export($taskArgs,true)."),group $group start\r\n";
        }
        return true;
    }

    /**
     * 检测任务组中正在执行的任务数是否超出限制
     * @param $group 任务组名称
     * @return bool
     */
    public static function checkLimit($group){
        if ($group) {
            $limit = 1; //系统目前是只要有指定组名，都默认限制只能有一个相同任务在跑
            $limitConfig = config('jobgrouplimit');
            if ($limitConfig[$group]) {
                $limit = $limitConfig[$group];
            }
            $num = Redis::get('TaskNum:'.$group);
            if ($num >= $limit) {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取某任务组中正在执行的任务数
     * @param $group 任务组名称
     * @return int
     */
    public static function getTaskNum($group){
        $num = Redis::get('TaskNum:'.$group);
        if($num){
            return $num;
        }
        return 0;
    }

    /**
     * 当某任务运行完成时，将此任务组的执行任务数减1
     * @param $group 任务组名称
     */
    public static function decreaseTaskNum($group){
        $num = Redis::get('TaskNum:'.$group);
        if(intval($num) > 0){
            Redis::setex('TaskNum:'.$group, 60, $num - 1);
        }
    }
}
