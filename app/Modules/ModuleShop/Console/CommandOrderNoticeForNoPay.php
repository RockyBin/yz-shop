<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Console;

use App\Modules\ModuleShop\Libs\Message\MessageNoticeHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;

/**
 * 支付订单发送通知
 * Class CommandOrderCloseForNoPay
 * @package App\Modules\ModuleShop\Console
 */
class CommandOrderNoticeForNoPay extends Command
{
    protected $name = 'OrderNoticeForNoPay';
    protected $description = 'notice member when order no pay';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     */
    public function handle()
    {
        Log::writeLog('CommandOrderNoticeForNoPay', 'start');
        // 获取未支付的订单
        $orderList = OrderModel::query()
            ->from('tbl_order as order')
            ->leftJoin('tbl_order_config as order_config', 'order.site_id', '=', 'order_config.site_id')
            ->where('order.status', Constants::OrderStatus_NoPay)
            ->where('order_config.nopay_notice_minute', '>', 0)
            ->select('order.*', 'order_config.nopay_notice_minute')
            ->orderBy('order.created_at', 'asc')
            ->get();
        $taskNum = count($orderList);
        Log::writeLog('CommandOrderNoticeForNoPay', 'list ' . $taskNum);
        // 处理数据
        foreach ($orderList as $order) {
            try {
                $orderId = $order->id;
                $createdAt = $order->created_at;
                $siteId = $order->site_id;
                $minute = abs(intval($order->nopay_notice_minute));
                // 检查配置
                if ($minute == 0) {
                    continue;
                }
                // 初始化siteId
                Site::initSiteForCli($siteId);
                // 检查时间
                $carbon = Carbon::parse($order->created_at);
                if ($carbon->diffInMinutes(null, false) == $minute) {
                    // 发送通知
                    MessageNoticeHelper::sendMessageOrderNoPay($order);
                    Log::writeLog('CommandOrderNoticeForNoPay', $orderId . ' created_at ' . $createdAt);
                }
            } catch (\Exception $ex) {
                Log::writeLog('CommandOrderNoticeForNoPay', $orderId . ' is Error:' . $ex->getMessage());
            }
        }
        Log::writeLog('CommandOrderNoticeForNoPay', 'finish');
    }
}