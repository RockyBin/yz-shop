<?php

namespace App\Modules\ModuleShop\Jobs;

use App\Modules\ModuleShop\Libs\Agent\AgentHelper;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Task\QueueTask;

class ResetAgentParentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueTask;

    var $_memberId = -1;
    var $group = '';
    var $orderId = 0;

    /**
     * 刷新代理的关系链
     * @param $memberId 会员ID
     * @param string $group 任务分组，避免同一站点下的冲突
     * @param int $orderId 在订单支付成功后再绑定上下级关系时需要，用来在绑定关系后分佣
     */
    public function __construct($memberId,$group = '',$orderId = 0)
    {
        if ($memberId) $this->_memberId = $memberId;
        if ($group) $this->group = $group;
        if ($orderId) $this->orderId = $orderId;
    }

    /**
     * Execute
     */
    public function handle()
    {
        if(!$this->checkHandle($this->group,[$this->_memberId,$this->group])) return;
        echo "ResetAgentParentsJob member_id:" . $this->_memberId . "\r\n";
        Log::writeLog('ResetAgentParentsJob', 'start member_id:' . $this->_memberId);
        $timeStart = microtime(true);
        try {
            $member = DB::table("tbl_member")->where('id', $this->_memberId)->select('id', 'site_id')->first();
            if ($member) {
                Site::initSiteForCli($member->site_id);
                AgentHelper::resetAgentParentRelationTree($this->_memberId);
                $this->decreaseTaskNum($this->group);
                //如果有传订单号，在这里进行订单分佣等
                if($this->orderId){
                    $order = ShopOrderFactory::createOrderByOrderId($this->orderId);
                    $order->payAfterUpdateMemberAndCommission();
                }
                //因为上下级关系可能发生了改变，导致某些代理会升级
                Agentor::upgradeRelationAgentLevel($this->_memberId);
            }
            Log::writeLog('ResetAgentParentsJob', 'finish');
        } catch (\Exception $e) {
            Log::writeLog('ResetAgentParentsJob', 'Error:' . $e->getMessage());
        }
        $timeEnd = microtime(true);
        $timeUsed = round(($timeEnd - $timeStart), 4);
        Log::writeLog('ResetAgentParentsJob', 'end time use:' . $timeUsed);
        echo microtime_format('Y-m-d H:i:s.x')." ResetAgentParentsJob finish\r\n";
    }
}
