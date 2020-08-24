<?php
/**
 * Created by PhpStorm.
 * User: liyaohui
 * Date: 2019/3/29
 * Time: 15:43
 */

namespace App\Modules\ModuleShop\Console;

use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Shop\BaseShopOrder;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;
use Illuminate\Console\Command;
use YZ\Core\Logger\Log;
use YZ\Core\Model\MemberModel;
use YZ\Core\Site\Site;

class CommandDistributorChangeParent extends Command
{
    // cli 命令 memberId 为会员id 必须参数 retry 为重试次数 选填
    protected $signature = 'Task:distributorChangeParent {memberId} {--retry=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a Task when distributor change parent';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $retry = $this->option('retry')?:0;
        $memberId = $this->argument('memberId');
        try {
            // 订单成功，但没过维权期的状态
            $status = BaseShopOrder::getNoFinishStatusList();
            $memberModel = MemberModel::find($memberId);
            if($memberModel){
                Site::initSiteForCli($memberModel->site_id);
            }
			Log::writeLog('Task-distributorChangeParent', "member_id: $memberId");
            $orderHelp = new OrderHelper();
            $orderId = $orderHelp->getOrder($status, $memberId, true);
            foreach ($orderId as $k => $v) {
				Log::writeLog('Task-distributorChangeParent', "member_id: $memberId, order_id: $v[id]");
                ShopOrderFactory::createOrderByOrderId($v['id'])->doDistribution();
            }
        } catch (\Exception $e) {
            if ($retry < 5) {
                ++$retry;
                addTask(base_path() . '/artisan Task:distributorChangeParent '. $memberId . ' --retry=' . $retry, $retry);
            } else {
                Log::writeLog('TaskErrorDistributorChangeParent', $e->getMessage());
            }
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        /*return [
            ['example', InputArgument::REQUIRED, 'An example argument.'],
        ];*/
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        /*return [
            ['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];*/
        return [];
    }
}