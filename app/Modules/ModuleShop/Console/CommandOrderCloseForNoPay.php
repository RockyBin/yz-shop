<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;

/**
 * 自动关闭未支付订单
 * Class CommandOrderCloseForNoPay
 * @package App\Modules\ModuleShop\Console
 */
class CommandOrderCloseForNoPay extends Command
{
    protected $name = 'OrderCloseForNoPay';
    protected $description = 'close order when no pay';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     */
    public function handle()
    {
        Log::writeLog('CommandOrderCloseForNoPay', 'start');
        // 获取未支付的订单
        $orderList = OrderModel::query()
            ->from('tbl_order as order')
            ->leftJoin('tbl_order_config as order_config', 'order.site_id', '=', 'order_config.site_id')
            ->where('order.status', Constants::OrderStatus_NoPay)
            ->where(function (Builder $subQuery) {
                $subQuery->orWhere('order_config.nopay_close_day', '>', 0)
                    ->orWhere('order_config.nopay_close_hour', '>', 0)
                    ->orWhere('order_config.nopay_close_minute', '>', 0);
            })
            ->select('order.id', 'order.site_id', 'order.member_id', 'order.created_at', 'order_config.nopay_close_day', 'order_config.nopay_close_hour', 'order_config.nopay_close_minute')
            ->orderBy('order.created_at', 'asc')
            ->get();
        $taskNum = count($orderList);
        Log::writeLog('CommandOrderCloseForNoPay', 'list ' . $taskNum);
        // 处理数据
        foreach ($orderList as $order) {
            try {
                $orderId = $order->id;
                $createdAt = $order->created_at;
                $siteId = $order->site_id;
                $day = abs(intval($order->nopay_close_day));
                $hour = abs(intval($order->nopay_close_hour));
                $minute = abs(intval($order->nopay_close_minute));
                // 检查配置
                if ($day == 0 && $hour == 0 && $minute == 0) {
                    continue;
                }
                // 初始化siteId
                Site::initSiteForCli($siteId);
                // 检查时间
                if (OrderHelper::timeRemain($order->created_at, $day, $hour, $minute)) {
                    continue;
                }
                // 关闭订单
                $shopOrder = ShopOrderFactory::createOrderByOrderId($orderId, false);
                $shopOrder->cancel('自动关闭');
                Log::writeLog('CommandOrderCloseForNoPay', $orderId . ' is close created_at ' . $createdAt);
            } catch (\Exception $ex) {
                Log::writeLog('CommandOrderCloseForNoPay', $orderId . ' is Error:' . $ex->getMessage());
            }
        }
        Log::writeLog('CommandOrderCloseForNoPay', 'finish');
    }
}