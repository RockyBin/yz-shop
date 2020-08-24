<?php

namespace App\Modules\ModuleShop\Jobs;

use App\Modules\ModuleShop\Libs\Agent\Agentor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

class UpgradeAgentLevelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    var $_memberId = -1;
    var $_params = [];

    /**
     * ResetAgentParentsJob constructor.
     * @param $memberId
     */
    public function __construct($memberId, $params = [])
    {
        if ($memberId) $this->_memberId = $memberId;
        $this->_params = $params;
    }

    /**
     * Execute
     */
    public function handle()
    {
        echo "UpgradeAgentLevelJob member_id:" . $this->_memberId . "\r\n";
        Log::writeLog('UpgradeAgentLevelJob', 'start member_id:' . $this->_memberId);
        $timeStart = microtime(true);
        try {
            $member = DB::table("tbl_member")->where('id', $this->_memberId)->select('id', 'site_id')->first();
            if ($member) {
                Site::initSiteForCli($member->site_id);
                Agentor::upgradeRelationAgentLevel($this->_memberId, $this->_params);
            }
            Log::writeLog('UpgradeAgentLevelJob', 'finish');
        } catch (\Exception $e) {
            Log::writeLog('UpgradeAgentLevelJob', 'Error:' . $e->getMessage());
        }
        $timeEnd = microtime(true);
        $timeUsed = round(($timeEnd - $timeStart), 4);
        Log::writeLog('UpgradeAgentLevelJob', 'end time use:' . $timeUsed);
        echo "UpgradeAgentLevelJob finish\r\n";
    }
}
