<?php
namespace YZ\Core\Task;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TaskHelper{

    /**
     * 任务类型，默认，异步任务（不需要按顺序执行的，只是用来做异步处理）
     */
    const QueueTaskType_Default = 'default';

    /**
     * 任务类型，队列任务（要按顺序执行的）
     */
    const QueueTaskType_Queue = 'queue';

    /**
     * 生成任务组的ID
     *
     * @param int $siteId 网站ID
     * @param string $groupType 组类别，一般是一个字符串
     * @param string $id 相关ID，如会员ID等
     * @return string
     */
    public static function createTaskGroupId($siteId,$groupType,$id = ''){
        return "Site_".$siteId."_".$groupType.($id ? "_".$id : "");
    }

    /**
     * 获取更改会员上家的任务组ID
     *
     * @param int $siteId
     * @return string
     */
    public static function createChangeMemberParentTaskGroupId($siteId){
        return "Site_".$siteId."_ChangeMemberParent";
    }

    /**
     * 获取更改代理上家的任务组ID
     *
     * @param int $siteId
     * @return string
     */
    public static function createChangeAgentParentTaskGroupId($siteId){
        return "Site_".$siteId."_ChangeAgentParent";
    }

    /**
     * 获取更改经销商上家的任务组ID
     *
     * @param int $siteId
     * @return string
     */
    public static function createChangeDealerParentTaskGroupId($siteId){
        return "Site_".$siteId."_ChangeDealerParent";
    }

    /**
     * 添加一个任务到队列
     * @param ShouldQueue $queue 任务实例
     * @param string $queueTaskType 任务添加到哪个队列分组，默认 default，当要指定队列分组时，不要直接写字符串，而是使用 TaskUtil::QueueTaskType_XXX 常量，目前只支持 default
     */
    public static function addTask(ShouldQueue $queue,string $queueTaskType = 'default'){
        if($queueTaskType !== static::QueueTaskType_Default) dispatch($queue)->onQueue($queueTaskType);
        else dispatch($queue);
    }
}