<?php
namespace App\Modules\ModuleShop\Console;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerPerformanceReward;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

class CommandDealerPerformanceReward extends Command
{
    protected $name = 'DealerPerformanceReward';
    protected $description = 'grant dealer performance reward';

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
        return false;
        Log::writeLog('CommandDealerPerformanceReward', 'start');
        $siteList = DB::query()->from('tbl_statistics')->where('type',Constants::Statistics_MemberCloudStockPerformancePaid)->select('site_id')->distinct()->get(); //分析哪些站有业务统计数据
        foreach ($siteList as $siteItem) {
            $siteId = $siteItem->site_id;
            try {
                if ($siteId) {
                    Site::initSiteForCli($siteId);
                    DealerPerformanceReward::grantPerformanceReward();
                    Log::writeLog('CommandDealerPerformanceReward', $siteId . ' is finished');
                }
            } catch (\Exception $ex) {
                Log::writeLog('CommandDealerPerformanceReward', $siteId . ' is Error:' . $ex->getMessage());
            }
        }
        Log::writeLog('CommandDealerPerformanceReward', 'finished');
    }
}
