<?php
/**
 * 快递同步任务
 * User: liyaohui
 * Date: 2020/7/15
 * Time: 15:32
 */

namespace App\Modules\ModuleShop\Jobs;


use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Express\Express;
use App\Modules\ModuleShop\Libs\Express\ExpressConstants;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use YZ\Core\Logger\Log;
use YZ\Core\Task\QueueTask;

class OrderExpressSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueTask;

    protected $orderIds = [];
    protected $siteId;
    protected $type;

    /**
     * 同步订单到快递100
     * @param $orderIds
     * @param $siteId
     * @param $type
     */
    public function __construct($orderIds, $siteId, $type)
    {
        if (is_array($orderIds)) {
            $this->orderIds = $orderIds;
        } else {
            $this->orderIds = [$orderIds];
        }
        $this->siteId = $siteId;
        $this->type = $type;
    }

    public function handle()
    {
        try {
            Log::writeLog('expressJob', 'siteId:' . $this->siteId . ' start type:' . $this->type);
            $express = new Express(null, $this->siteId);
            // 检测状态
            $check = true;
            $setting = $express->getSetting();
            if (!$setting->checkStatus(false)) {
                $check = false;
                // 是否是token过期
                if ($setting->needRefreshToken()) {
                    // 重新刷新token
                    $check = $setting->refreshToken();
                }
            }
            // 检测通过
            if ($check) {
                $orderList = [];
                if ($this->orderIds) {
                    $orderList = OrderModel::query()
                        ->whereIn('id', $this->orderIds)
                        ->where('site_id', $this->siteId)
                        ->where('virtual_flag', 0);
                    // 根据类型去筛选订单列表
                    switch ($this->type) {
                        // 导入订单
                        case ExpressConstants::OrderSynType_Send:
                            $orderList = $orderList->whereIn('express_sync_status',
                                [
                                    ExpressConstants::OrderSynStatus_NoSync,
                                    ExpressConstants::OrderSynStatus_SyncFail,
                                    ExpressConstants::OrderSynStatus_InSync,
                                ])
                                ->where('status', Constants::OrderStatus_OrderPay)
                                ->with('items')
                                ->get();
                            break;
                            // 更新订单
                        case ExpressConstants::OrderSynType_Update:
                            $orderList = $orderList->whereIn('express_sync_status',
                                [
                                    ExpressConstants::OrderSynStatus_SyncSuccessed,
                                    ExpressConstants::OrderSynStatus_UpdateFail,
                                ])
                                ->where('status', Constants::OrderStatus_OrderPay)
                                ->with('items')
                                ->get();
                            break;
                            // 关闭订单
                        case ExpressConstants::OrderSynType_Cancel:
                            $orderList = $orderList->whereIn('express_sync_status',
                                [
                                    ExpressConstants::OrderSynStatus_SyncSuccessed,
                                    ExpressConstants::OrderSynStatus_UpdateSuccessed
                                ])
                                ->where(function($query){
                                    $query->where('delivery_status', '!=', Constants::OrderDeliveryStatus_No)
                                        ->orWhere('status', Constants::OrderStatus_OrderClosed);
                                })
                                ->get();
                            break;
                            // 未知类型
                        default:
                            $orderList = [];

                    }
                }
                if (count($orderList)) {
                    // 订单关闭
                    if ($this->type == ExpressConstants::OrderSynType_Cancel) {
                        // 一次最多10个订单 300个字符 我们的订单id为30个字符 所以只能最多9个订单id
                        $ids = $orderList->pluck('id');
                        $page = ceil($ids->count() / 9);
                        for ($i = 1; $i <= $page; $i++) {
                            $id = $ids->forPage($i, 9)->all();
                            $express->orderCancel(['order_list' => implode(',', $id)]);
                        }
                    } else {
                        foreach ($orderList as $order) {
                            switch ($this->type) {
                                // 导入订单
                                case ExpressConstants::OrderSynType_Send:
                                    $express->orderSend($order, $order['id']);
                                    break;
                                // 更新订单
                                case ExpressConstants::OrderSynType_Update:
                                    $express->orderUpdate($order, $order['id']);
                                    break;
                            }
                        }
                    }

                }
            }
            Log::writeLog('expressJob', 'siteId:' . $this->siteId . ' end');
        } catch (\Exception $e) {
            dd($e);
            Log::writeLog('expressJobError', 'siteId:' . $this->siteId . "\n\r" . $e->getMessage());
        }
    }
}