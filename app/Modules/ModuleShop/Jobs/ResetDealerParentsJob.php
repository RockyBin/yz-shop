<?php

namespace App\Modules\ModuleShop\Jobs;

use App\Modules\ModuleShop\Libs\Dealer\DealerHelper;
use App\Modules\ModuleShop\Libs\Agent\Agentor;
use App\Modules\ModuleShop\Libs\Dealer\DealerRecommendReward;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use YZ\Core\Task\QueueTask;

class ResetDealerParentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueTask;

    var $_memberId = -1;
    var $group = '';
    var $oldAgentLevel = 0;
    var $dealerLevel = 0;

    /**
     * ResetDealerParentsJob constructor.
     * @param $memberId
     */
    public function __construct($memberId,$group = '', $oldAgentLevel = 0, $dealerLevel = 0)
    {
        if ($memberId) $this->_memberId = $memberId;
        if ($group) $this->group = $group;
        $this->oldAgentLevel = $oldAgentLevel;
        $this->dealerLevel = $dealerLevel;
    }

    /**
     * Execute
     */
    public function handle()
    {
        if(!$this->checkHandle($this->group,[$this->_memberId,$this->group])) return;
        echo "ResetDealerParentsJob member_id:" . $this->_memberId . "\r\n";
        Log::writeLog('ResetDealerParentsJob', 'start member_id:' . $this->_memberId);
        $timeStart = microtime(true);
        try {
            $member = DB::table("tbl_member")->where('id', $this->_memberId)->select('id', 'site_id')->first();
            if ($member) {
                Site::initSiteForCli($member->site_id);
                DealerHelper::resetDealerParentRelationTree($this->_memberId);
                $this->decreaseTaskNum($this->group);
                //因为上下级关系可能发生了改变，导致某些经销商会升级
                DealerHelper::upgradeRelationDealerLevel($this->_memberId);
                // 根据重新绑定的关系 去算推荐奖
                DealerRecommendReward::createRecommendReward($this->_memberId, $this->oldAgentLevel, $this->dealerLevel);
            }
            Log::writeLog('ResetDealerParentsJob', 'finish');
        } catch (\Exception $e) {
            Log::writeLog('ResetDealerParentsJob', 'Error:' . $e->getMessage());
        }
        $timeEnd = microtime(true);
        $timeUsed = round(($timeEnd - $timeStart), 4);
        Log::writeLog('ResetDealerParentsJob', 'end time use:' . $timeUsed);
        echo microtime_format('Y-m-d H:i:s.x')." ResetDealerParentsJob finish\r\n";
    }
}
