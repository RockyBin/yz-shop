<?php
/**
 * 拼团自动关闭
 * User: liyaohui
 * Date: 2020/4/16
 * Time: 18:44
 */

namespace App\Modules\ModuleShop\Console;


use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuying;
use App\Modules\ModuleShop\Libs\GroupBuying\GroupBuyingConstants;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingModel;
use App\Modules\ModuleShop\Libs\Model\GroupBuyingSettingModel;
use Illuminate\Console\Command;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

class CommandGroupBuyingCloseForNoTime extends Command
{
    protected $name = 'GroupBuyingCloseForNoTime';
    protected $description = 'close group buying when no time';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     */
    public function handle()
    {
        Log::writeLog('CommandGroupBuyingCloseForNoTime', 'start');
        // 未成团 并且结束时间已经到了的拼团活动
        $list = GroupBuyingModel::query()
            ->where('status', GroupBuyingConstants::GroupBuyingTearmStatus_No)
            ->where('end_time', '<=', date('Y-m-d H:i:s'))
            ->get();
        $taskNum = $list->count();
        Log::writeLog('CommandGroupBuyingCloseForNoTime', 'list ' . $taskNum);
        foreach ($list as $item) {
            try {
                // 初始化siteId
                Site::initSiteForCli($item->site_id);
                // 获取配置 看是否需要模拟成团
                $setting = GroupBuyingSettingModel::query()
                    ->where('site_id', $item->site_id)
                    ->where('id', $item->group_buying_setting_id)
                    ->where('is_delete', 0)
                    ->first();
                // 没有模拟成团 直接关闭当前团和当前团的相关订单
                if (!$setting || !$setting->open_mock_group) {
                    Log::writeLog('CommandGroupBuyingCloseForNoTime', $item->id . ' start closed');
                    GroupBuying::cancelGroupBuying($item->id);
                    Log::writeLog('CommandGroupBuyingCloseForNoTime', $item->id . ' is closed');
                } else {
                    // 有模拟成团 时间到了直接成团 修改相关订单状态
                    Log::writeLog('CommandGroupBuyingCloseForNoTime', $item->id . ' start mock');
                    GroupBuying::mockGroupBuyingSuccess($item->id);
                    Log::writeLog('CommandGroupBuyingCloseForNoTime', $item->id . ' is mock success');
                }
            } catch (\Exception $e) {
                Log::writeLog('CommandGroupBuyingCloseForNoTime', $item->id . ' is Error:' . $e->getMessage());
            }
        }
        Log::writeLog('CommandGroupBuyingCloseForNoTime', 'finish');
    }
}