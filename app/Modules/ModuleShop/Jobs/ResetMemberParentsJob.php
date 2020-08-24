<?php

namespace App\Modules\ModuleShop\Jobs;

use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use App\Modules\ModuleShop\Libs\Dealer\DealerHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use YZ\Core\Member\Member;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;
use YZ\Core\Task\QueueTask;

class ResetMemberParentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueTask;

    var $memberId = -1;
    var $group = '';
    var $orderId = 0;

    /**
     * 刷新会员的关系链
     * @param $memberId 会员ID
     * @param string $group 任务分组，避免同一站点下的冲突
     * @param int $orderId 在订单支付成功后再绑定上下级关系时需要，用来在绑定关系后分佣
     */
    public function __construct($memberId,$group = '',$orderId = 0)
    {
        if ($memberId) $this->memberId = $memberId;
        if ($group) $this->group = $group;
        if ($orderId) $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(!$this->checkHandle($this->group,[$this->memberId,$this->group])) return;
        echo microtime_format('Y-m-d H:i:s.x')."ResetMemberParentsJob member_id:" . $this->memberId . "\r\n";
        Site::initSiteForCli(MemberModel::find($this->memberId)->site_id);
        // 刷新此会员的上家关系表
        Member::resetSubMemerParentsWithOldParent($this->memberId);
        $this->decreaseTaskNum($this->group);
        echo microtime_format('Y-m-d H:i:s.x')." ResetMemberParentsJob step2\r\n";
        // 更新团队关系的队列任务
        AgentHelper::dispatchResetAgentParentsJob($this->memberId,$this->orderId);
        // 更新经销商关系的队列任务
        DealerHelper::dispatchResetDealerParentsJob($this->memberId);
        echo "ResetMemberParentsJob finish\r\n";
    }
}
