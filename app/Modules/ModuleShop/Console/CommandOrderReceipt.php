<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;

/**
 * 订单自动收货
 * Class CommandOrderAutoReceive
 * @package App\Modules\ModuleShop\Console
 */
class CommandOrderReceipt extends Command
{
    protected $name = 'OrderReceipt';
    protected $description = 'receive order after send time';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     */
    public function handle()
    {
        Log::writeLog('CommandOrderReceipt', 'start');
        // 获取已完全发货的订单
        $orderList = OrderModel::query()
            ->from('tbl_order as order')
            ->leftJoin('tbl_order_config as order_config', 'order.site_id', '=', 'order_config.site_id')
            ->where('order.status', Constants::OrderStatus_OrderSend)
            ->where('order_config.ordersend_success_day', '>', 0)
            ->whereNotNull('send_at')
            ->select('order.id', 'order.site_id', 'order.member_id', 'order.send_at', 'order_config.ordersend_success_day')
            ->orderBy('order.send_at', 'asc')
            ->get();
        $taskNum = count($orderList);
        Log::writeLog('CommandOrderReceipt', 'list ' . $taskNum);
        // 处理数据
        foreach ($orderList as $order) {
            try {
                $sendAt = $order->send_at;
                if (!$sendAt) {
                    continue;
                }
                $orderId = $order->id;
                $siteId = $order->site_id;
                $day = abs(intval($order->ordersend_success_day));
                // 检查配置
                if ($day == 0) {
                    continue;
                }
                // 初始化siteId
                Site::initSiteForCli($siteId);
                // 检查时间
                if (OrderHelper::timeRemain($order->send_at, $day)) {
                    continue;
                }
                // 确认收货，方法里包含了对状态的检测
                $shopOrder = ShopOrderFactory::createOrderByOrderId($orderId);
                if ($shopOrder->receipt()) {
                    Log::writeLog('CommandOrderReceipt', $orderId . ' is receipt send_at ' . $sendAt);
                }
            } catch (\Exception $ex) {
                Log::writeLog('CommandOrderReceipt', $orderId . ' is Error:' . $ex->getMessage());
            }
        }
        Log::writeLog('CommandOrderReceipt', 'finish');
    }
}