<?php

namespace App\Modules\ModuleShop\Observer;
use App\Modules\ModuleShop\Jobs\UpgradeDealerLevelJob;
use App\Modules\ModuleShop\Libs\Constants;
use App\Modules\ModuleShop\Libs\Dealer\DealerOrderRewardService;
use App\Modules\ModuleShop\Libs\Dealer\DealerSaleReward;
use App\Modules\ModuleShop\Libs\Entities\CloudstockPurchaseOrderEntity;
use App\Modules\ModuleShop\Libs\Model\CloudStockPurchaseOrderModel;
use App\Modules\ModuleShop\Libs\Statistics\MemberStatistics\MemberStatistics;
use YZ\Core\Entities\Utils\EntityExecutionOptions;
use YZ\Core\Services\ServiceProxy;

/**
 * 云仓进货单观察者模型
 */
class CloudStockPurchaseOrderObserver {

    /**
     * 进货单更新时，用来执行一些事件，比如统计业绩等
     *
     * @param CloudStockPurchaseOrderModel $model
     * @return void
     * @throws \Exception
     */
    public function updated(CloudStockPurchaseOrderModel $model)
    {
        //支付状态改变时
        $oldPayStatus = $model->getOriginal('payment_status');
        if($model->payment_status == Constants::CloudStockPurchaseOrderPaymentStatus_Yes && $oldPayStatus != $model->payment_status){
            MemberStatistics::addCloudStockPerformance($model, Constants::Statistics_MemberCloudStockPerformancePaid);
            UpgradeDealerLevelJob::dispatch($model->member_id);
        }

        //订单状态改变时
        $oldStatus = $model->getOriginal('status');
        if($model->status == Constants::CloudStockPurchaseOrderStatus_Finished && $oldStatus != $model->status) {
            MemberStatistics::addCloudStockPerformance($model, Constants::Statistics_MemberCloudStockPerformanceFinished);
            UpgradeDealerLevelJob::dispatch($model->member_id);
            // 经销商销售奖
            DealerSaleReward::createSaleReward($model);

            // 经销商订单返现奖
            $dealerOrderRewardService = DealerOrderRewardService::createInstance();
            $dealerOrderRewardService->createDealerOrderReward(new CloudstockPurchaseOrderEntity($model, new EntityExecutionOptions(2)));
        }
    }
}