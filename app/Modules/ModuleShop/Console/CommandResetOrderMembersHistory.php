<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Console;

use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use Illuminate\Console\Command;
use YZ\Core\Model\SiteModel;
use YZ\Core\Site\Site;

class CommandResetOrderMembersHistory extends Command
{
    protected $signature = 'ResetOrderMembersHistory {--reset=} {--order_id=} {--site_id=}';

    protected $name = 'ResetOrderMembersHistory';

    protected $description = 'reset tbl_order_members_history data，irreversible';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $orderId = $this->option('order_id');
        $siteId = $this->option('site_id');
        $reset = $this->option('reset') ? true : false; // 如果之前有数据，是否强行重置
        if ($orderId) {
            // 处理当个订单
            $orderModel = OrderModel::query()->where('id', $orderId)->first();
            if ($orderModel) {
                Site::initSiteForCli($orderModel->site_id);
                OrderHelper::buildOrderMembersHistory($orderId, $reset);
                echo 'finish';
            } else {
                echo 'order_id ' . $orderId . ' not exist!';
            }
        } else if (is_numeric($siteId)) {
            // 处理整个站点
            Site::initSiteForCli($siteId);
            OrderHelper::buildOrderMembersHistoryForSite($reset);
            echo 'finish';
        } else if (strtolower($siteId) == 'all') {
            $siteModelList = SiteModel::query()->get();
            foreach ($siteModelList as $siteModel) {
                $siteId = $siteModel->site_id;
                echo $siteId . ' #';
                Site::initSiteForCli($siteId);
                OrderHelper::buildOrderMembersHistoryForSite($reset);
            }
            echo 'finish';
        } else {
            echo 'param miss';
        }
    }
}