<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;

class CommandOrderFinish extends Command
{
    protected $name = 'OrderFinish';
    protected $description = 'finish order after receive time';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     */
    public function handle()
    {
        Log::writeLog('CommandOrderFinish', 'start');
        // 获取已完全发货的订单
        $orderList = OrderModel::query()
            ->from('tbl_order as order')
            ->leftJoin('tbl_order_config as order_config', 'order.site_id', '=', 'order_config.site_id')
            ->whereIn('order.status', [Constants::OrderStatus_OrderReceive, Constants::OrderStatus_OrderSuccess])
            ->whereRaw(DB::raw('((order_config.aftersale_isopen = 1 and order_config.ordersend_close_day > 0) or order_config.aftersale_isopen = 0)'))
            ->whereNotNull('receive_at')
            ->select('order.id', 'order.site_id', 'order.member_id', 'order.receive_at', 'order_config.ordersend_close_day', 'order_config.aftersale_isopen')
            ->orderBy('order.receive_at', 'asc')
            ->get();
        $taskNum = count($orderList);
        Log::writeLog('CommandOrderFinish', 'list ' . $taskNum);
        // 处理数据
        foreach ($orderList as $order) {
            try {
                $receiveAt = $order->receive_at;
                if (!$receiveAt) {
                    continue;
                }
                $orderId = $order->id;
                $siteId = $order->site_id;
                // 初始化siteId
                Site::initSiteForCli($siteId);
                if ($order->aftersale_isopen == 1) {
                    $day = abs(intval($order->ordersend_close_day));
                    // 检查配置
                    if ($day == 0) {
                        continue;
                    }
                    // 检查时间
                    if (OrderHelper::timeRemain($receiveAt, $day)) {
                        continue;
                    }
                }
                // 检查规则
                $orderHelper = new OrderHelper($siteId);
                $parseData = $orderHelper->parseStatusWithOrderItem($orderId);
                if (!$parseData['allDone'] || $parseData['hasAfterSaleIng']) {
                    continue;
                }

                // 确认收货，方法里包含了对状态的检测
                $shopOrder = ShopOrderFactory::createOrderByOrderId($orderId);
                $shopOrder->finish($parseData['allAfterSaleOver'] ? 1 : 0);
                Log::writeLog('CommandOrderFinish', $orderId . ' is finish receive_at ' . $receiveAt);
            } catch (\Exception $ex) {
                Log::writeLog('CommandOrderFinish', $orderId . ' is Error:' . $ex->getMessage());
            }
        }
        Log::writeLog('CommandOrderFinish', 'finish');
    }
}