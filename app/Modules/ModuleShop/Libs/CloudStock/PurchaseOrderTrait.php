<?php
namespace App\Modules\ModuleShop\Libs\CloudStock;

use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Member\Member;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Model\DealerParentsModel;
use App\Modules\ModuleShop\Libs\Statistics\MemberStatistics\MemberStatistics;
use Illuminate\Support\Facades\DB;
use YZ\Core\Site\Site;

trait PurchaseOrderTrait
{
    /**
     * 进货单财务审核成功时要做的事情，比如统计业绩等
     * @param $orderId
     */
    //public static function afterMoneyVerify($orderId){
    //    MemberStatistics::addCloudStockPerformance($orderId, Constants::Statistics_MemberCloudStockPerformancePayed);
	//}

    /**
     * 进货单配仓完成时要做的事情，比如统计业绩等
     * @param $orderId
     */
	//public static function afterStockDeliver($orderId){
    //    MemberStatistics::addCloudStockPerformance($orderId, Constants::Statistics_MemberCloudStockPerformanceFinished);
	//}

    /**
     * 记录下云仓订单的关系历史数据
     * @param $orderId
     * @param bool $reset
     */
    public static function buildOrderMembersHistory($orderId)
    {
        if (!$orderId) return;
        $siteId = Site::getCurrentSite()->getSiteId();
        // 获取付过款的订单
        $orderModel = CloudStockPurchaseOrderModel::query()->where('site_id', $siteId)
            ->where('id', $orderId)
            ->first();
        if (!$orderModel) return;
        $member = new Member($orderModel->member_id);
        $memberModel = $member->getModel();

        // 暂时只记录经销商关系
        if ($memberModel->dealer_level > 0) {
            $insertDataList[] = [
                'site_id' => $siteId,
                'order_id' => $orderId,
                'member_id' => $memberModel->id,
                'type' => 1,
                'level' => 0,
            ];
            $dealerParentsList = DealerParentsModel::query()->where('site_id', $siteId)
                ->where('member_id', $memberModel->id)
                ->orderBy('level')
                ->groupBy('parent_id')
                ->get();
            foreach ($dealerParentsList as $item) {
                $insertDataList[] = [
                    'site_id' => $siteId,
                    'order_id' => $orderId,
                    'member_id' => $item->parent_id,
                    'type' => 1,
                    'level' => $item->level
                ];
            }
        }
        // 批量插入
        DB::table('tbl_cloudstock_purchase_order_history')->where('order_id',$orderId)->delete();
        if (count($insertDataList) > 0) {
            DB::table('tbl_cloudstock_purchase_order_history')->insert($insertDataList);
        }
    }
}