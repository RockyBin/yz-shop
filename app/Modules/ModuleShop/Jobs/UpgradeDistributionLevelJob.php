<?php
/**
 * 分销商升级任务
 * User: liyaohui
 * Date: 2019/10/25
 * Time: 14:30
 */

namespace App\Modules\ModuleShop\Jobs;


use App\Modules\ModuleShop\Libs\Distribution\Distributor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;

class UpgradeDistributionLevelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    var $_memberId = -1;
    var $_params = [];

    /**
     * ResetAgentParentsJob constructor.
     * @param $memberId
     */
    public function __construct($memberId ,$params = [])
    {
        if ($memberId) $this->_memberId = $memberId;
        $this->_params = $params;
    }

    /**
     * Execute
     */
    public function handle()
    {
        echo "UpgradeDistributionLevelJob member_id:" . $this->_memberId . "\r\n";
        Log::writeLog('UpgradeDistributionLevelJob', 'start member_id:' . $this->_memberId);
        $timeStart = microtime(true);
        try {
            $member = DB::table("tbl_member")->where('id', $this->_memberId)->select('id', 'site_id')->first();
            if ($member) {
                Site::initSiteForCli($member->site_id);
                Distributor::upgradeRelationDistributorLevel($this->_memberId,$this->_params);
            }
            Log::writeLog('UpgradeDistributionLevelJob', 'finish');
        } catch (\Exception $e) {
            Log::writeLog('UpgradeDistributionLevelJob', 'Error:' . $e->getMessage());
        }
        $timeEnd = microtime(true);
        $timeUsed = round(($timeEnd - $timeStart), 4);
        Log::writeLog('UpgradeDistributionLevelJob', 'end time use:' . $timeUsed);
        echo "UpgradeDistributionLevelJob finish\r\n";
    }
}