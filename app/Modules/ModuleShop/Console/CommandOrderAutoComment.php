<?php
/**
 * Created by Aison.
 */

namespace App\Modules\ModuleShop\Console;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Model\OrderItemModel;
use App\Modules\ModuleShop\Libs\Model\OrderModel;
use App\Modules\ModuleShop\Libs\Order\OrderHelper;
use App\Modules\ModuleShop\Libs\Product\ProductComment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use YZ\Core\Logger\Log;
use YZ\Core\Site\Site;

/**
 * 订单自动好评
 * Class CommandOrderAutoComment
 * @package App\Modules\ModuleShop\Console
 */
class CommandOrderAutoComment extends Command
{
    protected $name = 'OrderAutoComment';
    protected $description = 'order auto good comment';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行的方法
     */
    public function handle()
    {
        Log::writeLog('OrderAutoComment', 'start');

        // 获取未支付的订单
        $orderList = OrderModel::query()
            ->from('tbl_order as order')
            ->leftJoin('tbl_config as config', 'order.site_id', '=', 'config.site_id')
            ->where('config.product_comment_status', Constants::CommonStatus_Active)
            ->where('config.product_comment_auto_day', '>', 0)
            ->whereIn('order.status', [Constants::OrderStatus_OrderReceive, Constants::OrderStatus_OrderSuccess, Constants::OrderStatus_OrderFinished])
            ->where('order.comment_status', Constants::OrderCommentStatus_CanComment)
            ->whereNotNull('order.receive_at')
            ->select('order.id', 'order.site_id', 'order.member_id', 'order.receive_at', 'config.product_comment_auto_day')
            ->orderBy('order.receive_at', 'asc')
            ->get();
        $taskNum = count($orderList);
        Log::writeLog('OrderAutoComment', 'list ' . $taskNum);
        // 处理数据
        foreach ($orderList as $order) {
            $orderId = $order->id;
            $siteId = $order->site_id;
            try {
                $receiveAt = $order->receive_at;
                if (!$receiveAt) {
                    continue;
                }
                $day = abs(intval($order->product_comment_auto_day));
                // 检查配置
                if ($day == 0) {
                    continue;
                }
                // 初始化siteId
                Site::initSiteForCli($siteId);
                // 检查时间
                if (OrderHelper::timeRemain($order->receive_at, $day)) {
                    continue;
                }
                // 查找可评论的订单明细
                $orderItemList = OrderItemModel::query()
                    ->where('order_id', $orderId)
                    ->where('comment_status', Constants::OrderItemCommentStatus_NoComment)
                    ->where('after_sale_num', 0)
                    ->where('after_sale_over_num', 0)
                    ->whereRaw(DB::raw('id not in (select order_item_id from tbl_product_comment)'))
                    ->select('id', 'product_id')
                    ->get();
                foreach ($orderItemList as $orderItem) {
                    // 自动评论
                    $productComment = new ProductComment();
                    $productComment->add([
                        'order_id' => $orderId,
                        'product_id' => $orderItem->product_id,
                        'order_item_id' => $orderItem->id,
                        'member_id' => $order->member_id,
                        'type' => Constants::ProductCommentType_Comment,
                        'status' => Constants::ProductCommentStatus_Active,
                        'star' => 5,
                        'is_anonymous' => Constants::ProductCommentIsAnonymous_Yes,
                        'content' => trans('shop-front.comment.auto_content'),
                        'not_update_order_comment_status' => true, // 不更新订单的评论状态
                    ]);
                }
                // 更新订单的评论状态
                $orderHelper = new OrderHelper();
                $orderHelper->updateCommentStatus($orderId);
                // 写日志
                Log::writeLog('OrderAutoComment', $orderId . ' is due receive_at ' . $receiveAt);
            } catch (\Exception $ex) {
                Log::writeLog('OrderAutoComment', $orderId . ' is Error:' . $ex->getMessage());
            }
        }

        Log::writeLog('OrderAutoComment', 'finish');
    }
}