<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Console;

use App\Modules\ModuleShop\Libs\Agent\AgentReward;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

class CommandAgentPerformanceReward extends Command
{
    protected $name = 'AgentPerformanceReward';
    protected $description = 'grant agent performance reward';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     * @throws \Exception
     */
    public function handle()
    {
        Log::writeLog('CommandAgentPerformanceReward', 'start');
        $siteList = DB::query()->from('tbl_agent_performance')->select('site_id')->distinct()->get();
        Log::writeLog('CommandAgentPerformanceReward', 'list ' . count($siteList));
        foreach ($siteList as $siteItem) {
            $siteId = $siteItem->site_id;
            try {
                if ($siteId) {
                    Site::initSiteForCli($siteId);
                    AgentReward::grantPerformanceReward();
                    Log::writeLog('CommandAgentPerformanceReward', $siteId . ' is finish');
                }
            } catch (\Exception $ex) {
                Log::writeLog('CommandAgentPerformanceReward', $siteId . ' is Error:' . $ex->getMessage());
            }
        }
        Log::writeLog('CommandAgentPerformanceReward', 'finish');
    }
}
