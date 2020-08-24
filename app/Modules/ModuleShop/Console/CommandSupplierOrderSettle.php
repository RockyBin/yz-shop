<?php
namespace App\Modules\ModuleShop\Console;

use App\Modules\ModuleShop\Libs\Model\Supplier\SupplierBaseSettingModel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Shop\ShopOrderFactory;

class CommandSupplierOrderSettle extends Command
{
    protected $name = 'SupplierOrderSettle';
    protected $description = 'settle the supplier orders';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     */
    public function handle()
    {
        Log::writeLog('CommandSupplierOrderSettle', 'start');

        // 获取已完全发货的订单
        $orderList = OrderModel::query()
            ->from('tbl_order as order')
            ->leftJoin('tbl_supplier_base_setting as config', 'config.site_id', '=', 'order.site_id')
            ->leftJoin('tbl_supplier_settle as settle', 'order.id', '=', 'settle.order_id')
            ->whereIn('order.status', [Constants::OrderStatus_OrderFinished,Constants::OrderStatus_OrderClosed])
            ->where('order.supplier_member_id', '>', 0)
            ->where('settle.status', 0)
            ->whereRaw('TO_DAYS(NOW()) - TO_DAYS(order.end_at) >= config.settlement_period')
            ->whereRaw('(settle.money + settle.freight + settle.after_sale_money + settle.after_sale_freight) > 0')
            ->select('order.id', 'order.site_id', 'order.member_id','order.end_at')
            ->orderBy('order.end_at', 'asc')
            ->get();
        $taskNum = count($orderList);
        Log::writeLog('CommandSupplierOrderSettle', 'list ' . $taskNum);
        // 处理数据
        foreach ($orderList as $order) {
            try {
                $orderId = $order->id;
                $siteId = $order->site_id;
                // 初始化siteId
                Site::initSiteForCli($siteId);

                // 确认收货，方法里包含了对状态的检测
                $shopOrder = ShopOrderFactory::createOrderByOrderId($orderId);
                $shopOrder->activeSettleData();
                Log::writeLog('CommandSupplierOrderSettle', $orderId . ' is ok');
            } catch (\Exception $ex) {
                Log::writeLog('CommandSupplierOrderSettle', $orderId . ' is error:' . $ex->getMessage());
            }
        }
        Log::writeLog('CommandSupplierOrderSettle', 'finish');
    }
}